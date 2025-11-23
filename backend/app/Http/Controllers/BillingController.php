<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BillingController extends Controller
{
    protected $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Obtém faturamento mensal atual
     */
    public function getMonthlyBilling(): JsonResponse
    {
        try {
            $billing = $this->billingService->getMonthlyBilling(auth()->id());
            return response()->json($billing);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao obter faturamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém faturamento com filtros
     */
    public function getBilling(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'month' => 'nullable|date_format:Y-m',
            'bot_id' => 'nullable|integer|exists:bots,id',
            'payment_method' => 'nullable|string|in:credit_card,pix',
            'gateway' => 'nullable|string|in:mercadopago,stripe',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            // Verifica se o bot pertence ao usuário
            if ($request->bot_id) {
                $bot = \App\Models\Bot::where('id', $request->bot_id)
                    ->where('user_id', auth()->id())
                    ->first();
                
                if (!$bot) {
                    return response()->json(['error' => 'Bot não encontrado ou não pertence ao usuário'], 404);
                }
            }

            $filters = $request->only([
                'start_date',
                'end_date',
                'month',
                'bot_id',
                'payment_method',
                'gateway'
            ]);

            $billing = $this->billingService->getBillingWithFilters(auth()->id(), $filters);
            return response()->json($billing);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao obter faturamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém dados para gráfico mensal
     */
    public function getChartData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'months' => 'nullable|integer|min:1|max:24',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $months = $request->input('months', 12);
            $chartData = $this->billingService->getMonthlyChartData(auth()->id(), $months);
            return response()->json(['data' => $chartData]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao obter dados do gráfico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém faturamento total
     */
    public function getTotalBilling(): JsonResponse
    {
        try {
            $total = $this->billingService->getTotalBilling(auth()->id());
            return response()->json([
                'total' => $total,
                'currency' => 'BRL'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao obter faturamento total: ' . $e->getMessage()
            ], 500);
        }
    }
}

