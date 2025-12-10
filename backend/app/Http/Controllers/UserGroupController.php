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
        $currentUser = auth()->user();

        // Apenas admins podem ver grupos
        if (!$currentUser->isAdmin()) {
            return response()->json(['error' => 'Acesso negado. Apenas administradores podem acessar esta funcionalidade.'], 403);
        }

        // Super admin vê todos os grupos, admins comuns também veem todos (para poderem atribuir usuários)
        $groups = UserGroup::with(['users', 'permissions', 'creator'])->get();

        return response()->json([
            'groups' => $groups
        ]);
    }

    /**
     * Cria um novo grupo de usuários
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = auth()->user();

        // Apenas admins podem criar grupos
        if (!$currentUser->isAdmin()) {
            return response()->json(['error' => 'Acesso negado. Apenas administradores podem criar grupos de usuários.'], 403);
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
                'created_by' => $currentUser->id, // Vincula ao admin que criou
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
                // Se não for super admin, valida se os bots pertencem ao usuário
                if (!$currentUser->isSuperAdmin()) {
                    $userBotIds = Bot::where('user_id', $currentUser->id)->pluck('id')->toArray();
                    foreach ($request->bot_permissions as $botPermission) {
                        if (!in_array($botPermission['bot_id'], $userBotIds)) {
                            DB::rollBack();
                            return response()->json([
                                'error' => 'Você só pode atribuir permissões aos seus próprios bots.'
                            ], 403);
                        }
                    }
                }
                
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
        $currentUser = auth()->user();

        // Apenas admins podem ver grupos
        if (!$currentUser->isAdmin()) {
            return response()->json(['error' => 'Acesso negado. Apenas administradores podem acessar esta funcionalidade.'], 403);
        }

        $group = UserGroup::with(['users', 'permissions', 'creator'])->find($id);

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
        $currentUser = auth()->user();

        // Apenas admins podem atualizar grupos
        if (!$currentUser->isAdmin()) {
            return response()->json(['error' => 'Acesso negado. Apenas administradores podem atualizar grupos de usuários.'], 403);
        }

        $group = UserGroup::find($id);

        if (!$group) {
            return response()->json(['error' => 'Grupo não encontrado'], 404);
        }

        // Super admin pode atualizar qualquer grupo, admins comuns só podem atualizar grupos que criaram
        if (!$currentUser->isSuperAdmin() && $group->created_by !== $currentUser->id) {
            return response()->json(['error' => 'Acesso negado. Você só pode atualizar grupos criados por você.'], 403);
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
                    // Se não for super admin, valida se os bots pertencem ao usuário
                    if (!$currentUser->isSuperAdmin()) {
                        $userBotIds = Bot::where('user_id', $currentUser->id)->pluck('id')->toArray();
                        foreach ($request->bot_permissions as $botPermission) {
                            if (!in_array($botPermission['bot_id'], $userBotIds)) {
                                DB::rollBack();
                                return response()->json([
                                    'error' => 'Você só pode atribuir permissões aos seus próprios bots.'
                                ], 403);
                            }
                        }
                    }
                    
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
        $currentUser = auth()->user();

        // Apenas admins podem remover grupos
        if (!$currentUser->isAdmin()) {
            return response()->json(['error' => 'Acesso negado. Apenas administradores podem remover grupos de usuários.'], 403);
        }

        $group = UserGroup::find($id);

        if (!$group) {
            return response()->json(['error' => 'Grupo não encontrado'], 404);
        }

        // Super admin pode remover qualquer grupo, admins comuns só podem remover grupos que criaram
        if (!$currentUser->isSuperAdmin() && $group->created_by !== $currentUser->id) {
            return response()->json(['error' => 'Acesso negado. Você só pode remover grupos criados por você.'], 403);
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
        $currentUser = auth()->user();

        // Super admin vê todos os bots, admins comuns veem apenas seus próprios bots
        $query = Bot::select('id', 'name', 'user_id');
        if (!$currentUser->isSuperAdmin()) {
            $query->where('user_id', $currentUser->id);
        }

        $bots = $query->get();

        return response()->json(['bots' => $bots]);
    }
}

