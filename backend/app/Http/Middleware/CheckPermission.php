<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $resourceType
     * @param  string  $permission
     */
    public function handle(Request $request, Closure $next, string $resourceType, string $permission = 'read'): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }

        // Extrai resource_id da rota ou request
        $resourceId = null;
        if ($resourceType === 'bot') {
            $resourceId = $request->route('botId') ?? $request->route('id') ?? $request->input('bot_id');
        } elseif ($resourceType === 'menu') {
            $resourceId = $request->route('menu') ?? $request->input('menu');
        }

        if (!$this->permissionService->hasPermission($user, $resourceType, $resourceId, $permission)) {
            return response()->json([
                'error' => 'Acesso negado. Você não tem permissão para realizar esta ação.'
            ], 403);
        }

        return $next($request);
    }
}

