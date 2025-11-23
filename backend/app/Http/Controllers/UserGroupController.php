<?php

namespace App\Http\Controllers;

use App\Models\UserGroup;
use App\Models\UserGroupPermission;
use App\Models\Bot;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserGroupController extends Controller
{
    /**
     * Lista todos os grupos de usuários
     */
    public function index(): JsonResponse
    {
        // Apenas super admin pode ver todos os grupos
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $groups = UserGroup::with(['users', 'permissions'])->get();

        return response()->json([
            'groups' => $groups
        ]);
    }

    /**
     * Cria um novo grupo de usuários
     */
    public function store(Request $request): JsonResponse
    {
        // Apenas super admin pode criar grupos
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:user_groups,name',
            'description' => 'nullable|string',
            'active' => 'nullable|boolean',
            'menu_permissions' => 'nullable|array',
            'menu_permissions.*' => 'string',
            'bot_permissions' => 'nullable|array',
            'bot_permissions.*.bot_id' => 'required|exists:bots,id',
            'bot_permissions.*.permissions' => 'required|array',
            'bot_permissions.*.permissions.*' => 'in:read,write,delete',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            $group = UserGroup::create([
                'name' => $request->name,
                'description' => $request->description,
                'active' => $request->input('active', true),
            ]);

            // Adiciona permissões de menus
            if ($request->has('menu_permissions')) {
                foreach ($request->menu_permissions as $menu) {
                    UserGroupPermission::create([
                        'user_group_id' => $group->id,
                        'resource_type' => 'menu',
                        'resource_id' => $menu,
                        'permission' => 'read',
                    ]);
                }
            }

            // Adiciona permissões de bots
            if ($request->has('bot_permissions')) {
                foreach ($request->bot_permissions as $botPermission) {
                    foreach ($botPermission['permissions'] as $permission) {
                        UserGroupPermission::create([
                            'user_group_id' => $group->id,
                            'resource_type' => 'bot',
                            'resource_id' => (string) $botPermission['bot_id'], // Converte para string
                            'permission' => $permission,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'group' => $group->load(['permissions'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao criar grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um grupo específico
     */
    public function show($id): JsonResponse
    {
        // Apenas super admin pode ver grupos
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $group = UserGroup::with(['users', 'permissions'])->find($id);

        if (!$group) {
            return response()->json(['error' => 'Grupo não encontrado'], 404);
        }

        return response()->json([
            'group' => $group
        ]);
    }

    /**
     * Atualiza um grupo
     */
    public function update(Request $request, $id): JsonResponse
    {
        // Apenas super admin pode atualizar grupos
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $group = UserGroup::find($id);

        if (!$group) {
            return response()->json(['error' => 'Grupo não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:user_groups,name,' . $id,
            'description' => 'nullable|string',
            'active' => 'nullable|boolean',
            'menu_permissions' => 'nullable|array',
            'menu_permissions.*' => 'string',
            'bot_permissions' => 'nullable|array',
            'bot_permissions.*.bot_id' => 'required|exists:bots,id',
            'bot_permissions.*.permissions' => 'required|array',
            'bot_permissions.*.permissions.*' => 'in:read,write,delete',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            $group->update([
                'name' => $request->input('name', $group->name),
                'description' => $request->input('description', $group->description),
                'active' => $request->input('active', $group->active),
            ]);

            // Atualiza permissões se fornecidas
            if ($request->has('menu_permissions') || $request->has('bot_permissions')) {
                // Remove permissões antigas
                UserGroupPermission::where('user_group_id', $group->id)->delete();

                // Adiciona novas permissões de menus
                if ($request->has('menu_permissions')) {
                    foreach ($request->menu_permissions as $menu) {
                        UserGroupPermission::create([
                            'user_group_id' => $group->id,
                            'resource_type' => 'menu',
                            'resource_id' => $menu,
                            'permission' => 'read',
                        ]);
                    }
                }

                // Adiciona novas permissões de bots
                if ($request->has('bot_permissions')) {
                    foreach ($request->bot_permissions as $botPermission) {
                        foreach ($botPermission['permissions'] as $permission) {
                            UserGroupPermission::create([
                                'user_group_id' => $group->id,
                                'resource_type' => 'bot',
                                'resource_id' => (string) $botPermission['bot_id'], // Converte para string
                                'permission' => $permission,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'group' => $group->load(['permissions'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao atualizar grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um grupo
     */
    public function destroy($id): JsonResponse
    {
        // Apenas super admin pode remover grupos
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $group = UserGroup::find($id);

        if (!$group) {
            return response()->json(['error' => 'Grupo não encontrado'], 404);
        }

        // Verifica se há usuários no grupo
        if ($group->users()->count() > 0) {
            return response()->json([
                'error' => 'Não é possível remover o grupo pois existem usuários associados a ele.'
            ], 400);
        }

        $group->delete();

        return response()->json([
            'message' => 'Grupo removido com sucesso'
        ]);
    }

    /**
     * Obtém menus disponíveis para permissões
     */
    public function getAvailableMenus(): JsonResponse
    {
        $menus = [
            'dashboard',
            'billing',
            'bot',
            'results',
            'marketing',
            'settings',
        ];

        return response()->json(['menus' => $menus]);
    }

    /**
     * Obtém bots disponíveis para permissões
     */
    public function getAvailableBots(): JsonResponse
    {
        $bots = Bot::select('id', 'name', 'user_id')->get();

        return response()->json(['bots' => $bots]);
    }
}

