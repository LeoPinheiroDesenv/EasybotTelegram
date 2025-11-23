<?php

namespace App\Http\Middleware;

use App\Services\LogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogHttpRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ignora requisições de health check e webhooks do Telegram
        $ignoredPaths = ['/up', '/api/health', '/api/telegram/webhook'];
        
        $shouldLog = true;
        foreach ($ignoredPaths as $path) {
            if (str_starts_with($request->path(), ltrim($path, '/'))) {
                $shouldLog = false;
                break;
            }
        }
        
        if ($shouldLog) {
            $startTime = microtime(true);
            
            $response = $next($request);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            // Determina o nível do log baseado no status code
            $level = 'info';
            if ($response->getStatusCode() >= 500) {
                $level = 'error';
            } elseif ($response->getStatusCode() >= 400) {
                $level = 'warning';
            }
            
            // Extrai bot_id da URL se for uma rota de bot
            $botId = null;
            if (preg_match('/\/bots\/(\d+)/', $request->path(), $matches)) {
                $botId = (int) $matches[1];
                // Valida se o bot existe antes de passar para o log
                if (!\App\Models\Bot::where('id', $botId)->exists()) {
                    $botId = null; // Remove bot_id inválido
                }
            }
            
            LogService::log(
                sprintf(
                    '%s %s - Status: %d - Duration: %sms',
                    $request->method(),
                    $request->path(),
                    $response->getStatusCode(),
                    $duration
                ),
                $level,
                [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status_code' => $response->getStatusCode(),
                    'duration_ms' => $duration,
                    'user_agent' => $request->userAgent(),
                ],
                $botId
            );
            
            return $response;
        }
        
        return $next($request);
    }
}

