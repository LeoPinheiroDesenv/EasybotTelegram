<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

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
            /** @var User|null $user */
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Usuário não autenticado'], 401);
            }
            $billing = $this->billingService->getMonthlyBilling($user);
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
            /** @var User|null $user */
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Usuário não autenticado'], 401);
            }
            
            // Verifica se o bot pertence ao usuário (se não for super admin)
            if ($request->bot_id) {
                $bot = \App\Models\Bot::find($request->bot_id);
                
                if (!$bot) {
                    return response()->json(['error' => 'Bot não encontrado'], 404);
                }
                
                // Se não for super admin, verifica se o bot pertence ao usuário
                if (!$user->isSuperAdmin() && $bot->user_id !== $user->id) {
                    return response()->json(['error' => 'Bot não pertence ao usuário'], 403);
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

            $billing = $this->billingService->getBillingWithFilters($user, $filters);
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
            /** @var User|null $user */
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Usuário não autenticado'], 401);
            }
            $months = $request->input('months', 12);
            $chartData = $this->billingService->getMonthlyChartData($user, $months);
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
            /** @var User|null $user */
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Usuário não autenticado'], 401);
            }
            $total = $this->billingService->getTotalBilling($user);
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

    /**
     * Obtém estatísticas do dashboard
     */
    public function getDashboardStatistics(): JsonResponse
    {
        try {
            /** @var User|null $user */
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Usuário não autenticado'], 401);
            }
            $statistics = $this->billingService->getDashboardStatistics($user);
            return response()->json($statistics);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao obter estatísticas do dashboard: ' . $e->getMessage()
            ], 500);
        }
    }
}

