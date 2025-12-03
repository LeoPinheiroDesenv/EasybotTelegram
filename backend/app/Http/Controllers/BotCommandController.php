<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\BotCommand;
use App\Services\PermissionService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BotCommandController extends Controller
{
    protected $permissionService;
    protected $telegramService;

    public function __construct(PermissionService $permissionService, TelegramService $telegramService)
    {
        $this->permissionService = $permissionService;
        $this->telegramService = $telegramService;
    }

    /**
     * Lista comandos de um bot
     */
    public function index(string $botId): JsonResponse
    {
        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($botId);

            // Verifica permissão de leitura
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $commands = BotCommand::where('bot_id', $bot->id)
                ->orderBy('command')
                ->get();

            return response()->json(['commands' => $commands]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch commands'], 500);
        }
    }

    /**
     * Cria um novo comando
     */
    public function store(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'command' => 'required|string|max:50|regex:/^[a-zA-Z0-9_]+$/', // Sem barra, apenas alfanumérico e underscore
            'response' => 'required|string',
            'description' => 'nullable|string|max:255',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($botId);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Remove barra se presente
            $command = str_replace('/', '', $request->command);

            // Verifica se já existe
            $existing = BotCommand::where('bot_id', $bot->id)
                ->where('command', $command)
                ->first();

            if ($existing) {
                return response()->json(['error' => 'Comando já existe'], 400);
            }

            $botCommand = BotCommand::create([
                'bot_id' => $bot->id,
                'command' => $command,
                'response' => $request->response,
                'description' => $request->description,
                'active' => $request->active ?? true,
            ]);

            // Registra comandos no Telegram se o bot estiver ativado
            if ($bot->activated && $bot->active) {
                $this->telegramService->registerBotCommands($bot);
            }

            return response()->json(['command' => $botCommand], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create command: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza um comando
     */
    public function update(Request $request, string $botId, string $commandId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'response' => 'sometimes|string',
            'description' => 'nullable|string|max:255',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($botId);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $botCommand = BotCommand::where('id', $commandId)
                ->where('bot_id', $bot->id)
                ->firstOrFail();

            $botCommand->update($request->only(['response', 'description', 'active']));

            // Registra comandos no Telegram se o bot estiver ativado
            if ($bot->activated && $bot->active) {
                $this->telegramService->registerBotCommands($bot);
            }

            return response()->json(['command' => $botCommand]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Command not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update command'], 500);
        }
    }

    /**
     * Remove um comando
     */
    public function destroy(string $botId, string $commandId): JsonResponse
    {
        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($botId);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $botCommand = BotCommand::where('id', $commandId)
                ->where('bot_id', $bot->id)
                ->firstOrFail();

            $botCommand->delete();

            // Registra comandos no Telegram se o bot estiver ativado
            if ($bot->activated && $bot->active) {
                $this->telegramService->registerBotCommands($bot);
            }

            return response()->json(['message' => 'Command deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Command not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete command'], 500);
        }
    }

    /**
     * Registra comandos do bot no Telegram
     */
    public function registerCommands(string $botId): JsonResponse
    {
        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($botId);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            if (!$bot->activated || !$bot->active) {
                return response()->json(['error' => 'Bot não está ativo ou não foi inicializado'], 400);
            }

            $success = $this->telegramService->registerBotCommands($bot);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Comandos registrados no Telegram com sucesso'
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Erro ao registrar comandos no Telegram'
            ], 500);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to register commands: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtém comandos registrados no Telegram
     */
    public function getTelegramCommands(string $botId): JsonResponse
    {
        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($botId);

            // Verifica permissão de leitura
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $commands = $this->telegramService->getMyCommands($bot);

            return response()->json([
                'success' => true,
                'commands' => $commands
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get commands: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Deleta todos os comandos registrados no Telegram
     */
    public function deleteTelegramCommands(string $botId): JsonResponse
    {
        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($botId);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $result = $this->telegramService->deleteBotCommands($bot);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Comandos deletados do Telegram com sucesso'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao deletar comandos do Telegram'
                ], 500);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete commands: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Deleta um comando específico registrado no Telegram
     */
    public function deleteTelegramCommand(string $botId, Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($botId);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $commandName = $request->input('command');
            if (!$commandName) {
                return response()->json(['error' => 'Nome do comando é obrigatório'], 400);
            }

            $result = $this->telegramService->deleteBotCommand($bot, $commandName);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => "Comando '{$commandName}' deletado do Telegram com sucesso"
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao deletar comando do Telegram'
                ], 500);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete command: ' . $e->getMessage()], 500);
        }
    }
}

