<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Services\TelegramService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BotController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = Bot::query();

            // Filtra bots baseado em permissões
            $query = $this->permissionService->filterAccessibleBots($user, $query);

            $bots = $query->get();
            return response()->json(['bots' => $bots]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch bots'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'token' => 'required|string',
            'telegram_group_id' => 'nullable|string',
            'active' => 'sometimes|boolean',
            'initial_message' => 'nullable|string',
            'top_message' => 'nullable|string',
            'button_message' => 'nullable|string|max:255',
            'activate_cta' => 'sometimes|boolean',
            'media_1_url' => 'nullable|string|max:500',
            'media_2_url' => 'nullable|string|max:500',
            'media_3_url' => 'nullable|string|max:500',
            'request_email' => 'sometimes|boolean',
            'request_phone' => 'sometimes|boolean',
            'request_language' => 'sometimes|boolean',
            'payment_method' => 'sometimes|string|in:credit_card,pix',
            'activated' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            
            // Verifica se usuário pode criar bots (admin ou super admin)
            if (!$user->isAdmin()) {
                return response()->json(['error' => 'Acesso negado. Apenas administradores podem criar bots.'], 403);
            }

            $bot = Bot::create([
                'user_id' => $user->isSuperAdmin() ? $request->input('user_id', auth()->id()) : auth()->id(),
                'name' => $request->name,
                'token' => $request->token,
                'telegram_group_id' => $request->telegram_group_id,
                'active' => $request->active ?? true,
                'initial_message' => $request->initial_message,
                'top_message' => $request->top_message,
                'button_message' => $request->button_message,
                'activate_cta' => $request->activate_cta ?? false,
                'media_1_url' => $request->media_1_url,
                'media_2_url' => $request->media_2_url,
                'media_3_url' => $request->media_3_url,
                'request_email' => $request->request_email ?? false,
                'request_phone' => $request->request_phone ?? false,
                'request_language' => $request->request_language ?? false,
                'payment_method' => $request->payment_method ?? 'credit_card',
                'activated' => $request->activated ?? false,
            ]);

            return response()->json(['bot' => $bot], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create bot: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Verifica permissão de leitura
            if (!$this->permissionService->hasBotPermission($user, (int)$id, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $bot = Bot::findOrFail($id);
            return response()->json(['bot' => $bot]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch bot'], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        
        // Verifica permissão de escrita
        if (!$this->permissionService->hasBotPermission($user, (int)$id, 'write')) {
            return response()->json(['error' => 'Acesso negado. Você não tem permissão para editar este bot.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'token' => 'sometimes|string',
            'telegram_group_id' => 'nullable|string',
            'active' => 'sometimes|boolean',
            'initial_message' => 'nullable|string',
            'top_message' => 'nullable|string',
            'button_message' => 'nullable|string|max:255',
            'activate_cta' => 'sometimes|boolean',
            'media_1_url' => 'nullable|string|max:500',
            'media_2_url' => 'nullable|string|max:500',
            'media_3_url' => 'nullable|string|max:500',
            'request_email' => 'sometimes|boolean',
            'request_phone' => 'sometimes|boolean',
            'request_language' => 'sometimes|boolean',
            'payment_method' => 'sometimes|string|in:credit_card,pix',
            'activated' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $bot = Bot::findOrFail($id);

            $bot->update($request->only([
                'name',
                'token',
                'telegram_group_id',
                'active',
                'initial_message',
                'top_message',
                'button_message',
                'activate_cta',
                'media_1_url',
                'media_2_url',
                'media_3_url',
                'request_email',
                'request_phone',
                'request_language',
                'payment_method',
                'activated',
            ]));

            return response()->json(['bot' => $bot]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update bot: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Verifica permissão de exclusão
            if (!$this->permissionService->hasBotPermission($user, (int)$id, 'delete')) {
                return response()->json(['error' => 'Acesso negado. Você não tem permissão para excluir este bot.'], 403);
            }

            $bot = Bot::findOrFail($id);
            $bot->delete();

            return response()->json(['message' => 'Bot deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete bot'], 500);
        }
    }

    /**
     * Initialize bot (start Telegram bot)
     */
    public function initialize(string $id): JsonResponse
    {
        try {
            $bot = Bot::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $telegramService = new TelegramService();
            $result = $telegramService->initializeBot($bot);

            if (!$result['success']) {
                return response()->json(['error' => $result['error'] ?? 'Failed to initialize bot'], 500);
            }

            return response()->json([
                'message' => $result['message'] ?? 'Bot initialized successfully',
                'bot' => $bot->fresh(),
                'bot_info' => $result['bot_info'] ?? null
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to initialize bot: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Stop bot (stop Telegram bot)
     */
    public function stop(string $id): JsonResponse
    {
        try {
            $bot = Bot::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $telegramService = new TelegramService();
            $result = $telegramService->stopBot($bot);

            if (!$result['success']) {
                return response()->json(['error' => $result['error'] ?? 'Failed to stop bot'], 500);
            }

            return response()->json([
                'message' => $result['message'] ?? 'Bot stopped successfully',
                'bot' => $bot->fresh()
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to stop bot: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get bot status
     */
    public function status(string $id): JsonResponse
    {
        try {
            $bot = Bot::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $telegramService = new TelegramService();
            $status = $telegramService->getBotStatus($bot);

            return response()->json($status);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get bot status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Validate bot token
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $telegramService = new TelegramService();
            $result = $telegramService->validateToken($request->token);

            if (!$result['valid']) {
                return response()->json([
                    'valid' => false,
                    'error' => $result['error'] ?? 'Token inválido'
                ], 400);
            }

            return response()->json([
                'valid' => true,
                'message' => 'Token válido',
                'bot' => $result['bot']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'error' => 'Erro ao validar token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate bot token and group
     */
    public function validateTokenAndGroup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'telegram_group_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $telegramService = new TelegramService();
            $result = $telegramService->validateTokenAndGroup(
                $request->token,
                $request->telegram_group_id
            );

            $isValid = $result['token_valid'] && ($result['group_valid'] || empty($request->telegram_group_id));

            return response()->json([
                'valid' => $isValid,
                'token_valid' => $result['token_valid'],
                'group_valid' => $result['group_valid'] ?? null,
                'bot_info' => $result['bot_info'],
                'group_info' => $result['group_info'],
                'errors' => $result['errors'],
                'message' => $isValid 
                    ? 'Token e grupo validados com sucesso!' 
                    : 'Validação falhou. Verifique os erros.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'error' => 'Erro ao validar: ' . $e->getMessage()
            ], 500);
        }
    }
}
