<?php

namespace App\Http\Controllers;

use App\Models\Downsell;
use App\Models\Bot;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DownsellController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Lista todos os downsells
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = Downsell::query();

            // Filtro por bot_id se fornecido
            if ($request->has('botId')) {
                $botId = $request->botId;
                
                // Verifica permissão
                if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'read')) {
                    return response()->json(['error' => 'Acesso negado'], 403);
                }

                $query->where('bot_id', $botId);
            } else {
                // Se não há botId, retorna apenas downsells dos bots do usuário
                $botIds = Bot::where('user_id', $user->id)->pluck('id');
                $query->whereIn('bot_id', $botIds);
            }

            // Filtros opcionais
            if ($request->has('active')) {
                $query->where('active', $request->active === 'true');
            }

            $downsells = $query->with(['bot', 'plan'])->orderBy('created_at', 'desc')->get();

            return response()->json(['downsells' => $downsells]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao carregar downsells: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cria um novo downsell
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bot_id' => 'required|exists:bots,id',
            'plan_id' => 'required|exists:payment_plans,id',
            'title' => 'required|string|max:255',
            'initial_media' => 'nullable|file|mimes:jpeg,jpg,png,gif,mp4,avi,mov|max:20480', // 20MB
            'message' => 'required|string',
            'promotional_value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'trigger_after_minutes' => 'required|integer|min:0',
            'trigger_event' => 'required|in:payment_failed,payment_canceled,checkout_abandoned',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            $botId = $request->bot_id;

            // Verifica permissão
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Verifica se o bot pertence ao usuário
            $bot = Bot::where('id', $botId)
                ->where('user_id', $user->id)
                ->first();

            if (!$bot) {
                return response()->json(['error' => 'Bot não encontrado ou não pertence ao usuário'], 404);
            }

            // Verifica se o plano pertence ao bot
            $plan = \App\Models\PaymentPlan::where('id', $request->plan_id)
                ->where('bot_id', $botId)
                ->first();

            if (!$plan) {
                return response()->json(['error' => 'Plano não encontrado ou não pertence ao bot'], 404);
            }

            $initialMediaUrl = null;

            // Upload de mídia se fornecida
            if ($request->hasFile('initial_media')) {
                $file = $request->file('initial_media');
                $path = $file->store('downsells', 'public');
                $initialMediaUrl = Storage::url($path);
            }

            $downsell = Downsell::create([
                'bot_id' => $botId,
                'plan_id' => $request->plan_id,
                'title' => $request->title,
                'initial_media_url' => $initialMediaUrl,
                'message' => $request->message,
                'promotional_value' => $request->promotional_value,
                'max_uses' => $request->max_uses,
                'trigger_after_minutes' => $request->trigger_after_minutes,
                'trigger_event' => $request->trigger_event,
                'active' => true,
                'quantity_uses' => 0,
            ]);

            return response()->json(['downsell' => $downsell->load(['bot', 'plan'])], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar downsell', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Erro ao criar downsell: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Exibe um downsell específico
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $downsell = Downsell::with(['bot', 'plan'])->findOrFail($id);

            // Verifica permissão
            if (!$this->permissionService->hasBotPermission($user, (int)$downsell->bot_id, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            return response()->json(['downsell' => $downsell]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Downsell não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao carregar downsell: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza um downsell
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'initial_media' => 'nullable|file|mimes:jpeg,jpg,png,gif,mp4,avi,mov|max:20480',
            'message' => 'sometimes|string',
            'promotional_value' => 'sometimes|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'trigger_after_minutes' => 'sometimes|integer|min:0',
            'trigger_event' => 'sometimes|in:payment_failed,payment_canceled,checkout_abandoned',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            $downsell = Downsell::findOrFail($id);

            // Verifica permissão
            if (!$this->permissionService->hasBotPermission($user, (int)$downsell->bot_id, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Upload de nova mídia se fornecida
            if ($request->hasFile('initial_media')) {
                // Remove mídia antiga se existir
                if ($downsell->initial_media_url) {
                    $oldPath = str_replace('/storage/', '', $downsell->initial_media_url);
                    Storage::disk('public')->delete($oldPath);
                }

                $file = $request->file('initial_media');
                $path = $file->store('downsells', 'public');
                $downsell->initial_media_url = Storage::url($path);
            }

            $downsell->update($request->only([
                'title',
                'message',
                'promotional_value',
                'max_uses',
                'trigger_after_minutes',
                'trigger_event',
                'active',
            ]));

            // Atualiza mídia se foi feito upload
            if ($request->hasFile('initial_media')) {
                $downsell->save();
            }

            return response()->json(['downsell' => $downsell->load(['bot', 'plan'])]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Downsell não encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar downsell', [
                'downsell_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Erro ao atualizar downsell: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove um downsell
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $downsell = Downsell::findOrFail($id);

            // Verifica permissão
            if (!$this->permissionService->hasBotPermission($user, (int)$downsell->bot_id, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Remove mídia se existir
            if ($downsell->initial_media_url) {
                $path = str_replace('/storage/', '', $downsell->initial_media_url);
                Storage::disk('public')->delete($path);
            }

            $downsell->delete();

            return response()->json(['message' => 'Downsell excluído com sucesso']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Downsell não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao excluir downsell: ' . $e->getMessage()], 500);
        }
    }
}
