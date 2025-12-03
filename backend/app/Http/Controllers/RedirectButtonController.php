<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\RedirectButton;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RedirectButtonController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Lista botões de redirecionamento de um bot
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

            $buttons = RedirectButton::where('bot_id', $bot->id)
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            return response()->json(['buttons' => $buttons]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch redirect buttons'], 500);
        }
    }

    /**
     * Cria um novo botão de redirecionamento
     */
    public function store(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'link' => 'required|url|max:500',
            'order' => 'sometimes|integer|min:0',
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

            // Verifica limite de 3 botões
            $currentCount = RedirectButton::where('bot_id', $bot->id)->count();
            if ($currentCount >= 3) {
                return response()->json(['error' => 'Limite de 3 botões atingido'], 400);
            }

            $order = $request->order ?? $currentCount;

            $redirectButton = RedirectButton::create([
                'bot_id' => $bot->id,
                'title' => $request->title,
                'link' => $request->link,
                'order' => $order,
            ]);

            return response()->json(['button' => $redirectButton], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create redirect button: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza um botão de redirecionamento
     */
    public function update(Request $request, string $botId, string $buttonId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'link' => 'sometimes|url|max:500',
            'order' => 'sometimes|integer|min:0',
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

            $redirectButton = RedirectButton::where('id', $buttonId)
                ->where('bot_id', $bot->id)
                ->firstOrFail();

            $redirectButton->update($request->only(['title', 'link', 'order']));

            return response()->json(['button' => $redirectButton]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Redirect button not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update redirect button'], 500);
        }
    }

    /**
     * Remove um botão de redirecionamento
     */
    public function destroy(string $botId, string $buttonId): JsonResponse
    {
        try {
            $user = auth()->user();
            $bot = Bot::findOrFail($botId);

            // Verifica permissão de escrita
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $redirectButton = RedirectButton::where('id', $buttonId)
                ->where('bot_id', $bot->id)
                ->firstOrFail();

            $redirectButton->delete();

            return response()->json(['message' => 'Redirect button deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Redirect button not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete redirect button'], 500);
        }
    }
}
