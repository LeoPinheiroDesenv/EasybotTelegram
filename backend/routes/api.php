<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BotCommandController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentCycleController;
use App\Http\Controllers\PaymentGatewayConfigController;
use App\Http\Controllers\PaymentPlanController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserGroupController;
use App\Http\Controllers\BotAdministratorController;
use App\Http\Controllers\RedirectButtonController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\DownsellController;
use App\Http\Controllers\FtpController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\ArtisanController;
use App\Http\Controllers\BotFatherController;
use App\Http\Controllers\PaymentStatusController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PixDiagnosticController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/verify-2fa', [AuthController::class, 'verifyTwoFactor']);
Route::post('/auth/password/request-reset', [AuthController::class, 'requestPasswordReset']);
Route::post('/auth/password/reset', [AuthController::class, 'resetPassword']);

// Rotas públicas de pagamento (sem autenticação)
Route::get('/payment/transaction/{token}', [PaymentController::class, 'getTransaction']);
Route::get('/payment/stripe-config', [PaymentController::class, 'getStripeConfig']);
Route::post('/payment/card/create-intent', [PaymentController::class, 'createPaymentIntent']);
Route::post('/payment/card/confirm', [PaymentController::class, 'confirmPayment']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Auth routes
    Route::get('/auth/me', [AuthController::class, 'getCurrentUser']);
    Route::get('/auth/2fa/setup', [AuthController::class, 'setup2FA']);
    Route::post('/auth/2fa/verify', [AuthController::class, 'verifyAndEnable2FA']);
    Route::post('/auth/2fa/disable', [AuthController::class, 'disable2FA']);

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar']);
    Route::get('/profile/states', [ProfileController::class, 'getStates']);
    Route::get('/profile/municipalities', [ProfileController::class, 'getMunicipalitiesByState']);
    Route::get('/profile/consult-cep', [ProfileController::class, 'consultCep']);

    // User routes (admins podem criar usuários, mas apenas super admins podem criar outros admins)
    // O controller já implementa as verificações de permissão adequadas
    Route::apiResource('users', UserController::class);

    // User Group routes (admins podem criar e gerenciar grupos de usuários)
    // O controller já implementa as verificações de permissão adequadas
    Route::apiResource('user-groups', UserGroupController::class);
    Route::get('/user-groups/menus/available', [UserGroupController::class, 'getAvailableMenus']);
    Route::get('/user-groups/bots/available', [UserGroupController::class, 'getAvailableBots']);

    // Bot routes
    Route::apiResource('bots', BotController::class);
    Route::post('/bots/{id}/initialize', [BotController::class, 'initialize']);
    Route::post('/bots/{id}/stop', [BotController::class, 'stop']);
    Route::post('/bots/{id}/validate-and-activate', [BotController::class, 'validateAndActivate']);
    Route::get('/bots/{id}/status', [BotController::class, 'status']);
    Route::post('/bots/validate', [BotController::class, 'validate']);
    Route::post('/bots/validate-token-and-group', [BotController::class, 'validateTokenAndGroup']);
    Route::post('/bots/{id}/media/upload', [BotController::class, 'uploadMedia']);
    Route::delete('/bots/{id}/media', [BotController::class, 'deleteMedia']);
    Route::post('/bots/{id}/update-invite-link', [BotController::class, 'updateInviteLink']);
    
    // Bot commands routes
    Route::get('/bots/{botId}/commands', [BotCommandController::class, 'index']);
    Route::post('/bots/{botId}/commands', [BotCommandController::class, 'store']);
    Route::put('/bots/{botId}/commands/{commandId}', [BotCommandController::class, 'update']);
    Route::delete('/bots/{botId}/commands/{commandId}', [BotCommandController::class, 'destroy']);
    Route::post('/bots/{botId}/commands/register', [BotCommandController::class, 'registerCommands']);
    Route::get('/bots/{botId}/commands/telegram', [BotCommandController::class, 'getTelegramCommands']);
    Route::delete('/bots/{botId}/commands/telegram', [BotCommandController::class, 'deleteTelegramCommands']);
    Route::delete('/bots/{botId}/commands/telegram/command', [BotCommandController::class, 'deleteTelegramCommand']);
    
    // Redirect buttons routes
    Route::get('/bots/{botId}/redirect-buttons', [RedirectButtonController::class, 'index']);
    Route::post('/bots/{botId}/redirect-buttons', [RedirectButtonController::class, 'store']);
    Route::put('/bots/{botId}/redirect-buttons/{buttonId}', [RedirectButtonController::class, 'update']);
    Route::delete('/bots/{botId}/redirect-buttons/{buttonId}', [RedirectButtonController::class, 'destroy']);
    
    // Bot administrators routes
    Route::get('/bot-administrators', [BotAdministratorController::class, 'index']);
    Route::post('/bot-administrators', [BotAdministratorController::class, 'store']);
    Route::get('/bot-administrators/{id}', [BotAdministratorController::class, 'show']);
    Route::put('/bot-administrators/{id}', [BotAdministratorController::class, 'update']);
    Route::delete('/bot-administrators/{id}', [BotAdministratorController::class, 'destroy']);
    
    // Telegram groups routes
    Route::get('/telegram-groups', [\App\Http\Controllers\TelegramGroupController::class, 'index']);
    Route::post('/telegram-groups', [\App\Http\Controllers\TelegramGroupController::class, 'store']);
    Route::get('/telegram-groups/{id}', [\App\Http\Controllers\TelegramGroupController::class, 'show']);
    Route::put('/telegram-groups/{id}', [\App\Http\Controllers\TelegramGroupController::class, 'update']);
    Route::delete('/telegram-groups/{id}', [\App\Http\Controllers\TelegramGroupController::class, 'destroy']);
    
    // Telegram webhook routes
    Route::get('/telegram/webhook/{botId}/info', [TelegramWebhookController::class, 'getWebhookInfo']);
    Route::post('/telegram/webhook/{botId}/set', [TelegramWebhookController::class, 'setWebhook']);
    Route::post('/telegram/webhook/{botId}/delete', [TelegramWebhookController::class, 'deleteWebhook']);

    // Payment Plan routes
    Route::apiResource('payment-plans', PaymentPlanController::class);

    // Payment Cycle routes
    Route::get('/payment-cycles/active', [PaymentCycleController::class, 'active']);
    Route::apiResource('payment-cycles', PaymentCycleController::class);

    // Payment routes
    Route::post('/payments/pix', [PaymentController::class, 'processPix']);
    Route::post('/payments/credit-card', [PaymentController::class, 'processCreditCard']);

    // Payment Status routes
    Route::get('/payment-status/contact/{contactId}', [PaymentStatusController::class, 'getContactStatus']);
    Route::get('/payment-status/bot/{botId}', [PaymentStatusController::class, 'getBotStatuses']);
    Route::post('/payment-status/check-expired/{botId?}', [PaymentStatusController::class, 'checkExpiredPayments']);
    Route::post('/payment-status/check-expiring/{botId?}', [PaymentStatusController::class, 'checkExpiringPayments']);
    Route::get('/payment-status/transaction/{transactionId}', [PaymentStatusController::class, 'getTransactionDetails']);
    
    // Payment routes - reenviar link do grupo
    Route::post('/payments/{transactionId}/resend-group-link', [PaymentController::class, 'resendGroupLink']);
    Route::post('/payments/{transactionId}/renew-group-link', [PaymentController::class, 'renewGroupLink']);

    // Payment Gateway Config routes
    Route::get('/payment-gateway-configs/config', [PaymentGatewayConfigController::class, 'getConfig']);
    Route::get('/payment-gateway-configs/status', [PaymentGatewayConfigController::class, 'checkApiStatus']);
    Route::apiResource('payment-gateway-configs', PaymentGatewayConfigController::class);

    // PIX Diagnostic routes (super admin only)
    Route::middleware('super_admin')->group(function () {
        Route::post('/pix-diagnostic/validate-code', [PixDiagnosticController::class, 'validatePixCode']);
        Route::get('/pix-diagnostic/statistics', [PixDiagnosticController::class, 'getCrcStatistics']);
        Route::get('/pix-diagnostic/mercado-pago-report', [PixDiagnosticController::class, 'generateMercadoPagoReport']);
    });

    // Contact routes
    Route::post('/contacts/{id}/block', [ContactController::class, 'block']);
    Route::get('/contacts/stats', [ContactController::class, 'stats']);
    Route::get('/contacts/latest', [ContactController::class, 'latest']);
    Route::post('/contacts/sync-group-members', [ContactController::class, 'syncGroupMembers']);
    Route::post('/contacts/update-all-status', [ContactController::class, 'updateAllContactsStatus']);
    Route::apiResource('contacts', ContactController::class);

    // Group management routes
    Route::post('/bots/{botId}/group/add-member', [\App\Http\Controllers\GroupManagementController::class, 'addMember']);
    Route::post('/bots/{botId}/group/remove-member', [\App\Http\Controllers\GroupManagementController::class, 'removeMember']);
    Route::get('/bots/{botId}/group/member-status/{contactId}', [\App\Http\Controllers\GroupManagementController::class, 'checkMemberStatus']);
    Route::get('/bots/{botId}/group/info', [\App\Http\Controllers\GroupManagementController::class, 'listGroupInfo']);
    Route::get('/bots/{botId}/group/statistics', [\App\Http\Controllers\GroupManagementController::class, 'getStatistics']);
    Route::get('/bots/{botId}/group/contact-history/{contactId}', [\App\Http\Controllers\GroupManagementController::class, 'getContactHistory']);

    // Billing routes
    Route::get('/billing/monthly', [\App\Http\Controllers\BillingController::class, 'getMonthlyBilling']);
    Route::get('/billing', [\App\Http\Controllers\BillingController::class, 'getBilling']);
    Route::get('/billing/chart', [\App\Http\Controllers\BillingController::class, 'getChartData']);
    Route::get('/billing/total', [\App\Http\Controllers\BillingController::class, 'getTotalBilling']);
    Route::get('/billing/dashboard-stats', [\App\Http\Controllers\BillingController::class, 'getDashboardStatistics']);

    // Alert routes
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::post('/alerts', [AlertController::class, 'store']);
    Route::post('/alerts/process', [AlertController::class, 'process']);
    Route::get('/alerts/{id}', [AlertController::class, 'show']);
    Route::put('/alerts/{id}', [AlertController::class, 'update']);
    Route::delete('/alerts/{id}', [AlertController::class, 'destroy']);

    // Downsell routes
    Route::get('/downsells', [DownsellController::class, 'index']);
    Route::post('/downsells', [DownsellController::class, 'store']);
    Route::get('/downsells/{id}', [DownsellController::class, 'show']);
    Route::put('/downsells/{id}', [DownsellController::class, 'update']);
    Route::delete('/downsells/{id}', [DownsellController::class, 'destroy']);

    // FTP routes
    Route::get('/ftp/files', [FtpController::class, 'listFiles']);
    Route::post('/ftp/upload', [FtpController::class, 'uploadFile']);
    Route::get('/ftp/download', [FtpController::class, 'downloadFile']);
    Route::delete('/ftp/delete', [FtpController::class, 'deleteFile']);
    Route::post('/ftp/directory', [FtpController::class, 'createDirectory']);
    Route::post('/ftp/test-connection', [FtpController::class, 'testConnection']);

    // Storage routes (super admin only)
    Route::middleware('super_admin')->group(function () {
        Route::get('/storage/link/status', [StorageController::class, 'checkStorageLink']);
        Route::post('/storage/link/create', [StorageController::class, 'createStorageLink']);
        Route::post('/storage/test', [StorageController::class, 'testStorageAccess']);
    });

    // BotFather routes
    Route::get('/bots/{botId}/botfather/info', [BotFatherController::class, 'getBotInfo']);
    Route::post('/bots/{botId}/botfather/set-name', [BotFatherController::class, 'setMyName']);
    Route::post('/bots/{botId}/botfather/set-description', [BotFatherController::class, 'setMyDescription']);
    Route::post('/bots/{botId}/botfather/set-short-description', [BotFatherController::class, 'setMyShortDescription']);
    Route::post('/bots/{botId}/botfather/set-about', [BotFatherController::class, 'setMyAbout']);
    Route::post('/bots/{botId}/botfather/set-menu-button', [BotFatherController::class, 'setChatMenuButton']);
    Route::post('/bots/{botId}/botfather/set-default-admin-rights', [BotFatherController::class, 'setMyDefaultAdministratorRights']);
    Route::post('/bots/{botId}/botfather/delete-commands', [BotFatherController::class, 'deleteMyCommands']);

    // Artisan commands routes (super admin only)
    Route::middleware('super_admin')->group(function () {
        Route::get('/artisan/commands', [ArtisanController::class, 'availableCommands']);
        Route::post('/artisan/execute', [ArtisanController::class, 'execute']);
        Route::post('/artisan/clear-all-caches', [ArtisanController::class, 'clearAllCaches']);
    });

    // Log routes (super admin only)
    Route::middleware('super_admin')->group(function () {
        Route::delete('logs', [LogController::class, 'deleteAll']);
        Route::apiResource('logs', LogController::class);
    });

            // Cron Jobs routes (super admin only)
            Route::middleware('super_admin')->group(function () {
                Route::get('/cron-jobs', [\App\Http\Controllers\CronJobController::class, 'index']);
                Route::post('/cron-jobs', [\App\Http\Controllers\CronJobController::class, 'store']);
                Route::post('/cron-jobs/create-default', [\App\Http\Controllers\CronJobController::class, 'createDefault']);
                Route::get('/cron-jobs/{id}', [\App\Http\Controllers\CronJobController::class, 'show']);
                Route::put('/cron-jobs/{id}', [\App\Http\Controllers\CronJobController::class, 'update']);
                Route::delete('/cron-jobs/{id}', [\App\Http\Controllers\CronJobController::class, 'destroy']);
                Route::post('/cron-jobs/{id}/test', [\App\Http\Controllers\CronJobController::class, 'test']);
                Route::post('/cron-jobs/test-cpanel', [\App\Http\Controllers\CronJobController::class, 'testCpanelConnection']);
                Route::post('/cron-jobs/sync-cpanel', [\App\Http\Controllers\CronJobController::class, 'syncWithCpanel']);
            });

            // Laravel Logs routes (super admin only)
            Route::middleware('super_admin')->group(function () {
                Route::get('/laravel-logs', [\App\Http\Controllers\LaravelLogController::class, 'index']);
                Route::get('/laravel-logs/{filename}', [\App\Http\Controllers\LaravelLogController::class, 'show']);
                Route::delete('/laravel-logs/{filename}', [\App\Http\Controllers\LaravelLogController::class, 'destroy']);
                Route::post('/laravel-logs/{filename}/clear', [\App\Http\Controllers\LaravelLogController::class, 'clear']);
                Route::post('/laravel-logs/test-cpanel', [\App\Http\Controllers\LaravelLogController::class, 'testCpanelConnection']);
            });
            
            // Endpoint master para executar todos os cron jobs automaticamente (público, protegido por token)
            // Este endpoint deve ser chamado a cada minuto pelo cPanel
            Route::post('/cron-jobs/execute-all', [\App\Http\Controllers\CronJobController::class, 'executeAll']);
});

// Telegram webhook (public route - Telegram precisa acessar)
Route::post('/telegram/webhook/{botId}', [TelegramWebhookController::class, 'webhook']);

// Payment webhooks (public routes - gateways de pagamento precisam acessar)
Route::post('/payments/webhook/mercadopago', [PaymentController::class, 'mercadoPagoWebhook']);
Route::post('/payments/webhook/stripe', [PaymentController::class, 'stripeWebhook']);

// Process alerts (public route - pode ser chamado por serviços externos para processamento automático)
// Protegido por token secreto configurado no .env (opcional - se não configurado, permite acesso sem autenticação)
Route::post('/alerts/process-auto', function (Request $request) {
    $secretToken = env('ALERTS_PROCESS_SECRET_TOKEN');
    
    // Se houver token configurado, verifica o token fornecido
    if ($secretToken) {
        // Aceita token em vários formatos: header X-Alerts-Process-Token, Authorization Bearer, ou parâmetro token
        $authHeader = $request->header('Authorization');
        $bearerToken = null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $bearerToken = substr($authHeader, 7);
        }
        
        $providedToken = $request->header('X-Alerts-Process-Token') 
            ?? $bearerToken 
            ?? $request->input('token');
        
        if (!$providedToken || $providedToken !== $secretToken) {
            return response()->json([
                'error' => 'Token inválido ou não fornecido',
                'message' => 'Para processar alertas automaticamente, forneça o token no header X-Alerts-Process-Token, Authorization Bearer, ou no parâmetro token'
            ], 403);
        }
    }
    // Se não houver token configurado, permite acesso sem autenticação (útil para desenvolvimento/testes)
    
    // Processa alertas
    try {
        $controller = app(\App\Http\Controllers\AlertController::class);
        return $controller->process($request);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao processar alertas automaticamente', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => 'Erro ao processar alertas',
            'message' => $e->getMessage()
        ], 500);
    }
});

// Verificar expiração de PIX (public route - pode ser chamado por serviços externos de cron)
// Protegido por token secreto configurado no .env (opcional - se não configurado, permite acesso sem autenticação)
Route::post('/pix/check-expiration', function (Request $request) {
    $secretToken = env('PIX_CHECK_EXPIRATION_SECRET_TOKEN');
    
    // Se houver token configurado, verifica o token fornecido
    if ($secretToken) {
        // Aceita token em vários formatos: header X-Pix-Check-Token, Authorization Bearer, ou parâmetro token
        $authHeader = $request->header('Authorization');
        $bearerToken = null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $bearerToken = substr($authHeader, 7);
        }
        
        $providedToken = $request->header('X-Pix-Check-Token') 
            ?? $bearerToken 
            ?? $request->input('token');
        
        if (!$providedToken || $providedToken !== $secretToken) {
            return response()->json([
                'error' => 'Token inválido ou não fornecido',
                'message' => 'Para verificar expiração de PIX, forneça o token no header X-Pix-Check-Token, Authorization Bearer, ou no parâmetro token'
            ], 403);
        }
    }
    // Se não houver token configurado, permite acesso sem autenticação (útil para desenvolvimento/testes)
    
    // Executa o comando de verificação de expiração de PIX
    try {
        $botId = $request->input('bot_id');
        $dryRun = $request->boolean('dry_run', false);
        
        $command = 'pix:check-expiration';
        $parameters = [];
        
        if ($botId) {
            $parameters['--bot-id'] = $botId;
        }
        
        if ($dryRun) {
            $parameters['--dry-run'] = true;
        }
        
        \Illuminate\Support\Facades\Artisan::call($command, $parameters);
        $output = \Illuminate\Support\Facades\Artisan::output();
        
        \Illuminate\Support\Facades\Log::info('Verificação de expiração de PIX executada via API', [
            'bot_id' => $botId,
            'dry_run' => $dryRun
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Verificação de expiração de PIX executada com sucesso',
            'output' => $output ?: 'Comando executado sem saída',
            'bot_id' => $botId,
            'dry_run' => $dryRun
        ], 200);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao verificar expiração de PIX via API', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => 'Erro ao verificar expiração de PIX',
            'message' => $e->getMessage()
        ], 500);
    }
});

// Verificar pagamentos pendentes (public route - pode ser chamado por serviços externos de cron)
// Protegido por token secreto configurado no .env (opcional - se não configurado, permite acesso sem autenticação)
// Retorna quando deve ser chamado novamente: 15 segundos se houver pendentes, 60 segundos se não houver
// Suporta tanto GET quanto POST para facilitar uso em diferentes serviços de cron
Route::match(['GET', 'POST'], '/payments/check-pending', function (Request $request) {
    $secretToken = env('PAYMENTS_CHECK_PENDING_SECRET_TOKEN');
    
    // Se houver token configurado, verifica o token fornecido
    if ($secretToken) {
        // Aceita token em vários formatos: header X-Payments-Check-Token, Authorization Bearer, ou parâmetro token
        $authHeader = $request->header('Authorization');
        $bearerToken = null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $bearerToken = substr($authHeader, 7);
        }
        
        $providedToken = $request->header('X-Payments-Check-Token') 
            ?? $bearerToken 
            ?? $request->input('token');
        
        if (!$providedToken || $providedToken !== $secretToken) {
            return response()->json([
                'error' => 'Token inválido ou não fornecido',
                'message' => 'Para verificar pagamentos pendentes, forneça o token no header X-Payments-Check-Token, Authorization Bearer, ou no parâmetro token'
            ], 403);
        }
    }
    // Se não houver token configurado, permite acesso sem autenticação (útil para desenvolvimento/testes)
    
    try {
        $botId = $request->input('bot_id');
        $interval = (int) $request->input('interval', 30);
        
        $paymentService = app(\App\Services\PaymentService::class);
        
        // Busca transações PIX pendentes
        $query = \App\Models\Transaction::where('status', 'pending')
            ->where('payment_method', 'pix')
            ->where('gateway', 'mercadopago')
            ->with(['bot', 'contact', 'paymentPlan']);

        if ($botId) {
            $query->where('bot_id', $botId);
        }

        // Filtra apenas transações que não foram verificadas recentemente
        $query->where(function($q) use ($interval) {
            $q->whereRaw('JSON_EXTRACT(metadata, "$.last_status_check") IS NULL')
              ->orWhereRaw('TIMESTAMPDIFF(SECOND, JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.last_status_check")), NOW()) >= ?', [$interval]);
        });

        $transactions = $query->get();

        // Verifica se há pagamentos pendentes (mesmo que não sejam verificados agora devido ao intervalo)
        $hasPendingPayments = \App\Models\Transaction::where('status', 'pending')
            ->where('payment_method', 'pix')
            ->where('gateway', 'mercadopago')
            ->when($botId, function($q) use ($botId) {
                $q->where('bot_id', $botId);
            })
            ->exists();

        $checkedCount = 0;
        $approvedCount = 0;
        $errorCount = 0;

        if (!$transactions->isEmpty()) {
            foreach ($transactions as $transaction) {
                try {
                    $paymentId = $transaction->gateway_transaction_id 
                        ?? $transaction->metadata['mercadopago_payment_id'] 
                        ?? null;

                    if (!$paymentId) {
                        continue;
                    }

                    // Busca configuração do gateway
                    $gatewayConfig = \App\Models\PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                        ->where('gateway', 'mercadopago')
                        ->where('active', true)
                        ->first();

                    if (!$gatewayConfig || !$gatewayConfig->api_key) {
                        continue;
                    }

                    // Configura o SDK do Mercado Pago
                    \MercadoPago\MercadoPagoConfig::setAccessToken($gatewayConfig->api_key);
                    $client = new \MercadoPago\Client\Payment\PaymentClient();
                    
                    // Busca o status atual do pagamento
                    $payment = $client->get($paymentId);
                    
                    if ($payment) {
                        $status = $payment->status ?? 'pending';
                        
                        // Atualiza metadata com última verificação
                        $metadata = $transaction->metadata ?? [];
                        $metadata['last_status_check'] = now()->toIso8601String();
                        // Remove flag de "não encontrado" se foi encontrado agora
                        unset($metadata['payment_not_found']);
                        $transaction->update(['metadata' => $metadata]);
                        
                        $checkedCount++;
                        
                        // Se está aprovado, processa
                        if ($status === 'approved') {
                            $paymentService->processPaymentApproval($transaction, $payment, $gatewayConfig);
                            $approvedCount++;
                            
                            \Illuminate\Support\Facades\Log::info('Pagamento aprovado via verificação automática', [
                                'transaction_id' => $transaction->id,
                                'payment_id' => $paymentId
                            ]);
                        }
                    }
                } catch (\MercadoPago\Exceptions\MPApiException $e) {
                    $errorCount++;
                    $errorMessage = $e->getMessage();
                    $apiResponse = $e->getApiResponse();
                    $statusCode = $apiResponse ? $apiResponse->getStatusCode() : null;
                    $responseContent = $apiResponse ? $apiResponse->getContent() : null;
                    
                    // Verifica se é o erro "Chave não localizada" (payment não encontrado)
                    $isKeyNotFound = stripos($errorMessage, 'chave não localizada') !== false 
                        || stripos($errorMessage, 'key not found') !== false
                        || stripos($errorMessage, 'not found') !== false
                        || ($statusCode === 404)
                        || (isset($responseContent['message']) && (
                            stripos($responseContent['message'], 'chave não localizada') !== false ||
                            stripos($responseContent['message'], 'not found') !== false
                        ));
                    
                    if ($isKeyNotFound) {
                        // Pagamento não encontrado - marca transação como inválida
                        \Illuminate\Support\Facades\Log::warning('⚠️ Pagamento não encontrado no Mercado Pago (Chave não localizada)', [
                            'transaction_id' => $transaction->id,
                            'payment_id' => $paymentId,
                            'status_code' => $statusCode,
                            'api_response' => $responseContent,
                            'note' => 'O payment_id pode estar incorreto ou o pagamento foi deletado no Mercado Pago. Considerando transação como inválida.'
                        ]);
                        
                        // Atualiza metadata para indicar que o pagamento não foi encontrado
                        $metadata = $transaction->metadata ?? [];
                        $metadata['payment_not_found'] = true;
                        $metadata['payment_not_found_at'] = now()->toIso8601String();
                        $metadata['payment_not_found_error'] = $errorMessage;
                        $metadata['last_status_check'] = now()->toIso8601String();
                        $transaction->update(['metadata' => $metadata]);
                        
                        // Se o pagamento não foi encontrado múltiplas vezes, marca como falhado
                        $notFoundCount = $metadata['payment_not_found_count'] ?? 0;
                        $notFoundCount++;
                        $metadata['payment_not_found_count'] = $notFoundCount;
                        
                        // Se não foi encontrado 3 vezes ou mais, marca como falhado
                        if ($notFoundCount >= 3) {
                            $transaction->update([
                                'status' => 'failed',
                                'metadata' => $metadata
                            ]);
                            
                            \Illuminate\Support\Facades\Log::warning('🔄 Transação marcada como falhada após múltiplas tentativas de encontrar pagamento', [
                                'transaction_id' => $transaction->id,
                                'payment_id' => $paymentId,
                                'not_found_count' => $notFoundCount
                            ]);
                        } else {
                            $transaction->update(['metadata' => $metadata]);
                        }
                    } else {
                        \Illuminate\Support\Facades\Log::error('Erro ao verificar pagamento pendente', [
                            'transaction_id' => $transaction->id,
                            'payment_id' => $paymentId,
                            'error' => $errorMessage,
                            'status_code' => $statusCode,
                            'api_response' => $responseContent
                        ]);
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    \Illuminate\Support\Facades\Log::error('Erro ao verificar pagamento pendente', [
                        'transaction_id' => $transaction->id,
                        'payment_id' => $paymentId ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }

        // Verifica se ainda há pagamentos pendentes após processar
        $stillHasPending = \App\Models\Transaction::where('status', 'pending')
            ->where('payment_method', 'pix')
            ->where('gateway', 'mercadopago')
            ->when($botId, function($q) use ($botId) {
                $q->where('bot_id', $botId);
            })
            ->exists();

        // Define quando deve ser chamado novamente
        // 15 segundos se houver pendentes, 60 segundos se não houver
        $nextCheckInSeconds = ($stillHasPending || $hasPendingPayments) ? 15 : 60;
        
        \Illuminate\Support\Facades\Log::info('Verificação de pagamentos pendentes executada via API', [
            'bot_id' => $botId,
            'checked_count' => $checkedCount,
            'approved_count' => $approvedCount,
            'error_count' => $errorCount,
            'has_pending' => $stillHasPending || $hasPendingPayments,
            'next_check_in_seconds' => $nextCheckInSeconds
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Verificação de pagamentos pendentes executada com sucesso',
            'checked_count' => $checkedCount,
            'approved_count' => $approvedCount,
            'error_count' => $errorCount,
            'has_pending_payments' => $stillHasPending || $hasPendingPayments,
            'next_check_in_seconds' => $nextCheckInSeconds,
            'next_check_at' => now()->addSeconds($nextCheckInSeconds)->toIso8601String(),
            'bot_id' => $botId
        ], 200);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao verificar pagamentos pendentes via API', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => 'Erro ao verificar pagamentos pendentes',
            'message' => $e->getMessage(),
            'next_check_in_seconds' => 60 // Em caso de erro, tenta novamente em 1 minuto
        ], 500);
    }
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Server is running'
    ]);
});

