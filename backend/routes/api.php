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

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Auth routes
    Route::get('/auth/me', [AuthController::class, 'getCurrentUser']);
    Route::get('/auth/2fa/setup', [AuthController::class, 'setup2FA']);
    Route::post('/auth/2fa/verify', [AuthController::class, 'verifyAndEnable2FA']);
    Route::post('/auth/2fa/disable', [AuthController::class, 'disable2FA']);

    // User routes (super admin only)
    Route::middleware('super_admin')->group(function () {
        Route::apiResource('users', UserController::class);
    });

    // User Group routes (super admin only)
    Route::middleware('super_admin')->group(function () {
        Route::apiResource('user-groups', UserGroupController::class);
        Route::get('/user-groups/menus/available', [UserGroupController::class, 'getAvailableMenus']);
        Route::get('/user-groups/bots/available', [UserGroupController::class, 'getAvailableBots']);
    });

    // Bot routes
    Route::apiResource('bots', BotController::class);
    Route::post('/bots/{id}/initialize', [BotController::class, 'initialize']);
    Route::post('/bots/{id}/stop', [BotController::class, 'stop']);
    Route::post('/bots/{id}/validate-and-activate', [BotController::class, 'validateAndActivate']);
    Route::get('/bots/{id}/status', [BotController::class, 'status']);
    Route::post('/bots/validate', [BotController::class, 'validate']);
    Route::post('/bots/validate-token-and-group', [BotController::class, 'validateTokenAndGroup']);
    
    // Bot commands routes
    Route::get('/bots/{botId}/commands', [BotCommandController::class, 'index']);
    Route::post('/bots/{botId}/commands', [BotCommandController::class, 'store']);
    Route::put('/bots/{botId}/commands/{commandId}', [BotCommandController::class, 'update']);
    Route::delete('/bots/{botId}/commands/{commandId}', [BotCommandController::class, 'destroy']);
    Route::post('/bots/{botId}/commands/register', [BotCommandController::class, 'registerCommands']);
    Route::get('/bots/{botId}/commands/telegram', [BotCommandController::class, 'getTelegramCommands']);
    
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
    Route::post('/payments/webhook/mercadopago', [PaymentController::class, 'mercadoPagoWebhook']);
    Route::post('/payments/webhook/stripe', [PaymentController::class, 'stripeWebhook']);

    // Payment Gateway Config routes
    Route::get('/payment-gateway-configs/config', [PaymentGatewayConfigController::class, 'getConfig']);
    Route::apiResource('payment-gateway-configs', PaymentGatewayConfigController::class);

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

    // Alert routes
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::post('/alerts', [AlertController::class, 'store']);
    Route::post('/alerts/process', [AlertController::class, 'process']);
    Route::get('/alerts/{id}', [AlertController::class, 'show']);
    Route::put('/alerts/{id}', [AlertController::class, 'update']);
    Route::delete('/alerts/{id}', [AlertController::class, 'destroy']);

    // Log routes (super admin only)
    Route::middleware('super_admin')->group(function () {
        Route::apiResource('logs', LogController::class);
    });
});

// Telegram webhook (public route - Telegram precisa acessar)
Route::post('/telegram/webhook/{botId}', [TelegramWebhookController::class, 'webhook']);

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

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Server is running'
    ]);
});

