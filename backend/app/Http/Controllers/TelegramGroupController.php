<?php

namespace App\Http\Controllers;

use App\Models\TelegramGroup;
use App\Models\Bot;
use App\Models\PaymentPlan;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TelegramGroupController extends Controller
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
            $validator = Validator::make($request->all(), [
                'bot_id' => 'required|exists:bots,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $botId = $request->input('bot_id');
            $user = auth()->user();

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $groups = TelegramGroup::where('bot_id', $botId)
                ->with('paymentPlan')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['groups' => $groups]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch groups: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bot_id' => 'required|exists:bots,id',
            'title' => 'required|string|max:255',
            'telegram_group_id' => 'required|string',
            'payment_plan_id' => 'nullable|exists:payment_plans,id',
            'type' => 'required|in:group,channel',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            $botId = (int)$request->input('bot_id');

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, $botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Verifica se já existe um grupo/canal com o mesmo ID para este bot
            $existing = TelegramGroup::where('bot_id', $botId)
                ->where('telegram_group_id', $request->input('telegram_group_id'))
                ->first();

            if ($existing) {
                return response()->json(['error' => 'Este grupo/canal já está cadastrado para este bot'], 409);
            }

            // Tenta obter o invite link do Telegram
            $inviteLink = null;
            try {
                $bot = Bot::findOrFail($botId);
                $telegramService = app(\App\Services\TelegramService::class);
                
                // Obtém o ID do bot
                $botInfo = $telegramService->validateToken($bot->token);
                $botIdForLink = $botInfo['valid'] && isset($botInfo['bot']['id']) ? $botInfo['bot']['id'] : null;
                
                // Tenta obter o invite link (não retorna erro se falhar na criação)
                $result = $telegramService->getChatInviteLink(
                    $bot->token,
                    $request->input('telegram_group_id'),
                    $botIdForLink
                );
                
                if ($result['success'] && $result['invite_link']) {
                    $inviteLink = $result['invite_link'];
                }
                // Se falhar, continua sem o link (não bloqueia a criação do grupo)
            } catch (\Exception $e) {
                // Se falhar ao obter invite link, continua sem ele
                \Illuminate\Support\Facades\Log::warning('Erro ao obter invite link na criação', [
                    'error' => $e->getMessage()
                ]);
            }

            $group = TelegramGroup::create([
                'bot_id' => $botId,
                'title' => $request->input('title'),
                'telegram_group_id' => $request->input('telegram_group_id'),
                'payment_plan_id' => $request->input('payment_plan_id'),
                'type' => $request->input('type'),
                'invite_link' => $inviteLink,
                'active' => $request->input('active', true),
            ]);

            $group->load('paymentPlan');

            return response()->json(['group' => $group], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create group: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $group = TelegramGroup::with(['bot', 'paymentPlan'])->findOrFail($id);
            $user = auth()->user();

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$group->bot_id, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            return response()->json(['group' => $group]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Group not found'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'payment_plan_id' => 'nullable|exists:payment_plans,id',
            'type' => 'sometimes|in:group,channel',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $group = TelegramGroup::findOrFail($id);
            $user = auth()->user();

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$group->bot_id, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Tenta atualizar o invite link se necessário
            if ($request->has('update_invite_link') && $request->input('update_invite_link')) {
                try {
                    $bot = Bot::findOrFail($group->bot_id);
                    $telegramService = app(\App\Services\TelegramService::class);
                    
                    // Obtém o ID do bot
                    $botInfo = $telegramService->validateToken($bot->token);
                    $botId = $botInfo['valid'] && isset($botInfo['bot']['id']) ? $botInfo['bot']['id'] : null;
                    
                    // Tenta obter o link de convite
                    $result = $telegramService->getChatInviteLink(
                        $bot->token,
                        $group->telegram_group_id,
                        $botId
                    );
                    
                    if ($result['success'] && $result['invite_link']) {
                        $request->merge(['invite_link' => $result['invite_link']]);
                    } else {
                        // Se falhou, retorna erro detalhado
                        $errorMessage = $result['error'] ?? 'Não foi possível obter o link de convite.';
                        $details = $result['details'] ?? [];
                        
                        // Adiciona informações úteis ao erro
                        if (isset($details['status'])) {
                            $errorMessage .= ' Status do bot: ' . $details['status'];
                        }
                        if (isset($details['is_admin']) && !$details['is_admin']) {
                            $errorMessage .= ' O bot não é administrador do grupo/canal.';
                        }
                        
                        return response()->json([
                            'error' => $errorMessage,
                            'details' => $details
                        ], 400);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'error' => 'Erro ao obter link de convite: ' . $e->getMessage()
                    ], 500);
                }
            }

            $group->update($request->only(['title', 'payment_plan_id', 'type', 'active', 'invite_link']));
            $group->load('paymentPlan');

            return response()->json(['group' => $group]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update group: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $group = TelegramGroup::findOrFail($id);
            $user = auth()->user();

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$group->bot_id, 'delete')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $group->delete();

            return response()->json(['message' => 'Group deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete group: ' . $e->getMessage()], 500);
        }
    }
}
