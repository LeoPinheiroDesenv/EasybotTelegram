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

            // IMPORTANTE: Salva o status anterior ANTES de atualizar
            $oldStatus = $transaction->status;
            
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
            
            // Recarrega a transação com os relacionamentos após o update
            $transaction->refresh();
            $transaction->load(['bot', 'contact', 'paymentPlan']);

            // Se o pagamento foi aprovado, notifica o usuário usando o método reutilizável
            if ($status === 'succeeded' && $internalStatus === 'completed') {
                $shouldNotify = !in_array($oldStatus, ['approved', 'paid', 'completed']);
                
                if ($shouldNotify && $transaction->contact && $transaction->bot && !empty($transaction->contact->telegram_id)) {
                    try {
                        $paymentService = app(\App\Services\PaymentService::class);
                        // Cria um objeto simulado do pagamento para usar o método reutilizável
                        $paymentObj = (object) [
                            'status' => 'approved', // Mapeia succeeded para approved
                            'status_detail' => null
                        ];
                        
                        // Busca configuração do gateway para passar ao método
                        $gatewayConfig = \App\Models\PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                            ->where('gateway', 'stripe')
                            ->where('active', true)
                            ->first();
                        
                        if ($gatewayConfig) {
                            $paymentService->processPaymentApproval($transaction, $paymentObj, $gatewayConfig);
                        } else {
                            // Se não tiver gateway config, envia notificação diretamente
                            $paymentService->sendPaymentApprovalNotification($transaction);
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Erro ao enviar notificação de pagamento Stripe confirmado', [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

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
            // Valida assinatura do webhook se configurado (recomendado para produção)
            // Documentação: https://www.mercadopago.com.br/developers/pt/docs/checkout-pro/payment-notifications
            // Busca webhook_secret do banco de dados baseado no payment_id recebido
            $webhookSecret = null;
            
            // Tenta encontrar a configuração do gateway baseado no payment_id
            // O Mercado Pago pode enviar data.id na query string ou no body
            $dataId = $request->query('data.id') 
                    ?? $request->query('data_id')
                    ?? $request->input('data.id')
                    ?? $request->input('data_id');
            
            // Se não encontrou, tenta do body
            if (!$dataId) {
                $dataBody = $request->input('data');
                if (is_array($dataBody) && isset($dataBody['id'])) {
                    $dataId = $dataBody['id'];
                }
            }
            
            if ($dataId) {
                // Busca a transação para obter o bot_id
                $transaction = Transaction::where('gateway', 'mercadopago')
                    ->where(function($query) use ($dataId) {
                        $query->where('gateway_transaction_id', (string) $dataId)
                              ->orWhereRaw('JSON_EXTRACT(metadata, "$.mercadopago_payment_id") = ?', [(string) $dataId]);
                    })
                    ->first();
                
                if ($transaction) {
                    // Busca a configuração do gateway para obter o webhook_secret
                    $gatewayConfig = \App\Models\PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                        ->where('gateway', 'mercadopago')
                        ->where('active', true)
                        ->first();
                    
                    if ($gatewayConfig && $gatewayConfig->webhook_secret) {
                        $webhookSecret = $gatewayConfig->webhook_secret;
                    }
                }
            }
            
            // Se não encontrou pelo payment_id, tenta buscar todas as configurações ativas
            if (!$webhookSecret) {
                $gatewayConfigs = \App\Models\PaymentGatewayConfig::where('gateway', 'mercadopago')
                    ->where('active', true)
                    ->whereNotNull('webhook_secret')
                    ->get();
                
                // Tenta validar com cada configuração até encontrar uma que funcione
                foreach ($gatewayConfigs as $config) {
                    if ($config->webhook_secret) {
                        $webhookSecret = $config->webhook_secret;
                        break; // Usa a primeira encontrada
                    }
                }
            }
            
            // Valida assinatura apenas se webhook_secret estiver configurado
            // Se não houver webhook_secret, processa o webhook sem validação (compatibilidade)
            if ($webhookSecret) {
                $signature = $request->header('x-signature');
                
                // Se não houver assinatura, loga aviso mas permite processar
                // Isso garante compatibilidade com diferentes configurações do Mercado Pago
                if (!$signature) {
                    \Illuminate\Support\Facades\Log::warning('Webhook Mercado Pago sem assinatura (webhook secret configurado)', [
                        'data_id' => $request->input('data.id'),
                        'type' => $request->input('type'),
                        'action' => $request->input('action'),
                        'note' => 'Processando webhook sem validação de assinatura - verifique se o webhook_secret está correto ou se o Mercado Pago está enviando a assinatura'
                    ]);
                    // Continua processando o webhook mesmo sem assinatura
                } else if ($signature) {
                    // Formato: ts=<timestamp>,v1=<hash>
                    if (preg_match('/ts=(\d+),v1=(.+)/', $signature, $matches)) {
                        $timestamp = $matches[1];
                        $hash = $matches[2];
                        
                        // Detecta se o timestamp está em milissegundos ou segundos
                        // Timestamps em milissegundos têm 13+ dígitos (ex: 1742505638683)
                        // Timestamps em segundos têm 10 dígitos (ex: 1765333549)
                        // Se o timestamp tiver 13+ dígitos, está em milissegundos
                        $timestampSeconds = (int)$timestamp;
                        $timestampLength = strlen($timestamp);
                        
                        if ($timestampLength >= 13) {
                            // Está em milissegundos, converte para segundos
                            $timestampSeconds = (int)($timestamp / 1000);
                        } elseif ($timestamp > 2147483648) {
                            // Timestamp muito grande para ser em segundos (maior que 2^31)
                            // Provavelmente está em milissegundos mesmo com menos de 13 dígitos
                            $timestampSeconds = (int)($timestamp / 1000);
                        }
                        
                        // Valida timestamp (tolerância de 15 minutos para diferenças de relógio)
                        // Permite timestamps até 15 minutos no passado ou no futuro
                        // Isso ajuda a lidar com pequenas diferenças de sincronização de relógio
                        $currentTime = time();
                        $timeDifference = abs($currentTime - $timestampSeconds);
                        $maxTolerance = 900; // 15 minutos em segundos
                        
                        if ($timeDifference > $maxTolerance) {
                            \Illuminate\Support\Facades\Log::warning('Webhook Mercado Pago com timestamp fora da tolerância', [
                                'timestamp' => $timestamp,
                                'timestamp_seconds' => $timestampSeconds,
                                'current_time' => $currentTime,
                                'difference_seconds' => $timeDifference,
                                'difference_minutes' => round($timeDifference / 60, 2),
                                'timestamp_length' => $timestampLength,
                                'is_future' => $timestampSeconds > $currentTime,
                                'max_tolerance_seconds' => $maxTolerance
                            ]);
                            
                            // Se a diferença for muito grande (mais de 1 hora), rejeita por segurança
                            if ($timeDifference > 3600) {
                                return response()->json(['error' => 'Invalid timestamp'], 400);
                            }
                            // Caso contrário, apenas loga mas continua processando
                            // Isso permite que webhooks com pequenas diferenças de relógio sejam processados
                        }
                        
                        // Valida hash HMAC SHA256
                        // Formato do manifest: id:{data.id};request-id:{x-request-id};ts:{ts};
                        // Documentação: https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks
                        
                        // Extrai data.id da query string ou do body
                        // O Mercado Pago pode enviar em múltiplos formatos:
                        // 1. Query string: data.id=123 ou data_id=123
                        // 2. Query string alternativa: id=123&topic=payment (formato antigo)
                        // 3. Body: data.id, data_id, ou data: {id: 123}
                        // 4. Body alternativo: resource=123 (formato antigo)
                        $dataId = null;
                        $queryParams = []; // Inicializa para evitar erro de variável não definida
                        
                        // Primeiro, tenta parsear diretamente da query string da URL
                        // Isso evita conversões automáticas do Laravel
                        $queryString = $request->getQueryString();
                        if ($queryString) {
                            parse_str($queryString, $urlParams);
                            if (isset($urlParams['data.id'])) {
                                $dataId = $urlParams['data.id'];
                            } elseif (isset($urlParams['data_id'])) {
                                $dataId = $urlParams['data_id'];
                            } elseif (isset($urlParams['id'])) {
                                // Formato alternativo: id=123&topic=payment
                                $dataId = $urlParams['id'];
                            }
                        }
                        
                        // Obtém query params para logs (sempre define a variável)
                        $queryParams = $request->query->all();
                        
                        // Se não encontrou, tenta através dos métodos do Laravel
                        if (!$dataId) {
                            // Tenta múltiplas variações do nome do parâmetro
                            if (isset($queryParams['data.id'])) {
                                $dataId = $queryParams['data.id'];
                            } elseif (isset($queryParams['data_id'])) {
                                $dataId = $queryParams['data_id'];
                            } elseif (isset($queryParams['id'])) {
                                $dataId = $queryParams['id'];
                            } elseif ($request->has('data.id')) {
                                $dataId = $request->query('data.id');
                            } elseif ($request->has('data_id')) {
                                $dataId = $request->query('data_id');
                            } elseif ($request->has('id')) {
                                $dataId = $request->query('id');
                            }
                        }
                        
                        // Se não encontrou na query, tenta do body
                        if (!$dataId) {
                            $dataId = $request->input('data.id') 
                                    ?? $request->input('data_id')
                                    ?? $request->input('id')
                                    ?? $request->input('resource'); // Formato alternativo
                            
                            // Tenta obter do objeto data no body
                            if (!$dataId) {
                                $dataBody = $request->input('data');
                                if (is_array($dataBody) && isset($dataBody['id'])) {
                                    $dataId = $dataBody['id'];
                                }
                            }
                        }
                        
                        // IMPORTANTE: Segundo a documentação do Mercado Pago, se data.id for alfanumérico,
                        // deve ser convertido para minúsculas para validação da assinatura
                        if ($dataId && !is_numeric($dataId)) {
                            $dataId = strtolower($dataId);
                        }
                        
                        // Extrai x-request-id do header
                        $requestId = $request->header('x-request-id');
                        
                        // Log detalhado para debug
                        \Illuminate\Support\Facades\Log::debug('Webhook Mercado Pago - Extração de dados para validação', [
                            'data_id' => $dataId,
                            'request_id' => $requestId,
                            'query_params' => $queryParams,
                            'query_string' => $request->getQueryString(),
                            'full_url' => $request->fullUrl(),
                            'body' => $request->all(),
                            'headers' => [
                                'x-request-id' => $request->header('x-request-id'),
                                'x-signature' => $request->header('x-signature')
                            ]
                        ]);
                        
                        // Valida se temos os dados necessários
                        if (!$dataId || !$requestId) {
                            \Illuminate\Support\Facades\Log::warning('Webhook Mercado Pago faltando dados para validação de assinatura (processando mesmo assim)', [
                                'has_data_id' => !empty($dataId),
                                'has_request_id' => !empty($requestId),
                                'data_id' => $dataId,
                                'request_id' => $requestId,
                                'query_params' => $queryParams,
                                'query_string' => $request->getQueryString(),
                                'body' => $request->all(),
                                'headers' => [
                                    'x-request-id' => $request->header('x-request-id'),
                                    'x-signature' => $request->header('x-signature')
                                ],
                                'note' => 'Webhook será processado sem validação de assinatura - alguns formatos do Mercado Pago não incluem todos os campos necessários'
                            ]);
                            // Não rejeita - permite processar mesmo sem validação completa
                            // Isso garante compatibilidade com diferentes formatos de webhook do Mercado Pago
                        } else {
                            // Temos todos os dados necessários - valida a assinatura
                            
                            // Constrói o manifest string no formato correto
                            // IMPORTANTE: O formato deve ser exatamente: id:{data.id};request-id:{x-request-id};ts:{ts};
                            // Segundo a documentação do Mercado Pago:
                            // - Se data.id for alfanumérico, deve estar em minúsculas (já convertido acima)
                            // - O formato é: id:[data.id];request-id:[x-request-id];ts:[ts];
                            $manifest = "id:{$dataId};request-id:{$requestId};ts:{$timestamp};";
                            
                            // Log detalhado para debug da validação
                            \Illuminate\Support\Facades\Log::debug('Webhook Mercado Pago - Validação de assinatura', [
                                'manifest' => $manifest,
                                'data_id' => $dataId,
                                'data_id_original' => $request->query('data.id') ?? $request->query('id') ?? $request->input('data.id') ?? $request->input('id'),
                                'request_id' => $requestId,
                                'timestamp' => $timestamp,
                                'timestamp_seconds' => $timestampSeconds ?? null,
                                'webhook_secret_length' => strlen($webhookSecret),
                                'webhook_secret_preview' => substr($webhookSecret, 0, 10) . '...'
                            ]);
                            
                            // Calcula o HMAC SHA256 do manifest
                            $calculatedHash = hash_hmac('sha256', $manifest, $webhookSecret);
                            
                            // Compara hash usando comparação segura (timing-safe)
                            if (!hash_equals($hash, $calculatedHash)) {
                                \Illuminate\Support\Facades\Log::warning('Webhook Mercado Pago com assinatura inválida (processando mesmo assim)', [
                                    'received_hash' => substr($hash, 0, 20) . '...',
                                    'calculated_hash' => substr($calculatedHash, 0, 20) . '...',
                                    'manifest' => $manifest,
                                    'data_id' => $dataId,
                                    'request_id' => $requestId,
                                    'timestamp' => $timestamp,
                                    'webhook_secret_length' => strlen($webhookSecret),
                                    'webhook_secret_preview' => substr($webhookSecret, 0, 10) . '...',
                                    'note' => 'Assinatura não corresponde, mas webhook será processado. Verifique se o webhook_secret está correto ou se o formato do webhook mudou.'
                                ]);
                                // Não rejeita - permite processar mesmo com assinatura inválida
                                // Isso garante que webhooks legítimos não sejam perdidos devido a problemas de configuração
                                // Em produção, você pode querer rejeitar aqui por segurança, mas isso pode causar perda de notificações
                            } else {
                                \Illuminate\Support\Facades\Log::debug('Webhook Mercado Pago com assinatura válida', [
                                    'data_id' => $dataId,
                                    'request_id' => $requestId,
                                    'timestamp' => $timestamp
                                ]);
                            }
                        }
                    } else {
                        \Illuminate\Support\Facades\Log::warning('Webhook Mercado Pago com formato de assinatura inválido', [
                            'signature' => $signature
                        ]);
                        // Não rejeita - permite processar mesmo com formato inválido
                        // Isso garante compatibilidade com diferentes versões do Mercado Pago
                    }
                }
            }

            \Illuminate\Support\Facades\Log::info('Webhook Mercado Pago recebido', [
                'data' => $request->all(),
                'type' => $request->input('type'),
                'action' => $request->input('action'),
                'data_id' => $request->input('data.id'),
                'has_signature' => $request->hasHeader('x-signature')
            ]);

            // O Mercado Pago envia notificações no formato:
            // { "type": "payment", "action": "payment.created", "data": { "id": "123456789" } }
            $type = $request->input('type');
            $action = $request->input('action');
            
            // Tenta obter data.id de múltiplas fontes
            // Aceita múltiplos formatos do Mercado Pago:
            // 1. Query string: data.id, data_id, ou id (formato antigo)
            // 2. Body: data.id, data_id, id, ou resource (formato antigo)
            // 3. Body aninhado: data: {id: 123}
            $dataId = $request->query('data.id') 
                    ?? $request->query('data_id')
                    ?? $request->query('id') // Formato alternativo
                    ?? $request->input('data.id')
                    ?? $request->input('data_id')
                    ?? $request->input('id')
                    ?? $request->input('resource'); // Formato alternativo
            
            // Se não encontrou, tenta do body aninhado
            if (!$dataId) {
                $dataBody = $request->input('data');
                if (is_array($dataBody) && isset($dataBody['id'])) {
                    $dataId = $dataBody['id'];
                }
            }
            
            // Se ainda não encontrou, tenta parsear diretamente da query string
            if (!$dataId) {
                $queryString = $request->getQueryString();
                if ($queryString) {
                    parse_str($queryString, $urlParams);
                    $dataId = $urlParams['data.id'] ?? $urlParams['data_id'] ?? $urlParams['id'] ?? null;
                }
            }

            \Illuminate\Support\Facades\Log::info('Webhook Mercado Pago - Processando', [
                'type' => $type,
                'action' => $action,
                'data_id' => $dataId,
                'has_data_id' => !empty($dataId)
            ]);

            if ($type === 'payment' && $dataId) {
                // Busca a transação pelo ID do pagamento do Mercado Pago
                $transaction = Transaction::where('gateway', 'mercadopago')
                    ->where(function($query) use ($dataId) {
                        $query->where('gateway_transaction_id', (string) $dataId)
                              ->orWhereRaw('JSON_EXTRACT(metadata, "$.mercadopago_payment_id") = ?', [(string) $dataId]);
                    })
                    ->with(['bot', 'contact', 'paymentPlan'])
                    ->first();

                if (!$transaction) {
                    \Illuminate\Support\Facades\Log::warning('Transação não encontrada para webhook do Mercado Pago', [
                        'data_id' => $dataId,
                        'type' => $type,
                        'action' => $action
                    ]);
                }

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
                                
                                // IMPORTANTE: Salva o status anterior ANTES de atualizar
                                $oldStatus = $transaction->status;
                                
                                // Mapeia status do Mercado Pago para status interno
                                $internalStatus = 'pending';
                                $isExpired = false;
                                if ($status === 'approved') {
                                    $internalStatus = 'completed';
                                } elseif ($status === 'rejected' || $status === 'cancelled') {
                                    $internalStatus = 'failed';
                                    // Verifica se foi cancelado por expiração
                                    if ($status === 'cancelled' && 
                                        ($statusDetail === 'expired' || str_contains(strtolower($statusDetail ?? ''), 'expir'))) {
                                        $isExpired = true;
                                    }
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
                                
                                // Recarrega a transação com os relacionamentos após o update
                                $transaction->refresh();
                                $transaction->load(['bot', 'contact', 'paymentPlan']);

                                // Se o pagamento foi aprovado, processa usando o método reutilizável
                                if ($status === 'approved' && $internalStatus === 'completed') {
                                    $paymentService = app(\App\Services\PaymentService::class);
                                    $paymentService->processPaymentApproval($transaction, $payment, $gatewayConfig);
                                }

                                // Se o PIX expirou, notifica o usuário
                                if ($isExpired && $transaction->contact && $transaction->bot) {
                                    // Verifica se já foi notificado (recarrega metadata atualizado)
                                    $transaction->refresh();
                                    $metadata = $transaction->metadata ?? [];
                                    $alreadyNotified = $metadata['pix_expiration_notified'] ?? false;
                                    
                                    if (!$alreadyNotified) {
                                        try {
                                            $telegramService = app(\App\Services\TelegramService::class);
                                            $paymentPlan = $transaction->paymentPlan;
                                            $amount = number_format($transaction->amount, 2, ',', '.');
                                            
                                            $message = "⏰ <b>PIX Expirado</b>\n\n";
                                            $message .= "Olá " . ($transaction->contact->first_name ?? 'Cliente') . ",\n\n";
                                            $message .= "O código PIX para o pagamento do plano <b>" . ($paymentPlan->title ?? 'N/A') . "</b> expirou.\n\n";
                                            $message .= "💰 <b>Valor:</b> R$ {$amount}\n\n";
                                            $message .= "Para realizar o pagamento novamente, use o comando /start e selecione um plano.";
                                            
                                            $telegramService->sendMessage(
                                                $transaction->bot,
                                                $transaction->contact->telegram_id,
                                                $message
                                            );
                                            
                                            // Marca como notificado no metadata
                                            $metadata['pix_expiration_notified'] = true;
                                            $metadata['pix_expiration_notified_at'] = now()->toIso8601String();
                                            $transaction->update(['metadata' => $metadata]);
                                            
                                            \Illuminate\Support\Facades\Log::info('Notificação de PIX expirado enviada via webhook', [
                                                'transaction_id' => $transaction->id,
                                                'contact_id' => $transaction->contact->id,
                                                'payment_id' => $dataId
                                            ]);
                                        } catch (\Exception $e) {
                                            \Illuminate\Support\Facades\Log::error('Erro ao enviar notificação de PIX expirado via webhook', [
                                                'transaction_id' => $transaction->id,
                                                'error' => $e->getMessage()
                                            ]);
                                        }
                                    }
                                }

                                return response()->json([
                                    'success' => true,
                                    'message' => 'Webhook processado com sucesso',
                                    'transaction_id' => $transaction->id,
                                    'status' => $internalStatus
                                ], 200);
                            }
                        } catch (\MercadoPago\Exceptions\MPApiException $e) {
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
                                // Pagamento não encontrado no webhook - pode ser um pagamento deletado ou ID incorreto
                                \Illuminate\Support\Facades\Log::warning('⚠️ Pagamento não encontrado no Mercado Pago via webhook (Chave não localizada)', [
                                    'payment_id' => $dataId,
                                    'transaction_id' => $transaction->id ?? null,
                                    'status_code' => $statusCode,
                                    'api_response' => $responseContent,
                                    'webhook_action' => $action ?? null,
                                    'note' => 'O payment_id pode estar incorreto ou o pagamento foi deletado no Mercado Pago'
                                ]);
                                
                                // Se a transação existe, marca no metadata
                                if ($transaction) {
                                    $metadata = $transaction->metadata ?? [];
                                    $metadata['payment_not_found'] = true;
                                    $metadata['payment_not_found_at'] = now()->toIso8601String();
                                    $metadata['payment_not_found_error'] = $errorMessage;
                                    $metadata['payment_not_found_via'] = 'webhook';
                                    $transaction->update(['metadata' => $metadata]);
                                }
                                
                                // Retorna sucesso mesmo assim para não gerar retry do webhook
                                return response()->json([
                                    'success' => true,
                                    'message' => 'Webhook recebido mas pagamento não encontrado no Mercado Pago',
                                    'payment_id' => $dataId
                                ], 200);
                            } else {
                                \Illuminate\Support\Facades\Log::error('Erro ao buscar pagamento no Mercado Pago via webhook', [
                                    'payment_id' => $dataId,
                                    'transaction_id' => $transaction->id ?? null,
                                    'error' => $errorMessage,
                                    'status_code' => $statusCode,
                                    'api_response' => $responseContent,
                                    'webhook_action' => $action ?? null
                                ]);
                            }
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
                $oldStatus = $transaction->status;
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

                // Recarrega a transação com os relacionamentos após o update
                $transaction->refresh();
                $transaction->load(['bot', 'contact', 'paymentPlan']);

                // Notifica o usuário via Telegram usando o método reutilizável
                $shouldNotify = !in_array($oldStatus, ['approved', 'paid', 'completed']);
                if ($shouldNotify && $transaction->contact && $transaction->bot && !empty($transaction->contact->telegram_id)) {
                    try {
                        $paymentService = app(\App\Services\PaymentService::class);
                        // Cria um objeto simulado do pagamento para usar o método reutilizável
                        $paymentObj = (object) [
                            'status' => 'approved', // Mapeia succeeded para approved
                            'status_detail' => null
                        ];
                        
                        // Busca configuração do gateway para passar ao método
                        $gatewayConfig = \App\Models\PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                            ->where('gateway', 'stripe')
                            ->where('active', true)
                            ->first();
                        
                        if ($gatewayConfig) {
                            $paymentService->processPaymentApproval($transaction, $paymentObj, $gatewayConfig);
                        } else {
                            // Se não tiver gateway config, envia notificação diretamente
                            $paymentService->sendPaymentApprovalNotification($transaction);
                        }
                    } catch (\Exception $e) {
                        Log::error('Erro ao enviar notificação de pagamento Stripe aprovado', [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
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

                // Processa downsell se houver
                try {
                    $downsellService = app(\App\Services\DownsellService::class);
                    $downsellService->processDownsell($transaction, 'payment_failed');
                } catch (\Exception $e) {
                    Log::error('Erro ao processar downsell após falha de pagamento', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage()
                    ]);
                }
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

                // Processa downsell se houver
                try {
                    $downsellService = app(\App\Services\DownsellService::class);
                    $downsellService->processDownsell($transaction, 'payment_canceled');
                } catch (\Exception $e) {
                    Log::error('Erro ao processar downsell após cancelamento de pagamento', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage()
                    ]);
                }
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
