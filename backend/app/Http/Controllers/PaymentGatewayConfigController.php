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
            return response()->json(['configs' => $configs]);
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
            'webhook_url' => 'nullable|string|max:255|url',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            // Check if config already exists for this bot/gateway/environment combination
            $existing = PaymentGatewayConfig::where('bot_id', $request->bot_id)
                ->where('gateway', $request->gateway)
                ->where('environment', $request->environment ?? 'sandbox')
                ->first();

            if ($existing) {
                // Update existing config
                $existing->update([
                    'api_key' => $request->api_key ?? $existing->api_key,
                    'api_secret' => $request->api_secret ?? $existing->api_secret,
                    'webhook_url' => $request->webhook_url ?? $existing->webhook_url,
                    'active' => $request->has('active') ? $request->active : $existing->active,
                ]);
                return response()->json(['config' => $existing]);
            }

            // Create new config
            $config = PaymentGatewayConfig::create([
                'bot_id' => $request->bot_id,
                'gateway' => $request->gateway,
                'environment' => $request->environment ?? 'sandbox',
                'api_key' => $request->api_key,
                'api_secret' => $request->api_secret,
                'webhook_url' => $request->webhook_url,
                'active' => $request->active ?? true,
            ]);

            return response()->json(['config' => $config], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create payment gateway config'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $config = PaymentGatewayConfig::findOrFail($id);
            return response()->json(['config' => $config]);
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
            'webhook_url' => 'nullable|string|max:255|url',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $config = PaymentGatewayConfig::findOrFail($id);
            $config->update($request->only(['gateway', 'environment', 'api_key', 'api_secret', 'webhook_url', 'active']));

            return response()->json(['config' => $config]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Payment gateway config not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update payment gateway config'], 500);
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

            return response()->json(['config' => $config]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch payment gateway config'], 500);
        }
    }
}
