<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Bot;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Obtém o timeout configurado para requisições à API do Telegram
     *
     * @return int
     */
    protected function getTimeout(): int
    {
        return (int) env('TELEGRAM_API_TIMEOUT', 30);
    }
    
    /**
     * Cria uma instância HTTP com timeout e retry configurados
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        return \Illuminate\Support\Facades\Http::timeout($this->getTimeout())
            ->retry(2, 1000); // 2 tentativas com 1 segundo de delay
    }
    /**
     * Recebe atualizações do Telegram via webhook
     *
     * @param Request $request
     * @param string $botId
     * @return JsonResponse
     */
    public function webhook(Request $request, string $botId): JsonResponse
    {
        try {
            $bot = Bot::find($botId);
            
            if (!$bot) {
                return response()->json(['error' => 'Bot not found'], 404);
            }

            if (!$bot->active || !$bot->activated) {
                return response()->json(['error' => 'Bot is not active'], 400);
            }

            // Valida origem da requisição (opcional mas recomendado)
            if (!$this->validateWebhookOrigin($request, $bot)) {
                Log::warning('Tentativa de webhook de origem não autorizada', [
                    'bot_id' => $botId,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                // Ainda assim processa, mas registra o aviso
            }

            $update = $request->all();
            
            // Processa a atualização de forma assíncrona usando queue
            ProcessTelegramUpdate::dispatch($bot, $update);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook do Telegram', [
                'bot_id' => $botId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Valida origem da requisição do webhook
     * 
     * Nota: O Telegram não fornece uma forma oficial de validar a origem,
     * mas podemos verificar alguns indicadores básicos
     *
     * @param Request $request
     * @param Bot $bot
     * @return bool
     */
    protected function validateWebhookOrigin(Request $request, Bot $bot): bool
    {
        // Verifica se a requisição tem estrutura válida de atualização do Telegram
        $update = $request->all();
        
        // Deve ter pelo menos um campo de atualização (message, callback_query, etc.)
        $validUpdateFields = ['message', 'callback_query', 'inline_query', 'chosen_inline_result', 'channel_post', 'edited_message'];
        
        foreach ($validUpdateFields as $field) {
            if (isset($update[$field])) {
                return true;
            }
        }

        // Se não tem nenhum campo válido, pode ser uma requisição inválida
        return false;
    }

    /**
     * Configura webhook para um bot
     *
     * @param string $botId
     * @return JsonResponse
     */
    public function setWebhook(string $botId): JsonResponse
    {
        try {
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $webhookUrl = config('app.url') . "/api/telegram/webhook/{$botId}";
            
            // Verifica se URL começa com https (obrigatório pelo Telegram)
            if (!str_starts_with($webhookUrl, 'https://')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Webhook requer HTTPS. URL deve começar com https://. URL atual: ' . $webhookUrl
                ], 400);
            }
            
            // Remove webhook existente primeiro (se houver)
            try {
                $this->http()
                    ->post("https://api.telegram.org/bot{$bot->token}/deleteWebhook");
            } catch (\Exception $e) {
                // Ignora erros ao remover webhook antigo
            }
            
            // Configura novo webhook com allowed_updates
            $response = $this->http()
                ->post("https://api.telegram.org/bot{$bot->token}/setWebhook", [
                    'url' => $webhookUrl,
                    'allowed_updates' => json_encode(['message', 'callback_query', 'inline_query', 'channel_post', 'edited_message'])
                ]);

            if (!$response->successful() || !$response->json()['ok']) {
                return response()->json([
                    'success' => false,
                    'error' => $response->json()['description'] ?? 'Erro ao configurar webhook'
                ], 400);
            }

            // Verifica webhook configurado
            $webhookInfo = $this->http()
                ->get("https://api.telegram.org/bot{$bot->token}/getWebhookInfo");

            $webhookData = $webhookInfo->json()['result'] ?? [];

            return response()->json([
                'success' => true,
                'message' => 'Webhook configurado com sucesso',
                'webhook_url' => $webhookUrl,
                'webhook_info' => [
                    'url' => $webhookData['url'] ?? null,
                    'has_custom_certificate' => $webhookData['has_custom_certificate'] ?? false,
                    'pending_update_count' => $webhookData['pending_update_count'] ?? 0,
                    'last_error_date' => $webhookData['last_error_date'] ?? null,
                    'last_error_message' => $webhookData['last_error_message'] ?? null,
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao configurar webhook: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove webhook de um bot
     *
     * @param string $botId
     * @return JsonResponse
     */
    public function deleteWebhook(string $botId): JsonResponse
    {
        try {
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $response = $this->http()
                ->post("https://api.telegram.org/bot{$bot->token}/deleteWebhook");

            if (!$response->successful() || !$response->json()['ok']) {
                return response()->json([
                    'success' => false,
                    'error' => $response->json()['description'] ?? 'Erro ao remover webhook'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook removido com sucesso'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover webhook: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém informações do webhook configurado
     *
     * @param string $botId
     * @return JsonResponse
     */
    public function getWebhookInfo(string $botId): JsonResponse
    {
        try {
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $response = $this->http()
                ->get("https://api.telegram.org/bot{$bot->token}/getWebhookInfo");

            if (!$response->successful() || !$response->json()['ok']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao obter informações do webhook'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'webhook_info' => $response->json()['result'] ?? []
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao obter informações do webhook: ' . $e->getMessage()
            ], 500);
        }
    }
}
