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
    Route::get('/bots/{id}/status', [BotController::class, 'status']);
    Route::post('/bots/validate', [BotController::class, 'validate']);
    Route::post('/bots/validate-token-and-group', [BotController::class, 'validateTokenAndGroup']);
    
    // Bot commands routes
    Route::get('/bots/{botId}/commands', [BotCommandController::class, 'index']);
    Route::post('/bots/{botId}/commands', [BotCommandController::class, 'store']);
    Route::put('/bots/{botId}/commands/{commandId}', [BotCommandController::class, 'update']);
    Route::delete('/bots/{botId}/commands/{commandId}', [BotCommandController::class, 'destroy']);
    
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

    // Log routes (super admin only)
    Route::middleware('super_admin')->group(function () {
        Route::apiResource('logs', LogController::class);
    });
});

// Telegram webhook (public route - Telegram precisa acessar)
Route::post('/telegram/webhook/{botId}', [TelegramWebhookController::class, 'webhook']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Server is running'
    ]);
});

