<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Verifica expiração de PIX a cada 5 minutos
        $schedule->command('pix:check-expiration')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Verifica expiração de links de grupo a cada hora
        $schedule->command('check:group-link-expiration')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Verifica expiração de planos e envia lembretes diariamente
        $schedule->command('expiration:check')
            ->dailyAt('09:00') // Envia às 9h da manhã
            ->withoutOverlapping()
            ->runInBackground();

        // NOTA: O scheduler do Laravel requer acesso ao terminal do servidor para funcionar
        // Como não temos acesso ao terminal em produção, o polling de pagamentos pendentes
        // deve ser feito via chamadas HTTP periódicas ao endpoint /api/payments/check-pending
        // Use um serviço externo de cron (como EasyCron, cron-job.org, etc.) para chamar o endpoint
        // O endpoint retorna 'next_check_in_seconds' indicando quando deve ser chamado novamente
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'super_admin' => \App\Http\Middleware\SuperAdminOnly::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);

        // Add CORS middleware globally - must be prepended to handle OPTIONS requests
        $middleware->prepend(\App\Http\Middleware\HandleCors::class);

        // Add HTTP request logging middleware (opcional - pode ser desabilitado via env)
        if (env('LOG_HTTP_REQUESTS', true)) {
            $middleware->append(\App\Http\Middleware\LogHttpRequests::class);
        }

        // Configura autenticação para retornar JSON em vez de redirecionar para login em rotas API
        $middleware->redirectGuestsTo(function () {
            // Para rotas API, não redireciona (retorna JSON 401)
            if (request()->is('api/*') || request()->expectsJson()) {
                return null;
            }
            // Para rotas web, também não redireciona (não temos rota login)
            return null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Captura exceções não tratadas e salva no banco de dados
        $exceptions->report(function (\Throwable $e) {
            \App\Services\LogService::error(
                'Exceção não tratada: ' . $e->getMessage(),
                [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
        });

        // Trata exceções de autenticação para rotas API
        // Exceção: webhooks não devem exigir autenticação
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            // Permite webhooks sem autenticação
            if ($request->is('api/payments/webhook/*') ||
                $request->is('api/telegram/webhook/*') ||
                $request->is('api/alerts/process-auto')) {
                // Webhooks são públicos, não devem retornar 401
                return null; // Deixa o Laravel processar normalmente
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'error' => 'Token de autenticação inválido ou ausente.'
                ], 401);
            }
        });

        // Trata exceções de rota não encontrada
        $exceptions->render(function (\Symfony\Component\Routing\Exception\RouteNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Route not found.',
                    'error' => 'A rota solicitada não foi encontrada.'
                ], 404);
            }
        });
    })->create();
