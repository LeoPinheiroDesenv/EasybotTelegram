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
        // Apenas super admin pode ver todos os usuários
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        try {
            $users = User::with('userGroup')->get();
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
        // Apenas super admin pode criar usuários
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso negado'], 403);
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

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password, // Will be hashed by mutator
                'user_type' => $request->user_type,
                'user_group_id' => $request->user_group_id,
                'role' => $request->role ?? 'user',
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
            $user = User::findOrFail($id);
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
        // Apenas super admin pode atualizar usuários
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso negado'], 403);
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

        try {
            $user = User::findOrFail($id);
            
            $updateData = $request->only(['name', 'email', 'user_type', 'user_group_id', 'role', 'active']);
            
            // Only update password if provided
            if ($request->has('password') && !empty($request->password)) {
                $updateData['password'] = $request->password; // Will be hashed by mutator
            }

            // Não permite alterar user_type para super_admin via API (deve ser feito manualmente no banco)
            if (isset($updateData['user_type']) && $updateData['user_type'] === 'super_admin') {
                unset($updateData['user_type']);
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
        // Apenas super admin pode deletar usuários
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        try {
            $user = User::findOrFail($id);
            
            // Prevent deleting yourself
            if ($user->id === auth()->id()) {
                return response()->json(['error' => 'You cannot delete your own account'], 400);
            }

            // Não permite deletar super admin
            if ($user->isSuperAdmin()) {
                return response()->json(['error' => 'Não é possível deletar um super administrador'], 400);
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
