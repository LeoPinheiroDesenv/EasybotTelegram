<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\BotCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BotCommandController extends Controller
{
    /**
     * Lista comandos de um bot
     */
    public function index(string $botId): JsonResponse
    {
        try {
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

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
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

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
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $botCommand = BotCommand::where('id', $commandId)
                ->where('bot_id', $bot->id)
                ->firstOrFail();

            $botCommand->update($request->only(['response', 'description', 'active']));

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
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $botCommand = BotCommand::where('id', $commandId)
                ->where('bot_id', $bot->id)
                ->firstOrFail();

            $botCommand->delete();

            return response()->json(['message' => 'Command deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Command not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete command'], 500);
        }
    }
}

