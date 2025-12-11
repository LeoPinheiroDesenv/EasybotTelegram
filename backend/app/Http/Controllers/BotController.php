<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Services\TelegramService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

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
            'payment_method' => 'sometimes|array|min:1',
            'payment_method.*' => 'in:credit_card,pix',
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

            // Valida token automaticamente ao criar
            $telegramService = new TelegramService();
            $validation = $telegramService->validateToken($request->token);
            
            if (!$validation['valid']) {
                return response()->json([
                    'error' => 'Token inválido: ' . ($validation['error'] ?? 'Token não pôde ser validado'),
                    'token_valid' => false
                ], 400);
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
                'payment_method' => $request->payment_method ?? ['credit_card'],
                'activated' => $request->activated ?? false,
            ]);

            // Se solicitado, ativa automaticamente
            if ($request->input('activate', false) && $bot->active) {
                $initResult = $telegramService->initializeBot($bot);
                if ($initResult['success']) {
                    $bot->refresh();
                }
            }

            return response()->json([
                'bot' => $bot,
                'bot_info' => $validation['bot'] ?? null,
                'token_valid' => true
            ], 201);
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
            'payment_method' => 'sometimes|array|min:1',
            'payment_method.*' => 'in:credit_card,pix',
            'activated' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $bot = Bot::findOrFail($id);
            $telegramService = new TelegramService();
            
            // Se o token foi alterado, valida automaticamente
            if ($request->has('token') && $request->token !== $bot->token) {
                $validation = $telegramService->validateToken($request->token);
                
                if (!$validation['valid']) {
                    return response()->json([
                        'error' => 'Token inválido: ' . ($validation['error'] ?? 'Token não pôde ser validado'),
                        'token_valid' => false
                    ], 400);
                }
            }

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

            // Se solicitado, ativa automaticamente
            if ($request->input('activate', false) && $bot->active && !$bot->activated) {
                $initResult = $telegramService->initializeBot($bot);
                if ($initResult['success']) {
                    $bot->refresh();
                }
            }

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
            $user = auth()->user();
            $bot = Bot::findOrFail($id);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$id, 'write')) {
                return response()->json(['error' => 'Acesso negado. Você não tem permissão para inicializar este bot.'], 403);
            }

            $telegramService = new TelegramService();
            $result = $telegramService->initializeBot($bot);

            if (!$result['success']) {
                return response()->json(['error' => $result['error'] ?? 'Failed to initialize bot'], 500);
            }

            return response()->json([
                'message' => $result['message'] ?? 'Bot initialized successfully',
                'bot' => $bot->fresh(),
                'bot_info' => $result['bot_info'] ?? null,
                'has_webhook' => $result['has_webhook'] ?? false,
                'webhook_url' => $result['webhook_url'] ?? null
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
            $user = auth()->user();
            $bot = Bot::findOrFail($id);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$id, 'write')) {
                return response()->json(['error' => 'Acesso negado. Você não tem permissão para parar este bot.'], 403);
            }

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
            $user = auth()->user();
            $bot = Bot::findOrFail($id);

            // Verifica permissão de leitura
            if (!$this->permissionService->hasBotPermission($user, (int)$id, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

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

    /**
     * Validate and activate bot in one step
     */
    public function validateAndActivate(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($id);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$id, 'write')) {
                return response()->json(['error' => 'Acesso negado. Você não tem permissão para ativar este bot.'], 403);
            }

            $telegramService = new TelegramService();

            // Valida token
            $validation = $telegramService->validateToken($bot->token);
            
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'error' => $validation['error'] ?? 'Token inválido',
                    'token_valid' => false
                ], 400);
            }

            // Ativa bot se estiver ativo
            if (!$bot->active) {
                $bot->update(['active' => true]);
            }

            // Inicializa bot
            $initResult = $telegramService->initializeBot($bot);

            if (!$initResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $initResult['error'] ?? 'Falha ao inicializar bot',
                    'token_valid' => true
                ], 500);
            }

            $bot->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Bot validado e ativado com sucesso!',
                'bot' => $bot,
                'bot_info' => $validation['bot'] ?? null,
                'token_valid' => true,
                'activated' => $bot->activated,
                'has_webhook' => $initResult['has_webhook'] ?? false,
                'webhook_url' => $initResult['webhook_url'] ?? null
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Erro ao validar e ativar bot: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload media file for bot
     */
    public function uploadMedia(Request $request, string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($id);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$id, 'write')) {
                return response()->json(['error' => 'Acesso negado. Você não tem permissão para fazer upload de mídia neste bot.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:jpg,jpeg,png,gif,mp4,avi,mov,webm,pdf,doc,docx|max:10240', // 10MB máximo
                'media_number' => 'required|integer|in:1,2,3'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 400);
            }

            $file = $request->file('file');
            $mediaNumber = $request->input('media_number');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            
            // Gera nome único para o arquivo
            $fileName = 'bot_' . $bot->id . '_media_' . $mediaNumber . '_' . time() . '.' . $extension;
            $path = 'bots/' . $bot->id . '/media';
            
            // Garante que o diretório existe
            if (!Storage::disk('public')->exists($path)) {
                Storage::disk('public')->makeDirectory($path);
            }
            
            // Verifica e cria link simbólico se necessário
            $this->ensureStorageLink();
            
            // Salva o arquivo no storage público
            $filePath = $file->storeAs($path, $fileName, 'public');
            
            // Gera URL pública do arquivo
            $url = Storage::disk('public')->url($filePath);
            
            // Garante que a URL está corretamente formatada (remove espaços duplos e codifica)
            $url = preg_replace('/\s+/', '%20', $url);
            
            // Atualiza o campo de mídia no bot
            $fieldName = 'media_' . $mediaNumber . '_url';
            
            // Remove arquivo antigo se existir
            if ($bot->$fieldName) {
                $oldUrl = $bot->$fieldName;
                // Extrai o caminho relativo da URL
                $baseUrl = Storage::disk('public')->url('');
                $oldPath = str_replace($baseUrl, '', $oldUrl);
                $oldPath = ltrim($oldPath, '/');
                
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    try {
                        Storage::disk('public')->delete($oldPath);
                    } catch (\Exception $e) {
                        // Log erro mas continua
                        \Log::warning('Erro ao deletar arquivo antigo: ' . $e->getMessage());
                    }
                }
            }
            
            $bot->$fieldName = $url;
            $bot->save();

            return response()->json([
                'success' => true,
                'message' => 'Mídia enviada com sucesso',
                'url' => $url,
                'file' => [
                    'name' => $originalName,
                    'path' => $filePath,
                    'url' => $url,
                    'size' => $file->getSize(),
                    'size_formatted' => $this->formatBytes($file->getSize())
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Bot não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Erro ao fazer upload de mídia: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete media file for bot
     */
    public function deleteMedia(Request $request, string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($id);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$id, 'write')) {
                return response()->json(['error' => 'Acesso negado. Você não tem permissão para deletar mídia deste bot.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'media_number' => 'required|integer|in:1,2,3'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 400);
            }

            $mediaNumber = $request->input('media_number');
            $fieldName = 'media_' . $mediaNumber . '_url';
            
            // Remove arquivo se existir
            if ($bot->$fieldName) {
                $oldUrl = $bot->$fieldName;
                // Extrai o caminho relativo da URL
                $baseUrl = Storage::disk('public')->url('');
                $oldPath = str_replace($baseUrl, '', $oldUrl);
                $oldPath = ltrim($oldPath, '/');
                
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    try {
                        Storage::disk('public')->delete($oldPath);
                    } catch (\Exception $e) {
                        // Log erro mas continua
                        \Log::warning('Erro ao deletar arquivo: ' . $e->getMessage());
                    }
                }
            }
            
            $bot->$fieldName = null;
            $bot->save();

            return response()->json([
                'success' => true,
                'message' => 'Mídia removida com sucesso'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Bot não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Erro ao remover mídia: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Update invite link for bot's Telegram group
     */
    public function updateInviteLink(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($id);

            // Verifica permissão de leitura
            if (!$this->permissionService->hasBotPermission($user, (int)$id, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Verifica se o bot tem um grupo configurado
            if (!$bot->telegram_group_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'O bot não tem um grupo do Telegram configurado. Configure o ID do grupo primeiro.'
                ], 400);
            }

            $telegramService = new TelegramService();
            
            // Obtém o ID do bot a partir do token
            $botId = null;
            try {
                $validation = $telegramService->validateToken($bot->token);
                if ($validation['valid'] && isset($validation['bot']['id'])) {
                    $botId = $validation['bot']['id'];
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao validar token do bot: ' . $e->getMessage()
                ], 500);
            }

            // Obtém o link de convite
            $result = $telegramService->getChatInviteLink($bot->token, $bot->telegram_group_id, $botId);

            if (!$result['success']) {
                $errorMessage = $result['error'] ?? 'Erro ao obter link de convite';
                $details = $result['details'] ?? [];
                
                // Adiciona informações detalhadas se disponíveis
                $fullErrorMessage = $errorMessage;
                if (isset($details['status'])) {
                    $fullErrorMessage .= ' Status do bot: ' . $details['status'];
                }
                if (isset($details['is_admin']) && $details['is_admin'] === false) {
                    $fullErrorMessage .= ' O bot não é administrador do grupo.';
                } elseif (isset($details['can_invite_users']) && $details['can_invite_users'] === false) {
                    $fullErrorMessage .= ' O bot não tem permissão para convidar usuários.';
                }

                return response()->json([
                    'success' => false,
                    'error' => $fullErrorMessage,
                    'details' => $details
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Link de convite obtido com sucesso!',
                'invite_link' => $result['invite_link'],
                'details' => $result['details'] ?? []
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Bot não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Garante que o link simbólico do storage existe
     */
    protected function ensureStorageLink(): void
    {
        $publicPath = public_path('storage');
        $storagePath = storage_path('app/public');

        // Se o link já existe e está correto, não faz nada
        if (is_link($publicPath)) {
            $linkTarget = readlink($publicPath);
            if ($linkTarget === $storagePath || realpath($linkTarget) === realpath($storagePath)) {
                return;
            }
        }

        // Se não existe ou está quebrado, tenta criar
        if (!File::exists($publicPath) || (is_link($publicPath) && !File::exists($publicPath))) {
            try {
                // Remove link quebrado
                if (File::exists($publicPath) && is_link($publicPath)) {
                    @unlink($publicPath);
                }

                // Cria o diretório public se não existir
                $publicDir = dirname($publicPath);
                if (!File::exists($publicDir)) {
                    File::makeDirectory($publicDir, 0755, true);
                }

                // Cria o link simbólico (apenas Linux/Unix)
                if (PHP_OS_FAMILY !== 'Windows') {
                    @symlink($storagePath, $publicPath);
                }
            } catch (\Exception $e) {
                // Log erro mas não interrompe o fluxo
                \Log::warning('Erro ao criar link simbólico automaticamente: ' . $e->getMessage());
            }
        }
    }
}
