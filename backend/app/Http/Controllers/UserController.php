<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = User::with('userGroup', 'creator');

            // Super admin vê todos os usuários
            // Admins veem apenas usuários criados por eles (subordinados)
            if (!$user->isSuperAdmin()) {
                $query->where('created_by', $user->id);
            }

            $users = $query->get();
            return response()->json(['users' => $users]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch users'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = auth()->user();

        // Apenas super admin e admins podem criar usuários
        if (!$currentUser->isAdmin()) {
            return response()->json(['error' => 'Acesso negado. Apenas administradores podem criar usuários.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'user_type' => 'required|string|in:user,admin',
            'user_group_id' => 'nullable|exists:user_groups,id',
            'role' => 'sometimes|string|in:admin,user',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Apenas super admin pode criar administradores
        if ($request->user_type === 'admin' && !$currentUser->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso negado. Apenas super administradores podem criar administradores.'], 403);
        }

        try {
            // Se user_type for admin, role também deve ser admin
            $role = $request->role ?? ($request->user_type === 'admin' ? 'admin' : 'user');
            
            // Se não for super admin, força user_type como 'user'
            $userType = $request->user_type;
            if (!$currentUser->isSuperAdmin() && $userType === 'admin') {
                $userType = 'user';
                $role = 'user';
            }
            
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password, // Will be hashed by mutator
                'user_type' => $userType,
                'user_group_id' => $request->user_group_id,
                'role' => $role,
                'created_by' => $currentUser->id, // Vincula ao admin que criou
                'active' => $request->active ?? true,
            ]);

            return response()->json(['user' => $user], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create user'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $currentUser = auth()->user();
            $user = User::with('userGroup', 'creator')->findOrFail($id);

            // Super admin pode ver qualquer usuário
            // Admins só podem ver usuários criados por eles
            if (!$currentUser->isSuperAdmin() && $user->created_by !== $currentUser->id) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            return response()->json(['user' => $user]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'User not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch user'], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $currentUser = auth()->user();

        try {
            $user = User::findOrFail($id);

            // Super admin pode atualizar qualquer usuário
            // Admins só podem atualizar usuários criados por eles
            if (!$currentUser->isSuperAdmin()) {
                if ($user->created_by !== $currentUser->id) {
                    return response()->json(['error' => 'Acesso negado. Você só pode atualizar usuários criados por você.'], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
                'password' => 'sometimes|string|min:6',
                'user_type' => 'sometimes|string|in:user,admin',
                'user_group_id' => 'nullable|exists:user_groups,id',
                'role' => 'sometimes|string|in:admin,user',
                'active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Apenas super admin pode alterar user_type para admin
            if ($request->has('user_type') && $request->user_type === 'admin' && !$currentUser->isSuperAdmin()) {
                return response()->json(['error' => 'Acesso negado. Apenas super administradores podem criar ou alterar usuários para administradores.'], 403);
            }

            $updateData = $request->only(['name', 'email', 'user_type', 'user_group_id', 'role', 'active']);
            
            // Only update password if provided
            if ($request->has('password') && !empty($request->password)) {
                $updateData['password'] = $request->password; // Will be hashed by mutator
            }

            // Não permite alterar user_type para super_admin via API (deve ser feito manualmente no banco)
            if (isset($updateData['user_type']) && $updateData['user_type'] === 'super_admin') {
                unset($updateData['user_type']);
            }
            
            // Sincronizar role com user_type se user_type for alterado
            if (isset($updateData['user_type'])) {
                if ($updateData['user_type'] === 'admin' && (!isset($updateData['role']) || $updateData['role'] !== 'admin')) {
                    $updateData['role'] = 'admin';
                } elseif ($updateData['user_type'] === 'user' && (!isset($updateData['role']) || $updateData['role'] !== 'user')) {
                    $updateData['role'] = 'user';
                }
            }
            
            $user->update($updateData);

            return response()->json(['user' => $user]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'User not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update user'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $currentUser = auth()->user();

        try {
            $user = User::findOrFail($id);
            
            // Prevent deleting yourself
            if ($user->id === $currentUser->id) {
                return response()->json(['error' => 'You cannot delete your own account'], 400);
            }

            // Não permite deletar super admin
            if ($user->isSuperAdmin()) {
                return response()->json(['error' => 'Não é possível deletar um super administrador'], 400);
            }

            // Super admin pode deletar qualquer usuário
            // Admins só podem deletar usuários criados por eles
            if (!$currentUser->isSuperAdmin()) {
                if ($user->created_by !== $currentUser->id) {
                    return response()->json(['error' => 'Acesso negado. Você só pode deletar usuários criados por você.'], 403);
                }
            }
            
            $user->delete();

            return response()->json(['message' => 'User deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'User not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete user'], 500);
        }
    }
}
