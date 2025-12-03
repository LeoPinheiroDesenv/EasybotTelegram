<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

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
