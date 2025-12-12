<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Contact;
use App\Services\PaymentStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PaymentStatusController extends Controller
{
    protected $paymentStatusService;

    public function __construct(PaymentStatusService $paymentStatusService)
    {
        $this->paymentStatusService = $paymentStatusService;
    }

    /**
     * Obtém status de pagamento de um contato específico
     *
     * @param Request $request
     * @param int $contactId
     * @return JsonResponse
     */
    public function getContactStatus(Request $request, int $contactId): JsonResponse
    {
        try {
            $contact = Contact::findOrFail($contactId);

            // Verifica se o usuário tem permissão para ver este contato
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $status = $this->paymentStatusService->getContactPaymentStatus($contact);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter status de pagamento do contato', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao obter status de pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém status de pagamentos de todos os contatos de um bot
     *
     * @param Request $request
     * @param int $botId
     * @return JsonResponse
     */
    public function getBotStatuses(Request $request, int $botId): JsonResponse
    {
        try {
            $bot = Bot::findOrFail($botId);

            // Verifica se o usuário tem permissão para ver este bot
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $statuses = $this->paymentStatusService->getBotPaymentStatuses($bot);

            // Filtros opcionais
            $statusFilter = $request->query('status'); // active, expired, expiring_soon, no_payment
            if ($statusFilter) {
                $statuses = array_filter($statuses, function ($status) use ($statusFilter) {
                    return $status['status'] === $statusFilter;
                });
            }

            // Ordenação
            $sortBy = $request->query('sort_by', 'contact.first_name');
            $sortOrder = $request->query('sort_order', 'asc');

            usort($statuses, function ($a, $b) use ($sortBy, $sortOrder) {
                $keys = explode('.', $sortBy);
                $valueA = $a;
                $valueB = $b;
                foreach ($keys as $key) {
                    $valueA = $valueA[$key] ?? null;
                    $valueB = $valueB[$key] ?? null;
                }

                if ($sortOrder === 'desc') {
                    return $valueB <=> $valueA;
                }
                return $valueA <=> $valueB;
            });

            return response()->json([
                'success' => true,
                'data' => array_values($statuses),
                'summary' => [
                    'total' => count($statuses),
                    'active' => count(array_filter($statuses, fn($s) => $s['status'] === 'active')),
                    'expired' => count(array_filter($statuses, fn($s) => $s['status'] === 'expired')),
                    'expiring_soon' => count(array_filter($statuses, fn($s) => $s['status'] === 'expiring_soon')),
                    'no_payment' => count(array_filter($statuses, fn($s) => $s['status'] === 'no_payment')),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter status de pagamentos do bot', [
                'bot_id' => $botId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao obter status de pagamentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica e processa pagamentos expirados
     *
     * @param Request $request
     * @param int|null $botId
     * @return JsonResponse
     */
    public function checkExpiredPayments(Request $request, ?int $botId = null): JsonResponse
    {
        try {
            $bot = $botId ? Bot::findOrFail($botId) : null;

            $result = $this->paymentStatusService->checkAndProcessExpiredPayments($bot);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao verificar pagamentos expirados', [
                'bot_id' => $botId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao verificar pagamentos expirados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica e notifica pagamentos próximos de expirar
     *
     * @param Request $request
     * @param int|null $botId
     * @return JsonResponse
     */
    public function checkExpiringPayments(Request $request, ?int $botId = null): JsonResponse
    {
        try {
            $bot = $botId ? Bot::findOrFail($botId) : null;
            $daysBeforeExpiration = (int) $request->query('days', 7);

            $result = $this->paymentStatusService->checkAndNotifyExpiringPayments($bot, $daysBeforeExpiration);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao verificar pagamentos próximos de expirar', [
                'bot_id' => $botId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao verificar pagamentos próximos de expirar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém detalhes completos da transação incluindo metadata do gateway
     *
     * @param Request $request
     * @param int $transactionId
     * @return JsonResponse
     */
    public function getTransactionDetails(Request $request, int $transactionId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $transaction = \App\Models\Transaction::with(['bot', 'contact', 'paymentPlan', 'paymentCycle'])
                ->findOrFail($transactionId);

            // Verifica se o usuário tem permissão para ver esta transação
            // (pode verificar se o usuário tem acesso ao bot da transação)

            $metadata = $transaction->metadata ?? [];
            $gateway = $transaction->gateway ?? 'unknown';

            // Formata os dados do gateway
            $gatewayData = [];
            
            if ($gateway === 'mercadopago') {
                $gatewayData = [
                    'gateway' => 'Mercado Pago',
                    'payment_id' => $transaction->gateway_transaction_id ?? $metadata['mercadopago_payment_id'] ?? null,
                    'status' => $metadata['mercadopago_status'] ?? null,
                    'status_detail' => $metadata['mercadopago_status_detail'] ?? null,
                    'last_webhook_update' => $metadata['last_webhook_update'] ?? null,
                    'webhook_action' => $metadata['webhook_action'] ?? null,
                    'last_status_check' => $metadata['last_status_check'] ?? null,
                    'pix_code' => $metadata['pix_code'] ?? null,
                    'pix_ticket_url' => $metadata['pix_ticket_url'] ?? null,
                    'expiration_date' => $metadata['expiration_date'] ?? null,
                ];
            } elseif ($gateway === 'stripe') {
                $gatewayData = [
                    'gateway' => 'Stripe',
                    'payment_intent_id' => $transaction->gateway_transaction_id ?? $metadata['stripe_payment_intent_id'] ?? null,
                    'charge_id' => $metadata['stripe_charge_id'] ?? null,
                    'status' => $metadata['stripe_status'] ?? null,
                    'last_webhook_update' => $metadata['last_webhook_update'] ?? null,
                    'card_last4' => $metadata['card_last4'] ?? null,
                    'card_brand' => $metadata['card_brand'] ?? null,
                    'processed_at' => $metadata['processed_at'] ?? null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction' => [
                        'id' => $transaction->id,
                        'amount' => $transaction->amount,
                        'currency' => $transaction->currency,
                        'status' => $transaction->status,
                        'payment_method' => $transaction->payment_method,
                        'gateway' => $gateway,
                        'gateway_transaction_id' => $transaction->gateway_transaction_id,
                        'created_at' => $transaction->created_at,
                        'updated_at' => $transaction->updated_at,
                    ],
                    'gateway_data' => $gatewayData,
                    'payment_plan' => $transaction->paymentPlan ? [
                        'id' => $transaction->paymentPlan->id,
                        'title' => $transaction->paymentPlan->title,
                        'price' => $transaction->paymentPlan->price,
                    ] : null,
                    'contact' => $transaction->contact ? [
                        'id' => $transaction->contact->id,
                        'first_name' => $transaction->contact->first_name,
                        'last_name' => $transaction->contact->last_name,
                        'username' => $transaction->contact->username,
                        'email' => $transaction->contact->email,
                    ] : null,
                    'raw_metadata' => $metadata, // Metadata completo para debug
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter detalhes da transação', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao obter detalhes da transação: ' . $e->getMessage()
            ], 500);
        }
    }
}

