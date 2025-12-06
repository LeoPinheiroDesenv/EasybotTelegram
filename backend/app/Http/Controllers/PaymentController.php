<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;

class PaymentController extends Controller
{
    /**
     * Retorna a chave pública do Stripe para o frontend
     * Pode receber um token opcional para buscar a configuração baseada na transação
     */
    public function getStripeConfig(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $gatewayConfig = null;
            
            // Se tiver token, busca a configuração baseada na transação
            if ($request->has('token')) {
                $transaction = Transaction::where('status', 'pending')
                    ->whereRaw('JSON_EXTRACT(metadata, "$.payment_token") = ?', [$request->token])
                    ->first();
                
                if ($transaction) {
                    // Busca configuração do bot da transação
                    $gatewayConfig = \App\Models\PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                        ->where('gateway', 'stripe')
                        ->where('active', true)
                        ->where('environment', 'production')
                        ->first();
                    
                    if (!$gatewayConfig) {
                        $gatewayConfig = \App\Models\PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                            ->where('gateway', 'stripe')
                            ->where('active', true)
                            ->where('environment', 'test')
                            ->first();
                    }
                }
            }
            
            // Se não encontrou pela transação, busca qualquer configuração ativa
            if (!$gatewayConfig) {
                $gatewayConfig = \App\Models\PaymentGatewayConfig::where('gateway', 'stripe')
                    ->where('active', true)
                    ->where('environment', 'production')
                    ->first();
            }
            
            if (!$gatewayConfig) {
                $gatewayConfig = \App\Models\PaymentGatewayConfig::where('gateway', 'stripe')
                    ->where('active', true)
                    ->where('environment', 'test')
                    ->first();
            }

            if (!$gatewayConfig) {
                return response()->json([
                    'success' => false,
                    'error' => 'Configuração do Stripe não encontrada. Configure o gateway de pagamento nas configurações do sistema.'
                ], 404);
            }

            // Tenta decodificar o api_secret como JSON
            $stripeData = null;
            $publicKey = null;
            
            if ($gatewayConfig->api_secret) {
                // Tenta decodificar como JSON
                $decoded = json_decode($gatewayConfig->api_secret, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $stripeData = $decoded;
                    $publicKey = $stripeData['public_key'] ?? null;
                } else {
                    // Se não for JSON, pode ser que esteja no formato antigo (apenas secret_key como string)
                    // Nesse caso, a public_key não está salva
                    Log::warning('Chave pública do Stripe não encontrada - api_secret não está em formato JSON', [
                        'gateway_config_id' => $gatewayConfig->id,
                        'bot_id' => $gatewayConfig->bot_id,
                        'environment' => $gatewayConfig->environment,
                        'api_secret_type' => gettype($gatewayConfig->api_secret),
                        'api_secret_preview' => is_string($gatewayConfig->api_secret) ? substr($gatewayConfig->api_secret, 0, 20) . '...' : 'not_string'
                    ]);
                }
            }

            if (!$publicKey || empty(trim($publicKey))) {
                return response()->json([
                    'success' => false,
                    'error' => 'Chave pública do Stripe não configurada. Por favor, configure a chave pública (Public Key) nas configurações do gateway de pagamento (Configurações > Gateways de Pagamento > Stripe).'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'public_key' => $publicKey
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter configuração do Stripe', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro ao obter configuração do Stripe: ' . $e->getMessage()
            ], 500);
        }
    }

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
     * Cria um PaymentIntent no Stripe para iniciar o pagamento
     */
    public function createPaymentIntent(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
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

            // Verifica qual gateway está configurado
            $gateway = $transaction->gateway ?? 'stripe';

            if ($gateway === 'stripe') {
                // Cria PaymentIntent no Stripe
                $paymentService = new PaymentService();
                $result = $paymentService->createStripePaymentIntent($transaction);

                if ($result['success']) {
                    return response()->json([
                        'success' => true,
                        'client_secret' => $result['client_secret'],
                        'payment_intent_id' => $result['payment_intent_id'],
                        'transaction_id' => $result['transaction_id']
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => $result['error'] ?? 'Erro ao criar PaymentIntent.'
                    ], 400);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Gateway de pagamento não suportado: ' . $gateway
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao criar PaymentIntent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar PaymentIntent: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirma o pagamento após o Stripe.js processar no frontend
     */
    public function confirmPayment(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'payment_intent_id' => 'required|string',
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

            // Busca o PaymentIntent no Stripe para verificar o status
            $gatewayConfig = \App\Models\PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                ->where('gateway', 'stripe')
                ->where('active', true)
                ->first();

            if (!$gatewayConfig) {
                $gatewayConfig = \App\Models\PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                    ->where('gateway', 'stripe')
                    ->where('active', true)
                    ->where('environment', 'test')
                    ->first();
            }

            if (!$gatewayConfig) {
                return response()->json([
                    'success' => false,
                    'error' => 'Configuração do Stripe não encontrada.'
                ], 400);
            }

            $stripeData = json_decode($gatewayConfig->api_secret, true);
            $secretKey = $stripeData['secret_key'] ?? $gatewayConfig->api_secret ?? null;

            if (!$secretKey) {
                return response()->json([
                    'success' => false,
                    'error' => 'Chave secreta do Stripe não configurada.'
                ], 400);
            }

            \Stripe\Stripe::setApiKey($secretKey);
            $paymentIntent = \Stripe\PaymentIntent::retrieve($request->payment_intent_id);

            // Atualiza status da transação baseado no status do PaymentIntent
            $status = $paymentIntent->status;
            $internalStatus = 'pending';

            if ($status === 'succeeded') {
                $internalStatus = 'completed';
            } elseif ($status === 'requires_action' || $status === 'requires_payment_method') {
                $internalStatus = 'requires_action';
            } elseif ($status === 'canceled' || $status === 'payment_failed') {
                $internalStatus = 'failed';
            }

            $metadata = $transaction->metadata ?? [];
            $metadata['stripe_payment_intent_id'] = $paymentIntent->id;
            $metadata['stripe_status'] = $status;
            if (isset($paymentIntent->charges->data[0]->payment_method_details->card)) {
                $metadata['card_last4'] = $paymentIntent->charges->data[0]->payment_method_details->card->last4 ?? null;
                $metadata['card_brand'] = $paymentIntent->charges->data[0]->payment_method_details->card->brand ?? null;
            }
            $metadata['processed_at'] = now()->toIso8601String();

            $transaction->update([
                'status' => $internalStatus,
                'metadata' => $metadata
            ]);

            if ($status === 'succeeded') {
                return response()->json([
                    'success' => true,
                    'message' => 'Pagamento processado com sucesso!',
                    'transaction_id' => $transaction->id,
                    'payment_intent_id' => $paymentIntent->id
                ]);
            } elseif ($status === 'requires_action') {
                return response()->json([
                    'success' => false,
                    'requires_action' => true,
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'error' => 'Este pagamento requer autenticação adicional (3D Secure).'
                ], 402);
            } else {
                $errorMessage = 'Pagamento recusado.';
                if (isset($paymentIntent->last_payment_error)) {
                    $errorMessage = $paymentIntent->last_payment_error->message ?? $errorMessage;
                }

                return response()->json([
                    'success' => false,
                    'error' => $errorMessage,
                    'payment_intent_id' => $paymentIntent->id
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao confirmar pagamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao confirmar pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook do Mercado Pago
     */
    public function mercadoPagoWebhook(Request $request)
    {
        try {
            \Illuminate\Support\Facades\Log::info('Webhook Mercado Pago recebido', [
                'data' => $request->all(),
                'type' => $request->input('type'),
                'action' => $request->input('action'),
                'data_id' => $request->input('data.id')
            ]);

            // O Mercado Pago envia notificações no formato:
            // { "type": "payment", "action": "payment.created", "data": { "id": "123456789" } }
            $type = $request->input('type');
            $action = $request->input('action');
            $dataId = $request->input('data.id');

            if ($type === 'payment' && $dataId) {
                // Busca a transação pelo ID do pagamento do Mercado Pago
                $transaction = Transaction::where('gateway', 'mercadopago')
                    ->where(function($query) use ($dataId) {
                        $query->where('gateway_transaction_id', (string) $dataId)
                              ->orWhereRaw('JSON_EXTRACT(metadata, "$.mercadopago_payment_id") = ?', [(string) $dataId]);
                    })
                    ->with(['bot', 'contact', 'paymentPlan'])
                    ->first();

                if ($transaction) {
                    // Busca informações atualizadas do pagamento no Mercado Pago
                    $gatewayConfig = \App\Models\PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                        ->where('gateway', 'mercadopago')
                        ->where('active', true)
                        ->first();

                    if ($gatewayConfig && $gatewayConfig->api_key) {
                        \MercadoPago\MercadoPagoConfig::setAccessToken($gatewayConfig->api_key);
                        $client = new \MercadoPago\Client\Payment\PaymentClient();
                        
                        try {
                            $payment = $client->get($dataId);
                            
                            if ($payment) {
                                $status = $payment->status ?? 'pending';
                                $statusDetail = $payment->status_detail ?? null;
                                
                                // Mapeia status do Mercado Pago para status interno
                                $internalStatus = 'pending';
                                if ($status === 'approved') {
                                    $internalStatus = 'completed';
                                } elseif ($status === 'rejected' || $status === 'cancelled') {
                                    $internalStatus = 'failed';
                                } elseif ($status === 'refunded') {
                                    $internalStatus = 'refunded';
                                } elseif ($status === 'charged_back') {
                                    $internalStatus = 'charged_back';
                                }

                                // Atualiza transação
                                $metadata = $transaction->metadata ?? [];
                                $metadata['mercadopago_status'] = $status;
                                $metadata['mercadopago_status_detail'] = $statusDetail;
                                $metadata['last_webhook_update'] = now()->toIso8601String();
                                $metadata['webhook_action'] = $action;

                                $transaction->update([
                                    'status' => $internalStatus,
                                    'metadata' => $metadata
                                ]);

                                // Se o pagamento foi aprovado, pode executar ações adicionais
                                if ($status === 'approved' && $internalStatus === 'completed') {
                                    // Aqui você pode adicionar lógica adicional, como:
                                    // - Notificar o usuário via Telegram
                                    // - Ativar o plano
                                    // - Enviar confirmação por email
                                    
                                    \Illuminate\Support\Facades\Log::info('Pagamento PIX aprovado', [
                                        'transaction_id' => $transaction->id,
                                        'payment_id' => $dataId,
                                        'amount' => $transaction->amount
                                    ]);
                                }

                                return response()->json([
                                    'success' => true,
                                    'message' => 'Webhook processado com sucesso',
                                    'transaction_id' => $transaction->id,
                                    'status' => $internalStatus
                                ], 200);
                            }
                        } catch (\MercadoPago\Exceptions\MPApiException $e) {
                            \Illuminate\Support\Facades\Log::error('Erro ao buscar pagamento no Mercado Pago', [
                                'payment_id' => $dataId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                } else {
                    \Illuminate\Support\Facades\Log::warning('Transação não encontrada para o pagamento do Mercado Pago', [
                        'payment_id' => $dataId
                    ]);
                }
            }

            return response()->json(['received' => true], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao processar webhook do Mercado Pago', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook do Stripe
     */
    public function stripeWebhook(Request $request)
    {
        try {
            $payload = $request->getContent();
            $sigHeader = $request->header('Stripe-Signature');
            $event = null;

            Log::info('Webhook Stripe recebido', [
                'headers' => $request->headers->all(),
                'payload_size' => strlen($payload)
            ]);

            // Busca todas as configurações do Stripe para validar o webhook
            $gatewayConfigs = \App\Models\PaymentGatewayConfig::where('gateway', 'stripe')
                ->where('active', true)
                ->get();

            $event = null;
            $webhookSecret = null;

            // Tenta validar o webhook com cada configuração
            foreach ($gatewayConfigs as $config) {
                $stripeData = json_decode($config->api_secret, true);
                $webhookSecret = $stripeData['webhook_secret'] ?? null;

                if ($webhookSecret) {
                    try {
                        \Stripe\Stripe::setApiKey($stripeData['secret_key'] ?? $config->api_secret);
                        $event = \Stripe\Webhook::constructEvent(
                            $payload,
                            $sigHeader,
                            $webhookSecret
                        );
                        break; // Se validou, sai do loop
                    } catch (\Stripe\Exception\SignatureVerificationException $e) {
                        // Continua tentando com outras configurações
                        continue;
                    }
                }
            }

            // Se não conseguiu validar com nenhuma configuração
            if (!$event) {
                Log::warning('Webhook Stripe não pôde ser validado', [
                    'sig_header' => $sigHeader
                ]);
                // Retorna 200 mesmo assim para evitar retentativas do Stripe
                return response()->json(['received' => true], 200);
            }

            // Processa o evento
            $eventType = $event->type;
            $eventData = $event->data->object;

            Log::info('Evento Stripe processado', [
                'type' => $eventType,
                'id' => $event->id,
                'object_id' => $eventData->id ?? null
            ]);

            // Processa diferentes tipos de eventos
            switch ($eventType) {
                case 'payment_intent.succeeded':
                    $this->handleStripePaymentSucceeded($eventData);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handleStripePaymentFailed($eventData);
                    break;

                case 'payment_intent.canceled':
                    $this->handleStripePaymentCanceled($eventData);
                    break;

                case 'charge.refunded':
                    $this->handleStripeChargeRefunded($eventData);
                    break;

                default:
                    Log::info('Evento Stripe não processado', [
                        'type' => $eventType
                    ]);
            }

            return response()->json(['received' => true], 200);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Erro ao verificar assinatura do webhook Stripe', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook do Stripe', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Processa pagamento aprovado do Stripe
     */
    protected function handleStripePaymentSucceeded($paymentIntent)
    {
        try {
            $paymentIntentId = $paymentIntent->id;

            // Busca transação pelo PaymentIntent ID
            $transaction = Transaction::where('gateway', 'stripe')
                ->where(function($query) use ($paymentIntentId) {
                    $query->where('gateway_transaction_id', $paymentIntentId)
                          ->orWhereRaw('JSON_EXTRACT(metadata, "$.stripe_payment_intent_id") = ?', [$paymentIntentId]);
                })
                ->with(['bot', 'contact', 'paymentPlan'])
                ->first();

            if ($transaction) {
                $metadata = $transaction->metadata ?? [];
                $metadata['stripe_status'] = 'succeeded';
                $metadata['stripe_charge_id'] = $paymentIntent->charges->data[0]->id ?? null;
                $metadata['last_webhook_update'] = now()->toIso8601String();

                $transaction->update([
                    'status' => 'completed',
                    'metadata' => $metadata
                ]);

                Log::info('Pagamento Stripe confirmado via webhook', [
                    'transaction_id' => $transaction->id,
                    'payment_intent_id' => $paymentIntentId,
                    'amount' => $transaction->amount
                ]);
            } else {
                Log::warning('Transação não encontrada para PaymentIntent do Stripe', [
                    'payment_intent_id' => $paymentIntentId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao processar pagamento aprovado do Stripe', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Processa pagamento falhado do Stripe
     */
    protected function handleStripePaymentFailed($paymentIntent)
    {
        try {
            $paymentIntentId = $paymentIntent->id;

            $transaction = Transaction::where('gateway', 'stripe')
                ->where(function($query) use ($paymentIntentId) {
                    $query->where('gateway_transaction_id', $paymentIntentId)
                          ->orWhereRaw('JSON_EXTRACT(metadata, "$.stripe_payment_intent_id") = ?', [$paymentIntentId]);
                })
                ->first();

            if ($transaction) {
                $metadata = $transaction->metadata ?? [];
                $metadata['stripe_status'] = 'payment_failed';
                $metadata['stripe_failure_reason'] = $paymentIntent->last_payment_error->message ?? null;
                $metadata['last_webhook_update'] = now()->toIso8601String();

                $transaction->update([
                    'status' => 'failed',
                    'metadata' => $metadata
                ]);

                Log::info('Pagamento Stripe falhado via webhook', [
                    'transaction_id' => $transaction->id,
                    'payment_intent_id' => $paymentIntentId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao processar pagamento falhado do Stripe', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Processa pagamento cancelado do Stripe
     */
    protected function handleStripePaymentCanceled($paymentIntent)
    {
        try {
            $paymentIntentId = $paymentIntent->id;

            $transaction = Transaction::where('gateway', 'stripe')
                ->where(function($query) use ($paymentIntentId) {
                    $query->where('gateway_transaction_id', $paymentIntentId)
                          ->orWhereRaw('JSON_EXTRACT(metadata, "$.stripe_payment_intent_id") = ?', [$paymentIntentId]);
                })
                ->first();

            if ($transaction) {
                $metadata = $transaction->metadata ?? [];
                $metadata['stripe_status'] = 'canceled';
                $metadata['last_webhook_update'] = now()->toIso8601String();

                $transaction->update([
                    'status' => 'failed',
                    'metadata' => $metadata
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao processar pagamento cancelado do Stripe', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Processa reembolso do Stripe
     */
    protected function handleStripeChargeRefunded($charge)
    {
        try {
            $chargeId = $charge->id;
            $paymentIntentId = $charge->payment_intent ?? null;

            if ($paymentIntentId) {
                $transaction = Transaction::where('gateway', 'stripe')
                    ->where(function($query) use ($paymentIntentId) {
                        $query->where('gateway_transaction_id', $paymentIntentId)
                              ->orWhereRaw('JSON_EXTRACT(metadata, "$.stripe_payment_intent_id") = ?', [$paymentIntentId]);
                    })
                    ->first();

                if ($transaction) {
                    $metadata = $transaction->metadata ?? [];
                    $metadata['stripe_status'] = 'refunded';
                    $metadata['stripe_refund_id'] = $charge->refunds->data[0]->id ?? null;
                    $metadata['last_webhook_update'] = now()->toIso8601String();

                    $transaction->update([
                        'status' => 'refunded',
                        'metadata' => $metadata
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao processar reembolso do Stripe', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
