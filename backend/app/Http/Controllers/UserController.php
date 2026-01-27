<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserGroup;
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
            // Admin comum vê apenas:
            // - Usuários comuns (user_type = 'user') criados por ele
            // - Administradores comuns (user_type = 'admin') criados por ele
            if (!$user->isSuperAdmin()) {
                $query->where('created_by', $user->id)
                      ->where(function($q) {
                          $q->where('user_type', 'user')
                            ->orWhere('user_type', 'admin');
                      });
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
            'phone' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'address_street' => 'nullable|string|max:255',
            'address_number' => 'nullable|string|max:20',
            'address_zipcode' => 'nullable|string|max:10',
            'state_id' => 'nullable|exists:states,id',
            'municipality_id' => 'nullable|exists:municipalities,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Super admin pode criar qualquer tipo de usuário
        // Admin comum pode criar usuários comuns e administradores comuns (mas não super admin)
        if ($request->user_type === 'super_admin' && !$currentUser->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso negado. Apenas super administradores podem criar super administradores.'], 403);
        }

        // Admin comum não pode criar super admin
        if ($request->user_type === 'super_admin') {
            return response()->json(['error' => 'Acesso negado. Não é possível criar super administradores via API.'], 403);
        }

        try {
            // Se user_type for admin, role também deve ser admin
            $role = $request->role ?? ($request->user_type === 'admin' ? 'admin' : 'user');
            
            // Admin comum pode criar admin comum, mas não super admin
            $userType = $request->user_type;
            if (!$currentUser->isSuperAdmin() && $userType === 'super_admin') {
                $userType = 'user';
                $role = 'user';
            }

            $userGroupId = 8;
            
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password, // Will be hashed by mutator
                'user_type' => $userType,
                'user_group_id' => $userGroupId,
                'role' => $role,
                'created_by' => $currentUser->id, // Vincula ao admin que criou
                'active' => true,
                'phone' => $request->phone,
                'description' => $request->description,
                'address_street' => $request->address_street,
                'address_number' => $request->address_number,
                'address_zipcode' => $request->address_zipcode,
                'state_id' => $request->state_id,
                'municipality_id' => $request->municipality_id,
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
            $user = User::with('userGroup', 'creator', 'state', 'municipality')->findOrFail($id);

            // Super admin pode ver qualquer usuário
            // Admin comum só pode ver:
            // - Usuários comuns (user_type = 'user') criados por ele
            // - Administradores comuns (user_type = 'admin') criados por ele
            if (!$currentUser->isSuperAdmin()) {
                if ($user->created_by !== $currentUser->id) {
                    return response()->json(['error' => 'Acesso negado'], 403);
                }
                // Verifica se o usuário é do tipo permitido (user ou admin, mas não super_admin)
                if ($user->user_type === 'super_admin') {
                    return response()->json(['error' => 'Acesso negado. Você não pode visualizar super administradores.'], 403);
                }
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
            // Admin comum só pode atualizar:
            // - Usuários comuns (user_type = 'user') criados por ele
            // - Administradores comuns (user_type = 'admin') criados por ele
            if (!$currentUser->isSuperAdmin()) {
                if ($user->created_by !== $currentUser->id) {
                    return response()->json(['error' => 'Acesso negado. Você só pode atualizar usuários criados por você.'], 403);
                }
                // Verifica se o usuário é do tipo permitido (user ou admin, mas não super_admin)
                if ($user->user_type === 'super_admin') {
                    return response()->json(['error' => 'Acesso negado. Você não pode atualizar super administradores.'], 403);
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
                'phone' => 'nullable|string|max:20',
                'description' => 'nullable|string',
                'address_street' => 'nullable|string|max:255',
                'address_number' => 'nullable|string|max:20',
                'address_zipcode' => 'nullable|string|max:10',
                'state_id' => 'nullable|exists:states,id',
                'municipality_id' => 'nullable|exists:municipalities,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Super admin pode alterar para qualquer tipo (exceto super_admin via API)
            // Admin comum pode alterar para 'user' ou 'admin' (mas não 'super_admin')
            if ($request->has('user_type')) {
                if ($request->user_type === 'super_admin') {
                    return response()->json(['error' => 'Acesso negado. Não é possível alterar para super administrador via API.'], 403);
                }
                // Admin comum pode alterar para admin, desde que o usuário tenha sido criado por ele
                if (!$currentUser->isSuperAdmin() && $request->user_type === 'admin' && $user->created_by !== $currentUser->id) {
                    return response()->json(['error' => 'Acesso negado. Você só pode alterar usuários criados por você.'], 403);
                }
            }

            $updateData = $request->only([
                'name', 
                'email', 
                'user_type', 
                'user_group_id', 
                'role', 
                'active',
                'phone',
                'description',
                'address_street',
                'address_number',
                'address_zipcode',
                'state_id',
                'municipality_id'
            ]);
            
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

            // Super admin pode deletar qualquer usuário (exceto super admin)
            // Admin comum só pode deletar:
            // - Usuários comuns (user_type = 'user') criados por ele
            // - Administradores comuns (user_type = 'admin') criados por ele
            if (!$currentUser->isSuperAdmin()) {
                if ($user->created_by !== null && $user->created_by !== $currentUser->id) {
                    return response()->json(['error' => 'Acesso negado. Você só pode deletar usuários criados por você.'], 403);
                }
                // Verifica se o usuário é do tipo permitido (user ou admin, mas não super_admin)
                if ($user->user_type === 'super_admin') {
                    return response()->json(['error' => 'Acesso negado. Você não pode deletar super administradores.'], 403);
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
