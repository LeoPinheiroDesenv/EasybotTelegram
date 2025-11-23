<?php

namespace App\Http\Controllers;

use App\Models\PaymentPlan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PaymentPlanController extends Controller
{
    /**
     * Lista todos os planos de pagamento
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentPlan::query();

        // Filtro por bot_id se fornecido
        if ($request->has('botId')) {
            $query->where('bot_id', $request->botId);
        }

        // Verifica se o bot pertence ao usuário autenticado
        $user = auth()->user();
        if ($request->has('botId')) {
            $bot = \App\Models\Bot::where('id', $request->botId)
                ->where('user_id', $user->id)
                ->first();
            
            if (!$bot) {
                return response()->json(['error' => 'Bot não encontrado ou não pertence ao usuário'], 404);
            }
        } else {
            // Se não há botId, retorna apenas planos dos bots do usuário
            $botIds = \App\Models\Bot::where('user_id', $user->id)->pluck('id');
            $query->whereIn('bot_id', $botIds);
        }

        $paymentPlans = $query->with(['bot', 'paymentCycle'])->get();

        return response()->json([
            'paymentPlans' => $paymentPlans
        ]);
    }

    /**
     * Cria um novo plano de pagamento
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bot_id' => 'required|exists:bots,id',
            'payment_cycle_id' => 'required|exists:payment_cycles,id',
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'charge_period' => 'nullable|string|in:day,week,month,year',
            'cycle' => 'nullable|integer|min:1',
            'message' => 'nullable|string',
            'pix_message' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Verifica se o bot pertence ao usuário
        $bot = \App\Models\Bot::where('id', $request->bot_id)
            ->where('user_id', auth()->id())
            ->first();
        
        if (!$bot) {
            return response()->json(['error' => 'Bot não encontrado ou não pertence ao usuário'], 404);
        }

        $paymentPlan = PaymentPlan::create($request->all());

        return response()->json([
            'paymentPlan' => $paymentPlan->load(['bot', 'paymentCycle'])
        ], 201);
    }

    /**
     * Exibe um plano de pagamento específico
     */
    public function show(Request $request, $id): JsonResponse
    {
        $paymentPlan = PaymentPlan::with(['bot', 'paymentCycle'])->find($id);

        if (!$paymentPlan) {
            return response()->json(['error' => 'Plano de pagamento não encontrado'], 404);
        }

        // Verifica se o bot pertence ao usuário
        $bot = \App\Models\Bot::where('id', $paymentPlan->bot_id)
            ->where('user_id', auth()->id())
            ->first();
        
        if (!$bot) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        return response()->json([
            'paymentPlan' => $paymentPlan
        ]);
    }

    /**
     * Atualiza um plano de pagamento
     */
    public function update(Request $request, $id): JsonResponse
    {
        $paymentPlan = PaymentPlan::find($id);

        if (!$paymentPlan) {
            return response()->json(['error' => 'Plano de pagamento não encontrado'], 404);
        }

        // Verifica se o bot pertence ao usuário
        $bot = \App\Models\Bot::where('id', $paymentPlan->bot_id)
            ->where('user_id', auth()->id())
            ->first();
        
        if (!$bot) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'payment_cycle_id' => 'nullable|exists:payment_cycles,id',
            'title' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'charge_period' => 'nullable|string|in:day,week,month,year',
            'cycle' => 'nullable|integer|min:1',
            'message' => 'nullable|string',
            'pix_message' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $paymentPlan->update($request->all());

        return response()->json([
            'paymentPlan' => $paymentPlan->load(['bot', 'paymentCycle'])
        ]);
    }

    /**
     * Remove um plano de pagamento
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $paymentPlan = PaymentPlan::find($id);

        if (!$paymentPlan) {
            return response()->json(['error' => 'Plano de pagamento não encontrado'], 404);
        }

        // Verifica se o bot pertence ao usuário
        $bot = \App\Models\Bot::where('id', $paymentPlan->bot_id)
            ->where('user_id', auth()->id())
            ->first();
        
        if (!$bot) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $paymentPlan->delete();

        return response()->json([
            'message' => 'Plano de pagamento removido com sucesso'
        ]);
    }
}
