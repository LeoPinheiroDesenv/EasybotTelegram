<?php

namespace App\Http\Middleware;

use App\Services\LogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogHttpRequests
{
    /**
     * Campos sensíveis que devem ser mascarados nos logs
     */
    private array $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'api_key',
        'api_secret',
        'secret_key',
        'access_token',
        'refresh_token',
        'authorization',
        'card_number',
        'card_cvv',
        'cvv',
        'cvc',
    ];

    /**
     * Headers sensíveis que devem ser mascarados
     */
    private array $sensitiveHeaders = [
        'authorization',
        'cookie',
        'x-api-key',
        'x-auth-token',
    ];

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
            
            // Prepara dados do request
            $requestData = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'headers' => $this->sanitizeHeaders($request->headers->all()),
                'query' => $request->query->all(),
                'body' => $this->sanitizeRequestBody($request->all()),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];
            
            // Prepara dados do response
            $responseData = [
                'status_code' => $response->getStatusCode(),
                'headers' => $this->sanitizeHeaders($response->headers->all()),
                'content' => $this->sanitizeResponseContent($response),
            ];
            
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
                $botId,
                $requestData,
                $responseData
            );
            
            return $response;
        }
        
        return $next($request);
    }

    /**
     * Sanitiza headers removendo ou mascarando dados sensíveis
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $this->sensitiveHeaders)) {
                $sanitized[$key] = ['***MASKED***'];
            } else {
                $sanitized[$key] = is_array($value) ? $value : [$value];
            }
        }
        return $sanitized;
    }

    /**
     * Sanitiza o body da requisição removendo ou mascarando dados sensíveis
     */
    private function sanitizeRequestBody(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $this->sensitiveFields)) {
                $sanitized[$key] = '***MASKED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeRequestBody($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Sanitiza o conteúdo da resposta
     */
    private function sanitizeResponseContent(Response $response): ?string
    {
        try {
            $content = $response->getContent();
            
            // Limita o tamanho do conteúdo para evitar logs muito grandes
            $maxLength = 5000;
            if (strlen($content) > $maxLength) {
                $content = substr($content, 0, $maxLength) . '... [TRUNCATED]';
            }
            
            // Tenta decodificar JSON para sanitizar
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $sanitized = $this->sanitizeRequestBody($decoded);
                return json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            
            return $content;
        } catch (\Exception $e) {
            return '[Error reading response content]';
        }
    }
}

