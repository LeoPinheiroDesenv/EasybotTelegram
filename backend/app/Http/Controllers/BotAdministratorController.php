<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\BotAdministrator;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BotAdministratorController extends Controller
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

            $user = auth()->user();
            $botId = $request->input('bot_id');

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $administrators = BotAdministrator::where('bot_id', $botId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['administrators' => $administrators]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao carregar administradores: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bot_id' => 'required|exists:bots,id',
            'telegram_user_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            $botId = $request->input('bot_id');

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Verifica se já existe
            $existing = BotAdministrator::where('bot_id', $botId)
                ->where('telegram_user_id', $request->input('telegram_user_id'))
                ->first();

            if ($existing) {
                return response()->json(['error' => 'Este administrador já está cadastrado para este bot'], 400);
            }

            $administrator = BotAdministrator::create([
                'bot_id' => $botId,
                'telegram_user_id' => $request->input('telegram_user_id'),
            ]);

            return response()->json([
                'message' => 'Administrador adicionado com sucesso',
                'administrator' => $administrator
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao adicionar administrador: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $administrator = BotAdministrator::findOrFail($id);
            $user = auth()->user();

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$administrator->bot_id, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            return response()->json(['administrator' => $administrator]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Administrador não encontrado'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'telegram_user_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $administrator = BotAdministrator::findOrFail($id);
            $user = auth()->user();

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$administrator->bot_id, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Verifica se já existe outro com o mesmo telegram_user_id
            $existing = BotAdministrator::where('bot_id', $administrator->bot_id)
                ->where('telegram_user_id', $request->input('telegram_user_id'))
                ->where('id', '!=', $id)
                ->first();

            if ($existing) {
                return response()->json(['error' => 'Este administrador já está cadastrado para este bot'], 400);
            }

            $administrator->update([
                'telegram_user_id' => $request->input('telegram_user_id'),
            ]);

            return response()->json([
                'message' => 'Administrador atualizado com sucesso',
                'administrator' => $administrator
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao atualizar administrador: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $administrator = BotAdministrator::findOrFail($id);
            $user = auth()->user();

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$administrator->bot_id, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $administrator->delete();

            return response()->json(['message' => 'Administrador removido com sucesso']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao remover administrador: ' . $e->getMessage()], 500);
        }
    }
}
