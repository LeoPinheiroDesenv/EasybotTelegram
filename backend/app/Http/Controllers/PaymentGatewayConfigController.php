<?php

namespace App\Http\Controllers;

use App\Models\PaymentGatewayConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentGatewayConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PaymentGatewayConfig::query();

            // Filter by bot_id if provided
            if ($request->has('botId') && !empty($request->botId)) {
                $query->where('bot_id', $request->botId);
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
            'secret_key' => 'nullable|string', // Stripe
            'public_key' => 'nullable|string|max:255', // Stripe
            'webhook_secret' => 'nullable|string', // Stripe
            'webhook_url' => 'nullable|string|max:255|url',
            'active' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean', // Frontend usa is_active
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            // Mapear campos do frontend para o banco de dados
            $apiKey = $request->api_key ?? $request->access_token ?? null; // Mercado Pago usa access_token
            $apiSecret = $request->api_secret ?? $request->secret_key ?? null; // Stripe usa secret_key
            $active = $request->has('is_active') ? $request->is_active : ($request->active ?? true);
            $environment = $request->environment ?? 'test';

            // Check if config already exists for this bot/gateway/environment combination
            $existing = PaymentGatewayConfig::where('bot_id', $request->bot_id)
                ->where('gateway', $request->gateway)
                ->where('environment', $environment)
                ->first();

            $dataToSave = [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'webhook_url' => $request->webhook_url,
                'active' => $active,
            ];

            // Para Stripe, salvar public_key e webhook_secret no api_secret como JSON
            if ($request->gateway === 'stripe') {
                $stripeData = [
                    'secret_key' => $apiSecret,
                    'public_key' => $request->public_key ?? null,
                    'webhook_secret' => $request->webhook_secret ?? null,
                ];
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
            $config = PaymentGatewayConfig::findOrFail($id);
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
            'secret_key' => 'nullable|string', // Stripe
            'public_key' => 'nullable|string|max:255', // Stripe
            'webhook_secret' => 'nullable|string', // Stripe
            'webhook_url' => 'nullable|string|max:255|url',
            'active' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean', // Frontend usa is_active
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $config = PaymentGatewayConfig::findOrFail($id);
            
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
            
            // Mapear api_secret / secret_key
            if ($request->has('api_secret')) {
                $updateData['api_secret'] = $request->api_secret;
            } elseif ($request->has('secret_key')) {
                $updateData['api_secret'] = $request->secret_key;
            }
            
            // Para Stripe, salvar public_key e webhook_secret no api_secret como JSON
            if ($config->gateway === 'stripe') {
                $stripeData = json_decode($config->api_secret, true) ?? [];
                if ($request->has('secret_key')) {
                    $stripeData['secret_key'] = $request->secret_key;
                }
                if ($request->has('public_key')) {
                    $stripeData['public_key'] = $request->public_key;
                }
                if ($request->has('webhook_secret')) {
                    $stripeData['webhook_secret'] = $request->webhook_secret;
                }
                // Se não tinha dados anteriores, criar estrutura
                if (empty($stripeData) && ($request->has('secret_key') || $request->has('public_key') || $request->has('webhook_secret'))) {
                    $stripeData = [
                        'secret_key' => $request->secret_key ?? null,
                        'public_key' => $request->public_key ?? null,
                        'webhook_secret' => $request->webhook_secret ?? null,
                    ];
                }
                if (!empty($stripeData)) {
                    $updateData['api_secret'] = json_encode($stripeData);
                }
            }
            
            if ($request->has('webhook_url')) {
                $updateData['webhook_url'] = $request->webhook_url;
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
            $config = PaymentGatewayConfig::findOrFail($id);
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
}
