<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupPermission;

class PermissionService
{
    /**
     * Verifica se o usuário tem permissão para acessar um recurso
     *
     * @param User $user
     * @param string $resourceType 'menu' ou 'bot'
     * @param int|null $resourceId ID do bot ou null para menu
     * @param string $permission 'read', 'write', 'delete'
     * @return bool
     */
    public function hasPermission(User $user, string $resourceType, ?int $resourceId, string $permission): bool
    {
        // Super admin tem acesso total
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Se não tem grupo, não tem permissão
        if (!$user->user_group_id) {
            return false;
        }

        // Busca permissão do grupo
        $query = UserGroupPermission::where('user_group_id', $user->user_group_id)
            ->where('resource_type', $resourceType)
            ->where('permission', $permission);

        if ($resourceId !== null) {
            // Converte para string para comparação (resource_id agora é string)
            $query->where('resource_id', (string) $resourceId);
        } else {
            $query->whereNull('resource_id');
        }

        return $query->exists();
    }

    /**
     * Verifica se o usuário tem acesso a um bot específico
     *
     * @param User $user
     * @param int $botId
     * @param string $permission 'read', 'write', 'delete'
     * @return bool
     */
    public function hasBotPermission(User $user, int $botId, string $permission = 'read'): bool
    {
        return $this->hasPermission($user, 'bot', $botId, $permission);
    }

    /**
     * Verifica se o usuário tem acesso a um menu específico
     *
     * @param User $user
     * @param string $menuName
     * @param string $permission 'read', 'write', 'delete'
     * @return bool
     */
    public function hasMenuPermission(User $user, string $menuName, string $permission = 'read'): bool
    {
        // Super admin tem acesso a todos os menus
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Se não tem grupo, não tem permissão
        if (!$user->user_group_id) {
            return false;
        }

        // Busca permissão do menu no grupo
        return UserGroupPermission::where('user_group_id', $user->user_group_id)
            ->where('resource_type', 'menu')
            ->where('resource_id', $menuName)
            ->where('permission', $permission)
            ->exists();
    }

    /**
     * Verifica se o usuário pode ver logs (apenas super admin)
     *
     * @param User $user
     * @return bool
     */
    public function canViewLogs(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Verifica se o usuário pode gerenciar administradores (apenas super admin)
     *
     * @param User $user
     * @return bool
     */
    public function canManageAdmins(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Filtra bots que o usuário tem acesso
     *
     * @param User $user
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function filterAccessibleBots(User $user, $query)
    {
        // Super admin vê todos os bots
        if ($user->isSuperAdmin()) {
            return $query;
        }

        // Se não tem grupo, não vê nenhum bot
        if (!$user->user_group_id) {
            return $query->whereRaw('1 = 0'); // Retorna query vazia
        }

        // Busca IDs dos bots que o grupo tem permissão
        $botIds = UserGroupPermission::where('user_group_id', $user->user_group_id)
            ->where('resource_type', 'bot')
            ->where('permission', 'read')
            ->pluck('resource_id')
            ->map(function ($id) {
                return (int) $id; // Converte de string para int para comparação
            })
            ->toArray();

        return $query->whereIn('id', $botIds);
    }

    /**
     * Obtém menus que o usuário tem acesso
     *
     * @param User $user
     * @return array
     */
    public function getAccessibleMenus(User $user): array
    {
        // Super admin tem acesso a todos os menus
        if ($user->isSuperAdmin()) {
            return ['*']; // Todos os menus
        }

        // Se não tem grupo, não tem acesso a menus
        if (!$user->user_group_id) {
            return [];
        }

        // Busca menus permitidos
        return UserGroupPermission::where('user_group_id', $user->user_group_id)
            ->where('resource_type', 'menu')
            ->where('permission', 'read')
            ->pluck('resource_id')
            ->toArray();
    }
}

