<?php

namespace App\Http\Controllers;

use App\Models\PaymentGatewayConfig;
use App\Models\Bot;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentGatewayConfigController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = PaymentGatewayConfig::query();

            // Filter by bot_id if provided
            if ($request->has('botId') && !empty($request->botId)) {
                $query->where('bot_id', $request->botId);
            }

            // Aplicar filtro de acesso baseado em permissões de bot
            // Super admin vê todas as configs, outros só veem configs de bots criados por eles
            if (!$user->isSuperAdmin()) {
                // Buscar IDs dos bots que o usuário tem acesso
                $botQuery = Bot::query();
                $botQuery = $this->permissionService->filterAccessibleBots($user, $botQuery);
                $accessibleBotIds = $botQuery->pluck('id')->toArray();
                
                // Filtrar configs apenas dos bots acessíveis
                $query->whereIn('bot_id', $accessibleBotIds);
            }

            $configs = $query->get();
            // Formatar cada config para o frontend
            $formattedConfigs = $configs->map(function ($config) {
                return $this->formatConfigForFrontend($config);
            });
            
            return response()->json(['configs' => $formattedConfigs]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch payment gateway configs'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bot_id' => 'required|exists:bots,id',
            'gateway' => 'required|string|in:mercadopago,stripe,pix',
            'environment' => 'sometimes|string|in:sandbox,test,production',
            'api_key' => 'nullable|string|max:255',
            'api_secret' => 'nullable|string',
            'access_token' => 'nullable|string|max:255', // Mercado Pago
            'public_key' => 'nullable|string|max:255', // Mercado Pago e Stripe
            'client_id' => 'nullable|string|max:255', // Mercado Pago
            'client_secret' => 'nullable|string|max:255', // Mercado Pago
            'secret_key' => 'nullable|string', // Stripe
            'webhook_secret' => 'nullable|string', // Stripe e Mercado Pago
            'webhook_url' => 'nullable|string|max:255|url',
            'active' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean', // Frontend usa is_active
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            
            // Verificar se o usuário tem acesso ao bot
            $bot = Bot::find($request->bot_id);
            if (!$bot) {
                return response()->json(['error' => 'Bot not found'], 404);
            }
            
            if (!$user->isSuperAdmin() && $bot->user_id !== $user->id) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }
            
            // Mapear campos do frontend para o banco de dados
            $apiKey = $request->api_key ?? $request->access_token ?? null; // Mercado Pago usa access_token
            $apiSecret = $request->api_secret ?? $request->secret_key ?? null; // Stripe usa secret_key
            $active = $request->has('is_active') ? $request->is_active : ($request->active ?? true);
            $environment = $request->environment ?? 'test';

            // Validação específica por gateway
            if ($request->gateway === 'stripe') {
                if (empty($request->secret_key) && empty($apiSecret)) {
                    return response()->json([
                        'errors' => ['secret_key' => ['Secret Key é obrigatória para Stripe']]
                    ], 400);
                }
                if (empty($request->public_key)) {
                    return response()->json([
                        'errors' => ['public_key' => ['Public Key é obrigatória para Stripe']]
                    ], 400);
                }
            } elseif ($request->gateway === 'mercadopago') {
                if (empty($request->access_token) && empty($apiKey)) {
                    return response()->json([
                        'errors' => ['access_token' => ['Access Token é obrigatório para Mercado Pago']]
                    ], 400);
                }
            }

            // Check if config already exists for this bot/gateway/environment combination
            $existing = PaymentGatewayConfig::where('bot_id', $request->bot_id)
                ->where('gateway', $request->gateway)
                ->where('environment', $environment)
                ->first();

            $dataToSave = [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'webhook_url' => $request->webhook_url ?? null,
                'webhook_secret' => $request->webhook_secret ?? null,
                'active' => $active,
            ];

            // Para Mercado Pago, salvar todas as credenciais
            if ($request->gateway === 'mercadopago') {
                $dataToSave['public_key'] = $request->public_key ?? null;
                $dataToSave['client_id'] = $request->client_id ?? null;
                $dataToSave['client_secret'] = $request->client_secret ?? null;
            }

            // Para Stripe, salvar public_key e webhook_secret no api_secret como JSON
            if ($request->gateway === 'stripe') {
                // Se já existe uma configuração, preserva os dados existentes
                $stripeData = [];
                if ($existing && $existing->api_secret) {
                    $decoded = json_decode($existing->api_secret, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $stripeData = $decoded;
                    }
                }
                
                // Atualiza com os novos valores
                $stripeData['secret_key'] = $request->secret_key ?? $apiSecret ?? $stripeData['secret_key'] ?? null;
                $stripeData['public_key'] = $request->public_key ?? $stripeData['public_key'] ?? null;
                $stripeData['webhook_secret'] = $request->webhook_secret ?? $stripeData['webhook_secret'] ?? null;
                
                // Sempre salva como JSON
                $dataToSave['api_secret'] = json_encode($stripeData);
            }

            if ($existing) {
                // Update existing config
                $existing->update($dataToSave);
                return response()->json(['config' => $this->formatConfigForFrontend($existing)]);
            }

            // Create new config
            $config = PaymentGatewayConfig::create([
                'bot_id' => $request->bot_id,
                'gateway' => $request->gateway,
                'environment' => $environment,
                ...$dataToSave
            ]);

            return response()->json(['config' => $this->formatConfigForFrontend($config)], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create payment gateway config: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $config = PaymentGatewayConfig::findOrFail($id);
            
            // Verificar se o usuário tem acesso ao bot desta configuração
            if (!$user->isSuperAdmin()) {
                $bot = Bot::find($config->bot_id);
                if (!$bot || $bot->user_id !== $user->id) {
                    return response()->json(['error' => 'Acesso negado'], 403);
                }
            }
            
            return response()->json(['config' => $this->formatConfigForFrontend($config)]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Payment gateway config not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch payment gateway config'], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gateway' => 'sometimes|string|in:mercadopago,stripe,pix',
            'environment' => 'sometimes|string|in:sandbox,test,production',
            'api_key' => 'nullable|string|max:255',
            'api_secret' => 'nullable|string',
            'access_token' => 'nullable|string|max:255', // Mercado Pago
            'public_key' => 'nullable|string|max:255', // Mercado Pago e Stripe
            'client_id' => 'nullable|string|max:255', // Mercado Pago
            'client_secret' => 'nullable|string|max:255', // Mercado Pago
            'secret_key' => 'nullable|string', // Stripe
            'webhook_secret' => 'nullable|string', // Mercado Pago e Stripe
            'webhook_url' => 'nullable|string|max:255|url',
            'active' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean', // Frontend usa is_active
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            $config = PaymentGatewayConfig::findOrFail($id);
            
            // Verificar se o usuário tem acesso ao bot desta configuração
            if (!$user->isSuperAdmin()) {
                $bot = Bot::find($config->bot_id);
                if (!$bot || $bot->user_id !== $user->id) {
                    return response()->json(['error' => 'Acesso negado'], 403);
                }
            }
            
            // Validação específica por gateway
            if ($config->gateway === 'stripe') {
                // Se está atualizando secret_key, valida se foi enviada
                if ($request->has('secret_key') && empty($request->secret_key)) {
                    return response()->json([
                        'errors' => ['secret_key' => ['Secret Key é obrigatória para Stripe']]
                    ], 400);
                }
                // Se está atualizando public_key, valida se foi enviada
                if ($request->has('public_key') && empty($request->public_key)) {
                    return response()->json([
                        'errors' => ['public_key' => ['Public Key é obrigatória para Stripe']]
                    ], 400);
                }
            } elseif ($config->gateway === 'mercadopago') {
                // Se está atualizando access_token, valida se foi enviado
                if ($request->has('access_token') && empty($request->access_token)) {
                    return response()->json([
                        'errors' => ['access_token' => ['Access Token é obrigatório para Mercado Pago']]
                    ], 400);
                }
            }
            
            // Mapear campos do frontend para o banco de dados
            $updateData = [];
            
            if ($request->has('gateway')) {
                $updateData['gateway'] = $request->gateway;
            }
            if ($request->has('environment')) {
                $updateData['environment'] = $request->environment;
            }
            
            // Mapear api_key / access_token
            if ($request->has('api_key')) {
                $updateData['api_key'] = $request->api_key;
            } elseif ($request->has('access_token')) {
                $updateData['api_key'] = $request->access_token;
            }
            
            // Para Stripe, salvar public_key e webhook_secret no api_secret como JSON
            if ($config->gateway === 'stripe') {
                // Tenta decodificar dados existentes
                $stripeData = [];
                if ($config->api_secret) {
                    $decoded = json_decode($config->api_secret, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $stripeData = $decoded;
                    } else {
                        // Se não for JSON, assume que é apenas a secret_key antiga
                        $stripeData = [
                            'secret_key' => $config->api_secret,
                            'public_key' => null,
                            'webhook_secret' => null,
                        ];
                    }
                }
                
                // Atualiza apenas os campos que foram enviados (preserva os existentes se não foram enviados)
                if ($request->has('secret_key')) {
                    // Se foi enviado, atualiza (mesmo que seja vazio, mas validação já foi feita acima)
                    $stripeData['secret_key'] = $request->secret_key;
                }
                // Se não foi enviado, preserva o valor existente
                
                if ($request->has('public_key')) {
                    // Se foi enviado, atualiza
                    $stripeData['public_key'] = $request->public_key;
                }
                // Se não foi enviado, preserva o valor existente
                
                if ($request->has('webhook_secret')) {
                    // Se foi enviado, atualiza (pode ser vazio/null)
                    $stripeData['webhook_secret'] = $request->webhook_secret ?: null;
                }
                // Se não foi enviado, preserva o valor existente
                
                // Sempre salva como JSON
                $updateData['api_secret'] = json_encode($stripeData);
            } else {
                // Para outros gateways (Mercado Pago), atualiza api_secret normalmente
                if ($request->has('api_secret')) {
                    $updateData['api_secret'] = $request->api_secret;
                } elseif ($request->has('secret_key')) {
                    $updateData['api_secret'] = $request->secret_key;
                }
            }
            
            if ($request->has('webhook_url')) {
                $updateData['webhook_url'] = $request->webhook_url;
            }
            
            // Para Mercado Pago, salvar todas as credenciais
            if ($config->gateway === 'mercadopago') {
                if ($request->has('webhook_secret')) {
                    $updateData['webhook_secret'] = $request->webhook_secret ?: null;
                }
                if ($request->has('public_key')) {
                    $updateData['public_key'] = $request->public_key ?: null;
                }
                if ($request->has('client_id')) {
                    $updateData['client_id'] = $request->client_id ?: null;
                }
                if ($request->has('client_secret')) {
                    $updateData['client_secret'] = $request->client_secret ?: null;
                }
            }
            
            // Mapear active / is_active
            if ($request->has('is_active')) {
                $updateData['active'] = $request->is_active;
            } elseif ($request->has('active')) {
                $updateData['active'] = $request->active;
            }
            
            $config->update($updateData);

            return response()->json(['config' => $this->formatConfigForFrontend($config)]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Payment gateway config not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update payment gateway config: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $config = PaymentGatewayConfig::findOrFail($id);
            
            // Verificar se o usuário tem acesso ao bot desta configuração
            if (!$user->isSuperAdmin()) {
                $bot = Bot::find($config->bot_id);
                if (!$bot || $bot->user_id !== $user->id) {
                    return response()->json(['error' => 'Acesso negado'], 403);
                }
            }
            
            $config->delete();

            return response()->json(['message' => 'Payment gateway config deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Payment gateway config not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete payment gateway config'], 500);
        }
    }

    /**
     * Get config by bot, gateway and environment
     */
    public function getConfig(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'botId' => 'required|exists:bots,id',
            'gateway' => 'required|string|in:mercadopago,stripe,pix',
            'environment' => 'required|string|in:sandbox,test,production',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            
            // Verificar se o usuário tem acesso ao bot
            $bot = Bot::find($request->botId);
            if (!$bot) {
                return response()->json(['error' => 'Bot not found'], 404);
            }
            
            if (!$user->isSuperAdmin() && $bot->user_id !== $user->id) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }
            
            $config = PaymentGatewayConfig::where('bot_id', $request->botId)
                ->where('gateway', $request->gateway)
                ->where('environment', $request->environment)
                ->first();

            if (!$config) {
                return response()->json(['error' => 'Payment gateway config not found'], 404);
            }

            return response()->json(['config' => $this->formatConfigForFrontend($config)]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch payment gateway config'], 500);
        }
    }

    /**
     * Formata a configuração para o formato esperado pelo frontend
     */
    private function formatConfigForFrontend($config)
    {
        $formatted = [
            'id' => $config->id,
            'bot_id' => $config->bot_id,
            'gateway' => $config->gateway,
            'environment' => $config->environment,
            'webhook_url' => $config->webhook_url,
            'is_active' => $config->active,
            'active' => $config->active,
        ];

        // Mapear campos específicos por gateway
        if ($config->gateway === 'mercadopago') {
            $formatted['access_token'] = $config->api_key;
            $formatted['public_key'] = $config->public_key;
            $formatted['client_id'] = $config->client_id;
            $formatted['client_secret'] = $config->client_secret;
            $formatted['webhook_secret'] = $config->webhook_secret;
        } else {
            $formatted['api_key'] = $config->api_key;
        }

        if ($config->gateway === 'stripe') {
            // Tentar decodificar JSON do api_secret
            $stripeData = json_decode($config->api_secret, true);
            if (is_array($stripeData)) {
                $formatted['secret_key'] = $stripeData['secret_key'] ?? $config->api_secret;
                $formatted['public_key'] = $stripeData['public_key'] ?? null;
                $formatted['webhook_secret'] = $stripeData['webhook_secret'] ?? null;
            } else {
                $formatted['secret_key'] = $config->api_secret;
                $formatted['public_key'] = null;
                $formatted['webhook_secret'] = null;
            }
        } else {
            $formatted['api_secret'] = $config->api_secret;
        }

        return $formatted;
    }

    /**
     * Verifica o status da API do gateway de pagamento
     */
    public function checkApiStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'botId' => 'required|exists:bots,id',
            'gateway' => 'required|string|in:mercadopago,stripe',
            'environment' => 'required|string|in:test,production',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            
            // Verificar se o usuário tem acesso ao bot
            $bot = Bot::find($request->botId);
            if (!$bot) {
                return response()->json(['error' => 'Bot not found'], 404);
            }
            
            if (!$user->isSuperAdmin() && $bot->user_id !== $user->id) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }
            
            $config = PaymentGatewayConfig::where('bot_id', $request->botId)
                ->where('gateway', $request->gateway)
                ->where('environment', $request->environment)
                ->first();

            if (!$config) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Configuração não encontrada',
                    'details' => 'Configure as credenciais do gateway primeiro'
                ], 404);
            }

            $status = $this->verifyGatewayStatus($config);

            return response()->json($status);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao verificar status da API',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica o status do gateway específico
     */
    private function verifyGatewayStatus($config)
    {
        if ($config->gateway === 'mercadopago') {
            return $this->verifyMercadoPagoStatus($config);
        } elseif ($config->gateway === 'stripe') {
            return $this->verifyStripeStatus($config);
        }

        return [
            'status' => 'error',
            'message' => 'Gateway não suportado',
            'details' => 'Apenas Mercado Pago e Stripe são suportados'
        ];
    }

    /**
     * Verifica o status da API do Mercado Pago
     */
    private function verifyMercadoPagoStatus($config)
    {
        try {
            $accessToken = $config->api_key;
            
            if (empty($accessToken)) {
                return [
                    'status' => 'error',
                    'message' => 'Access Token não configurado',
                    'details' => 'Configure o Access Token nas configurações do gateway',
                    'timestamp' => now()->toIso8601String()
                ];
            }

            // Configura o SDK do Mercado Pago
            \MercadoPago\MercadoPagoConfig::setAccessToken($accessToken);
            
            // Tenta buscar informações do usuário (endpoint simples para verificar autenticação)
            $client = new \MercadoPago\Client\User\UserClient();
            $user = $client->get();
            
            if ($user && isset($user->id)) {
                // Busca métodos de pagamento disponíveis
                $paymentMethods = [];
                $pixEnabled = false;
                
                try {
                    // Usa o endpoint de payment methods para verificar métodos habilitados
                    $paymentMethodsClient = new \MercadoPago\Client\PaymentMethod\PaymentMethodClient();
                    $methods = $paymentMethodsClient->list();
                    
                    if ($methods && is_array($methods)) {
                        foreach ($methods as $method) {
                            if (isset($method->id)) {
                                $paymentMethods[] = $method->id;
                                if ($method->id === 'pix') {
                                    $pixEnabled = true;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Se não conseguir buscar métodos, continua sem essa informação
                    // Não é crítico para a verificação de status
                }
                
                // Verifica se PIX está na lista de métodos disponíveis
                // Se não estiver na lista, pode não estar habilitado ou a lista pode estar incompleta
                if (in_array('pix', $paymentMethods)) {
                    $pixEnabled = true;
                } else {
                    // Se não está na lista, não podemos determinar com certeza
                    // Pode ser que não esteja habilitado ou a API não retornou todos os métodos
                    $pixEnabled = null; // Indeterminado
                }
                
                $details = [
                    'user_id' => $user->id ?? null,
                    'environment' => $config->environment,
                    'gateway' => 'Mercado Pago',
                    'authenticated' => true
                ];
                
                // Adiciona informações sobre métodos de pagamento
                if (!empty($paymentMethods)) {
                    $details['payment_methods_available'] = $paymentMethods;
                    $details['pix_enabled'] = $pixEnabled;
                } else {
                    // Se não conseguiu buscar métodos, tenta inferir pelo menos sobre PIX
                    $details['pix_enabled'] = $pixEnabled ? true : 'indeterminado';
                    $details['note'] = 'Não foi possível verificar todos os métodos de pagamento disponíveis';
                }
                
                return [
                    'status' => 'success',
                    'message' => 'API do Mercado Pago está funcionando',
                    'details' => $details,
                    'timestamp' => now()->toIso8601String()
                ];
            }

            return [
                'status' => 'warning',
                'message' => 'Resposta inesperada da API',
                'details' => 'A API respondeu mas com formato inesperado',
                'timestamp' => now()->toIso8601String()
            ];
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $errorMessage = 'Erro na API do Mercado Pago';
            if ($e->getApiResponse() && isset($e->getApiResponse()->getContent()['message'])) {
                $errorMessage .= ': ' . $e->getApiResponse()->getContent()['message'];
            } else {
                $errorMessage .= ': ' . $e->getMessage();
            }

            return [
                'status' => 'error',
                'message' => 'Falha na autenticação ou conexão',
                'details' => $errorMessage,
                'timestamp' => now()->toIso8601String()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro ao conectar com a API',
                'details' => $e->getMessage(),
                'timestamp' => now()->toIso8601String()
            ];
        }
    }

    /**
     * Verifica o status da API do Stripe
     */
    private function verifyStripeStatus($config)
    {
        try {
            // Extrai a secret key do JSON ou usa diretamente
            $stripeData = json_decode($config->api_secret, true);
            $secretKey = null;
            
            if (is_array($stripeData) && isset($stripeData['secret_key'])) {
                $secretKey = $stripeData['secret_key'];
            } else {
                $secretKey = $config->api_secret;
            }
            
            if (empty($secretKey)) {
                return [
                    'status' => 'error',
                    'message' => 'Secret Key não configurada',
                    'details' => 'Configure a Secret Key nas configurações do gateway',
                    'timestamp' => now()->toIso8601String()
                ];
            }

            // Configura o Stripe
            \Stripe\Stripe::setApiKey($secretKey);
            
            // Tenta buscar informações da conta (endpoint simples para verificar autenticação)
            $account = \Stripe\Account::retrieve();
            
            if ($account && isset($account->id)) {
                return [
                    'status' => 'success',
                    'message' => 'API do Stripe está funcionando',
                    'details' => [
                        'account_id' => $account->id ?? null,
                        'country' => $account->country ?? null,
                        'default_currency' => $account->default_currency ?? null,
                        'environment' => $config->environment,
                        'gateway' => 'Stripe',
                        'authenticated' => true
                    ],
                    'timestamp' => now()->toIso8601String()
                ];
            }

            return [
                'status' => 'warning',
                'message' => 'Resposta inesperada da API',
                'details' => 'A API respondeu mas com formato inesperado',
                'timestamp' => now()->toIso8601String()
            ];
        } catch (\Stripe\Exception\AuthenticationException $e) {
            return [
                'status' => 'error',
                'message' => 'Falha na autenticação',
                'details' => 'A Secret Key fornecida é inválida ou expirada',
                'timestamp' => now()->toIso8601String()
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'status' => 'error',
                'message' => 'Erro na API do Stripe',
                'details' => $e->getMessage(),
                'timestamp' => now()->toIso8601String()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro ao conectar com a API',
                'details' => $e->getMessage(),
                'timestamp' => now()->toIso8601String()
            ];
        }
    }
}
