<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GroupManagementPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        // Admin tem acesso total
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Verifica se o usuário tem permissão específica para gerenciar grupos
        // Por padrão, apenas admins podem gerenciar grupos
        // Isso pode ser expandido para verificar permissões específicas por bot
        
        $botId = $request->route('botId');
        
        if ($botId) {
            $bot = \App\Models\Bot::find($botId);
            
            if ($bot && $bot->user_id === $user->id) {
                // Usuário é dono do bot, permite acesso
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'Você não tem permissão para gerenciar grupos',
            'message' => 'Apenas administradores e donos dos bots podem gerenciar grupos'
        ], 403);
    }
}

