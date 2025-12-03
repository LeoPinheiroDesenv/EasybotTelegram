<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Retorna dados da transação para o frontend
     */
    public function getTransaction(string $token): \Illuminate\Http\JsonResponse
    {
        try {
            // Busca transação pelo token
            $transaction = Transaction::where('status', 'pending')
                ->whereRaw('JSON_EXTRACT(metadata, "$.payment_token") = ?', [$token])
                ->with(['bot', 'contact', 'paymentPlan'])
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'error' => 'Link de pagamento inválido ou expirado.'
                ], 404);
            }

            // Verifica se o token expirou
            $metadata = $transaction->metadata ?? [];
            $expiresAt = $metadata['expires_at'] ?? null;
            
            if ($expiresAt && now()->greaterThan($expiresAt)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Este link de pagamento expirou. Por favor, gere um novo link.'
                ], 400);
            }

            // Verifica se já foi pago
            if ($transaction->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'error' => 'Este pagamento já foi processado.'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'payment_plan' => [
                        'id' => $transaction->paymentPlan->id ?? null,
                        'title' => $transaction->paymentPlan->title ?? 'Plano',
                    ],
                    'bot' => [
                        'id' => $transaction->bot->id ?? null,
                        'name' => $transaction->bot->name ?? 'Bot',
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao carregar dados do pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Processa pagamento com cartão de crédito
     */
    public function processCreditCard(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'card_number' => 'required|string|min:13|max:19',
            'card_name' => 'required|string|max:255',
            'card_expiry' => 'required|string|regex:/^\d{2}\/\d{2}$/',
            'card_cvv' => 'required|string|min:3|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 400);
        }

        try {
            // Busca transação pelo token
            $transaction = Transaction::where('status', 'pending')
                ->whereRaw('JSON_EXTRACT(metadata, "$.payment_token") = ?', [$request->token])
                ->with(['bot', 'contact', 'paymentPlan'])
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'error' => 'Link de pagamento inválido ou expirado.'
                ], 404);
            }

            // Verifica se expirou
            $metadata = $transaction->metadata ?? [];
            $expiresAt = $metadata['expires_at'] ?? null;
            
            if ($expiresAt && now()->greaterThan($expiresAt)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Este link de pagamento expirou.'
                ], 400);
            }

            // TODO: Integrar com gateway de pagamento (Mercado Pago, Stripe, etc)
            // Por enquanto, apenas simula o pagamento
            
            // Atualiza transação como processada
            $transaction->update([
                'status' => 'processing',
                'metadata' => array_merge($metadata, [
                    'card_last4' => substr(str_replace(' ', '', $request->card_number), -4),
                    'card_name' => $request->card_name,
                    'processed_at' => now()->toIso8601String()
                ])
            ]);

            // Aqui você integraria com o gateway real
            // Por exemplo: Mercado Pago, Stripe, etc.
            
            return response()->json([
                'success' => true,
                'message' => 'Pagamento processado com sucesso!',
                'transaction_id' => $transaction->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao processar pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook do Mercado Pago
     */
    public function mercadoPagoWebhook(Request $request)
    {
        // TODO: Implementar webhook do Mercado Pago
        return response()->json(['received' => true]);
    }

    /**
     * Webhook do Stripe
     */
    public function stripeWebhook(Request $request)
    {
        // TODO: Implementar webhook do Stripe
        return response()->json(['received' => true]);
    }
}
