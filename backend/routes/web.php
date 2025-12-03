<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\Log;

Route::get('/', function () {
    return view('welcome');
});

// Rotas públicas de pagamento (redireciona para frontend)
Route::get('/payment/card/{token}', function ($token) {
    // Redireciona para o frontend React
    // Se o frontend estiver no mesmo domínio, usa caminho relativo
    // Se estiver em domínio diferente, precisa configurar FRONTEND_URL no .env
    $frontendUrl = env('FRONTEND_URL');
    
    if ($frontendUrl) {
        // Frontend em domínio separado
        return redirect($frontendUrl . '/payment/card/' . $token);
    } else {
        // Frontend no mesmo domínio (SPA)
        // Retorna HTML que redireciona via JavaScript para o React Router
        return response()->view('redirect', ['path' => '/payment/card/' . $token], 200);
    }
})->name('payment.card');

// Rota temporária para testar envio de email
// Remover após testes
Route::get('/test-email', function () {
    try {
        // Log da configuração atual
        Log::info('Email Configuration Test', [
            'MAIL_MAILER' => env('MAIL_MAILER'),
            'MAIL_HOST' => env('MAIL_HOST'),
            'MAIL_PORT' => env('MAIL_PORT'),
            'MAIL_USERNAME' => env('MAIL_USERNAME'),
            'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION'),
            'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
            'MAIL_FROM_NAME' => env('MAIL_FROM_NAME'),
            'config_mail_default' => config('mail.default'),
            'config_mail_from' => config('mail.from'),
        ]);

        // Teste de envio
        $testEmail = request('email', 'teste@example.com');
        $resetUrl = env('FRONTEND_URL', env('APP_URL')) . '/reset-password?token=test&email=' . urlencode($testEmail);
        
        Mail::to($testEmail)->send(new PasswordResetMail($resetUrl));
        
        return response()->json([
            'success' => true,
            'message' => 'Email enviado com sucesso!',
            'config' => [
                'MAIL_MAILER' => env('MAIL_MAILER'),
                'MAIL_HOST' => env('MAIL_HOST'),
                'MAIL_PORT' => env('MAIL_PORT'),
                'MAIL_USERNAME' => env('MAIL_USERNAME'),
                'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION'),
            ]
        ]);
    } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
        Log::error('Mail Transport Error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Erro de transporte SMTP: ' . $e->getMessage(),
            'details' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]
        ], 500);
    } catch (\Exception $e) {
        Log::error('Email Test Error', [
            'error' => $e->getMessage(),
            'class' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Erro ao enviar email: ' . $e->getMessage(),
            'details' => [
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]
        ], 500);
    }
});
