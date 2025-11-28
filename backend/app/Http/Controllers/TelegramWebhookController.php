<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Bot;
use App\Services\TelegramService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }
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
            
            // Processa a atualização de forma síncrona para garantir resposta imediata
            // Se necessário processar de forma assíncrona, pode usar: ProcessTelegramUpdate::dispatch($bot, $update);
            try {
                $telegramService = new TelegramService();
                $telegramService->processUpdate($bot, $update);
            } catch (\Exception $e) {
                // Se falhar síncrono, tenta assíncrono como fallback
                Log::warning('Erro ao processar atualização síncrona, tentando assíncrono', [
                    'bot_id' => $botId,
                    'error' => $e->getMessage()
                ]);
                ProcessTelegramUpdate::dispatch($bot, $update);
            }

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
     * Valida usando secret_token se configurado, ou estrutura da atualização
     *
     * @param Request $request
     * @param Bot $bot
     * @return bool
     */
    protected function validateWebhookOrigin(Request $request, Bot $bot): bool
    {
        // Verifica secret_token se configurado no .env
        $expectedSecretToken = env('TELEGRAM_WEBHOOK_SECRET_TOKEN');
        if ($expectedSecretToken) {
            $receivedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if ($receivedToken !== $expectedSecretToken) {
                Log::warning('Webhook com secret_token inválido', [
                    'bot_id' => $bot->id,
                    'expected' => substr($expectedSecretToken, 0, 10) . '...',
                    'received' => $receivedToken ? substr($receivedToken, 0, 10) . '...' : 'não fornecido'
                ]);
                return false;
            }
            return true;
        }

        // Se não tem secret_token configurado, valida estrutura da atualização
        $update = $request->all();
        
        // Deve ter pelo menos um campo de atualização válido
        $validUpdateFields = [
            'message',
            'edited_message',
            'channel_post',
            'edited_channel_post',
            'inline_query',
            'chosen_inline_result',
            'callback_query',
            'shipping_query',
            'pre_checkout_query',
            'poll',
            'poll_answer',
            'my_chat_member',
            'chat_member',
            'chat_join_request'
        ];
        
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
     * @param Request $request
     * @param string $botId
     * @return JsonResponse
     */
    public function setWebhook(Request $request, string $botId): JsonResponse
    {
        try {
            $bot = Bot::findOrFail($botId);
            $user = auth()->user();

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $webhookUrl = $request->input('url') ?? config('app.url') . "/api/telegram/webhook/{$botId}";
            $secretToken = $request->input('secret_token');
            $allowedUpdates = $request->input('allowed_updates', [
                'message',
                'edited_message',
                'channel_post',
                'edited_channel_post',
                'inline_query',
                'chosen_inline_result',
                'callback_query',
                'shipping_query',
                'pre_checkout_query',
                'poll',
                'poll_answer',
                'my_chat_member',
                'chat_member',
                'chat_join_request'
            ]);
            
            // Verifica se URL começa com https (obrigatório pelo Telegram)
            if (!str_starts_with($webhookUrl, 'https://')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Webhook requer HTTPS. URL deve começar com https://. URL atual: ' . $webhookUrl
                ], 400);
            }

            // Valida porta (Telegram aceita apenas 443, 80, 88, 8443)
            $parsedUrl = parse_url($webhookUrl);
            $port = $parsedUrl['port'] ?? (str_starts_with($webhookUrl, 'https://') ? 443 : 80);
            if (!in_array($port, [443, 80, 88, 8443])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Porta inválida. Telegram aceita apenas portas: 443, 80, 88, 8443. Porta atual: ' . $port
                ], 400);
            }
            
            // Remove webhook existente primeiro (se houver)
            try {
                $this->http()
                    ->post("https://api.telegram.org/bot{$bot->token}/deleteWebhook", [
                        'drop_pending_updates' => $request->input('drop_pending_updates', false)
                    ]);
            } catch (\Exception $e) {
                // Ignora erros ao remover webhook antigo
            }
            
            // Prepara dados para setWebhook
            $webhookData = [
                'url' => $webhookUrl,
                'allowed_updates' => json_encode($allowedUpdates)
            ];

            // Adiciona secret_token se fornecido
            if ($secretToken) {
                $webhookData['secret_token'] = $secretToken;
            }

            // Adiciona drop_pending_updates se solicitado
            if ($request->has('drop_pending_updates')) {
                $webhookData['drop_pending_updates'] = $request->input('drop_pending_updates');
            }

            // Configura novo webhook
            $response = $this->http()
                ->post("https://api.telegram.org/bot{$bot->token}/setWebhook", $webhookData);

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
                    'max_connections' => $webhookData['max_connections'] ?? null,
                    'allowed_updates' => $webhookData['allowed_updates'] ?? null,
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
            $bot = Bot::findOrFail($botId);
            $user = auth()->user();

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

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
            $bot = Bot::findOrFail($botId);
            $user = auth()->user();

            // Verifica permissão no bot
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

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
