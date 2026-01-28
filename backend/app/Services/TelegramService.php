<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotCommand;
use App\Models\Contact;
use App\Models\Log;
use App\Services\PaymentService;
use App\Services\ContactActionService;
use App\Services\PixCrcService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log as LogFacade;

class TelegramService
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
        return Http::timeout($this->getTimeout())
            ->retry(2, 1000); // 2 tentativas com 1 segundo de delay
    }

    /**
     * Valida um token do Telegram Bot
     *
     * @param string $token
     * @return array
     */
    public function validateToken(string $token): array
    {
        $maxRetries = 3;
        $retryDelay = 2; // segundos entre tentativas

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $this->http()->get("https://api.telegram.org/bot{$token}/getMe");

                if (!$response->successful()) {
                    $errorMessage = $response->json()['description'] ?? 'Token inválido ou inacessível';

                    // Se for erro de timeout e ainda houver tentativas, continua
                    if ($attempt < $maxRetries && str_contains($response->body(), 'timeout')) {
                        sleep($retryDelay);
                        continue;
                    }

                    return [
                        'valid' => false,
                        'error' => $errorMessage
                    ];
                }

                $data = $response->json();

                if (!$data['ok']) {
                    return [
                        'valid' => false,
                        'error' => $data['description'] ?? 'Token inválido'
                    ];
                }

                return [
                    'valid' => true,
                    'bot' => $data['result']
                ];
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Erro de conexão - tenta novamente se houver tentativas
                if ($attempt < $maxRetries) {
                    LogFacade::warning("Tentativa {$attempt} de conexão com Telegram falhou, tentando novamente...", [
                        'error' => $e->getMessage(),
                        'token' => substr($token, 0, 10) . '...'
                    ]);
                    sleep($retryDelay);
                    continue;
                }

                LogFacade::error('Erro ao conectar com a API do Telegram após ' . $maxRetries . ' tentativas', [
                    'error' => $e->getMessage(),
                    'token' => substr($token, 0, 10) . '...'
                ]);

                return [
                    'valid' => false,
                    'error' => 'Erro de conexão com a API do Telegram. Verifique sua conexão com a internet e tente novamente.'
                ];
            } catch (Exception $e) {
                // Outros erros
                if ($attempt < $maxRetries && (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'timed out'))) {
                    LogFacade::warning("Timeout na tentativa {$attempt}, tentando novamente...", [
                        'error' => $e->getMessage(),
                        'token' => substr($token, 0, 10) . '...'
                    ]);
                    sleep($retryDelay);
                    continue;
                }

                LogFacade::error('Erro ao validar token do Telegram', [
                    'error' => $e->getMessage(),
                    'token' => substr($token, 0, 10) . '...',
                    'attempt' => $attempt
                ]);

                return [
                    'valid' => false,
                    'error' => 'Erro ao conectar com a API do Telegram: ' . $e->getMessage()
                ];
            }
        }

        return [
            'valid' => false,
            'error' => 'Não foi possível conectar com a API do Telegram após ' . $maxRetries . ' tentativas. Verifique sua conexão com a internet.'
        ];
    }

    /**
     * Inicializa um bot do Telegram
     *
     * IMPORTANTE: Este método apenas valida e marca o bot como ativado.
     * Para receber atualizações, você DEVE:
     * - Usar polling: executar 'php artisan telegram:polling --bot-id={id}' em terminal separado
     * - OU configurar webhook: POST /api/telegram/webhook/{botId}/set
     *
     * @param Bot $bot
     * @return array
     */
    public function initializeBot(Bot $bot): array
    {
        try {
            if (!$bot->active) {
                return [
                    'success' => false,
                    'error' => 'Bot não está ativo'
                ];
            }

            // Valida token antes de inicializar
            $validation = $this->validateToken($bot->token);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error'] ?? 'Token inválido'
                ];
            }

            // Verifica se já existe webhook configurado
            $webhookInfo = $this->getWebhookInfo($bot->token);
            $hasWebhook = !empty($webhookInfo['url'] ?? null);

            // Marca bot como inicializado
            $bot->update(['activated' => true]);

            // Registra comandos do bot no Telegram
            $this->registerBotCommands($bot);

            // Configura webhook automaticamente se não estiver configurado
            $webhookConfigured = false;
            $webhookUrl = null;
            $webhookError = null;

            if (!$hasWebhook) {
                $webhookResult = $this->configureWebhook($bot);
                $webhookConfigured = $webhookResult['success'] ?? false;
                $webhookUrl = $webhookResult['webhook_url'] ?? null;
                $webhookError = $webhookResult['error'] ?? null;

                if ($webhookConfigured) {
                    $this->logBotAction($bot, 'Webhook configurado automaticamente durante inicialização', 'info', [
                        'webhook_url' => $webhookUrl
                    ]);
                } else {
                    $this->logBotAction($bot, 'Falha ao configurar webhook automaticamente: ' . ($webhookError ?? 'Erro desconhecido'), 'warning');
                }
            } else {
                $webhookUrl = $webhookInfo['url'] ?? null;
                $webhookConfigured = true;
            }

            $this->logBotAction($bot, 'Bot inicializado com sucesso', 'info');

            $message = 'Bot inicializado com sucesso. ';
            if ($webhookConfigured) {
                $message .= 'Webhook configurado. O bot receberá atualizações automaticamente.';
            } else {
                $message .= 'Webhook não pôde ser configurado automaticamente. ' . ($webhookError ?? 'Configure manualmente através da interface ou via API: POST /api/telegram/webhook/' . $bot->id . '/set');
            }

            return [
                'success' => true,
                'message' => $message,
                'bot_info' => $validation['bot'],
                'has_webhook' => $webhookConfigured,
                'webhook_url' => $webhookUrl,
                'webhook_error' => $webhookError,
                'next_steps' => $webhookConfigured ? [] : [
                    'webhook' => 'Configure webhook via POST /api/telegram/webhook/' . $bot->id . '/set ou através da interface de gerenciamento'
                ]
            ];
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao inicializar bot: ' . $e->getMessage(), 'error');

            return [
                'success' => false,
                'error' => 'Erro ao inicializar bot: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém informações do webhook configurado
     *
     * @param string $token
     * @return array
     */
    protected function getWebhookInfo(string $token): array
    {
        try {
            $response = $this->http()
                ->get("https://api.telegram.org/bot{$token}/getWebhookInfo");

            if ($response->successful() && $response->json()['ok']) {
                return $response->json()['result'] ?? [];
            }

            return [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Configura webhook para um bot automaticamente
     *
     * @param Bot $bot
     * @return array
     */
    protected function configureWebhook(Bot $bot): array
    {
        try {
            // Gera URL do webhook - usa variável de ambiente se disponível, senão usa APP_URL
            $baseUrl = env('TELEGRAM_WEBHOOK_URL') ?? config('app.url');
            $webhookUrl = $baseUrl . "/api/telegram/webhook/{$bot->id}";

            // Converte HTTP para HTTPS se necessário (Telegram requer HTTPS)
            if (str_starts_with($webhookUrl, 'http://')) {
                $webhookUrl = str_replace('http://', 'https://', $webhookUrl);
            }

            // Verifica se URL começa com https (obrigatório pelo Telegram)
            if (!str_starts_with($webhookUrl, 'https://')) {
                return [
                    'success' => false,
                    'error' => 'Webhook requer HTTPS. Configure TELEGRAM_WEBHOOK_URL no .env com URL HTTPS.'
                ];
            }

            // Valida porta (Telegram aceita apenas 443, 80, 88, 8443)
            $parsedUrl = parse_url($webhookUrl);
            $port = $parsedUrl['port'] ?? (str_starts_with($webhookUrl, 'https://') ? 443 : 80);
            if (!in_array($port, [443, 80, 88, 8443])) {
                return [
                    'success' => false,
                    'error' => 'Porta inválida. Telegram aceita apenas portas: 443, 80, 88, 8443. Porta atual: ' . $port
                ];
            }

            // Remove webhook existente primeiro (se houver)
            try {
                $this->http()
                    ->post("https://api.telegram.org/bot{$bot->token}/deleteWebhook", [
                        'drop_pending_updates' => false
                    ]);
            } catch (Exception $e) {
                // Ignora erros ao remover webhook antigo
            }

            // Prepara dados para setWebhook
            $allowedUpdates = [
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

            $webhookData = [
                'url' => $webhookUrl,
                'allowed_updates' => json_encode($allowedUpdates)
            ];

            // Configura novo webhook
            $response = $this->http()
                ->post("https://api.telegram.org/bot{$bot->token}/setWebhook", $webhookData);

            if (!$response->successful() || !$response->json()['ok']) {
                return [
                    'success' => false,
                    'error' => $response->json()['description'] ?? 'Erro ao configurar webhook'
                ];
            }

            // Verifica webhook configurado
            $webhookInfo = $this->getWebhookInfo($bot->token);

            return [
                'success' => true,
                'webhook_url' => $webhookUrl,
                'webhook_info' => $webhookInfo
            ];
        } catch (Exception $e) {
            LogFacade::error('Erro ao configurar webhook automaticamente', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao configurar webhook: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Para um bot do Telegram
     *
     * @param Bot $bot
     * @return array
     */
    public function stopBot(Bot $bot): array
    {
        try {
            $bot->update(['activated' => false]);

            $this->logBotAction($bot, 'Bot parado', 'info');

            return [
                'success' => true,
                'message' => 'Bot parado com sucesso'
            ];
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao parar bot: ' . $e->getMessage(), 'error');

            return [
                'success' => false,
                'error' => 'Erro ao parar bot: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém o status de um bot
     *
     * @param Bot $bot
     * @return array
     */
    public function getBotStatus(Bot $bot): array
    {
        try {
            $validation = $this->validateToken($bot->token);

            $status = [
                'bot_id' => $bot->id,
                'active' => $bot->active,
                'activated' => $bot->activated,
                'token_valid' => $validation['valid'],
                'bot_info' => $validation['valid'] ? $validation['bot'] : null,
                'error' => $validation['valid'] ? null : $validation['error']
            ];

            // Adiciona informações sobre permissões críticas
            if ($validation['valid'] && isset($validation['bot'])) {
                $botInfo = $validation['bot'];
                $warnings = [];
                $permissions = [];

                // Verifica permissão crítica: can_read_all_group_messages
                $canReadAllMessages = $botInfo['can_read_all_group_messages'] ?? false;
                $permissions['can_read_all_group_messages'] = $canReadAllMessages;

                if (!$canReadAllMessages) {
                    $warnings[] = [
                        'type' => 'critical',
                        'permission' => 'can_read_all_group_messages',
                        'message' => 'O bot não pode ler todas as mensagens do grupo. Isso impede o gerenciamento adequado do bot.',
                        'solution' => 'Configure esta permissão no BotFather do Telegram usando o comando /setprivacy e selecione "Disable" para permitir que o bot leia todas as mensagens.'
                    ];
                }

                // Verifica outras permissões importantes
                $permissions['can_join_groups'] = $botInfo['can_join_groups'] ?? false;
                if (!$permissions['can_join_groups']) {
                    $warnings[] = [
                        'type' => 'warning',
                        'permission' => 'can_join_groups',
                        'message' => 'O bot não pode entrar em grupos.',
                        'solution' => 'Configure esta permissão no BotFather usando /setjoingroups e selecione "Enable".'
                    ];
                }

                $status['permissions'] = $permissions;
                $status['warnings'] = $warnings;
                $status['can_manage_groups'] = $canReadAllMessages && $permissions['can_join_groups'];
            }

            return $status;
        } catch (Exception $e) {
            return [
                'bot_id' => $bot->id,
                'active' => $bot->active,
                'activated' => $bot->activated,
                'token_valid' => false,
                'error' => 'Erro ao verificar status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Valida token e grupo do bot
     *
     * @param string $token
     * @param string|null $groupId
     * @return array
     */
    public function validateTokenAndGroup(string $token, ?string $groupId = null): array
    {
        $result = [
            'token_valid' => false,
            'group_valid' => false,
            'group_info' => null,
            'bot_info' => null,
            'errors' => []
        ];

        // Valida token primeiro
        $tokenValidation = $this->validateToken($token);
        $result['token_valid'] = $tokenValidation['valid'];

        if (!$tokenValidation['valid']) {
            $result['errors'][] = 'Token inválido: ' . ($tokenValidation['error'] ?? 'Token não pôde ser validado');
            return $result;
        }

        $result['bot_info'] = $tokenValidation['bot'];

        // Se não há grupo informado, retorna apenas validação do token
        if (empty($groupId)) {
            return $result;
        }

        // Valida grupo (passa botInfo para evitar validação redundante)
        try {
            $groupValidation = $this->validateGroup($token, $groupId, $tokenValidation['bot']);
            $result['group_valid'] = $groupValidation['valid'];
            $result['group_info'] = $groupValidation['group_info'] ?? null;

            if (!$groupValidation['valid']) {
                $result['errors'][] = 'Grupo inválido: ' . ($groupValidation['error'] ?? 'Grupo não pôde ser validado');
            }
        } catch (Exception $e) {
            $result['errors'][] = 'Erro ao validar grupo: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Valida se o grupo existe e se o bot tem acesso
     *
     * @param string $token
     * @param string $groupId
     * @param array|null $botInfo Opcional: informações do bot já validadas
     * @return array
     */
    public function validateGroup(string $token, string $groupId, ?array $botInfo = null): array
    {
        try {
            // Obtém informações do chat/grupo
            $response = $this->http()->get("https://api.telegram.org/bot{$token}/getChat", [
                'chat_id' => $groupId
            ]);

            $responseData = $response->json() ?? [];

            if (!$response->successful()) {
                // Verifica se o erro é devido a migração de grupo para supergrupo
                $migration = $this->detectGroupMigration($responseData);

                if ($migration) {
                    return [
                        'valid' => false,
                        'error' => 'Grupo foi atualizado para supergrupo',
                        'migrated' => true,
                        'new_chat_id' => $migration['new_chat_id'],
                        'old_chat_id' => $groupId
                    ];
                }

                $errorMessage = $responseData['description'] ?? 'Erro ao acessar grupo';

                return [
                    'valid' => false,
                    'error' => $errorMessage
                ];
            }

            if (!($responseData['ok'] ?? false)) {
                // Verifica se o erro é devido a migração de grupo para supergrupo
                $migration = $this->detectGroupMigration($responseData);

                if ($migration) {
                    return [
                        'valid' => false,
                        'error' => 'Grupo foi atualizado para supergrupo',
                        'migrated' => true,
                        'new_chat_id' => $migration['new_chat_id'],
                        'old_chat_id' => $groupId
                    ];
                }

                return [
                    'valid' => false,
                    'error' => $responseData['description'] ?? 'Grupo não encontrado ou inacessível'
                ];
            }

            $data = $responseData;

            $chat = $data['result'];
            $chatType = $chat['type'] ?? '';

            // Verifica se é um grupo ou supergrupo
            if (!in_array($chatType, ['group', 'supergroup'])) {
                return [
                    'valid' => false,
                    'error' => "O ID informado não é de um grupo. Tipo encontrado: {$chatType}"
                ];
            }

            // Obtém ID do bot (usa botInfo se disponível, senão valida token)
            $botId = null;
            if ($botInfo && isset($botInfo['id'])) {
                $botId = $botInfo['id'];
            } else {
                try {
                    $botId = $this->getBotIdFromToken($token);
                } catch (Exception $e) {
                    return [
                        'valid' => false,
                        'error' => 'Não foi possível obter o ID do bot: ' . $e->getMessage()
                    ];
                }
            }

            // Verifica se o bot é membro do grupo
            $botMemberInfo = $this->getChatMember($token, $groupId, $botId);

            if (!$botMemberInfo['is_member']) {
                return [
                    'valid' => false,
                    'error' => 'O bot não é membro deste grupo. Adicione o bot ao grupo primeiro.',
                    'group_info' => [
                        'id' => $chat['id'],
                        'title' => $chat['title'] ?? 'Sem título',
                        'type' => $chatType
                    ]
                ];
            }

            // Verifica permissões do bot
            $hasAdminRights = $botMemberInfo['can_restrict_members'] ?? false;
            $canInviteUsers = $botMemberInfo['can_invite_users'] ?? false;

            return [
                'valid' => true,
                'group_info' => [
                    'id' => $chat['id'],
                    'title' => $chat['title'] ?? 'Sem título',
                    'type' => $chatType,
                    'username' => $chat['username'] ?? null,
                    'description' => $chat['description'] ?? null,
                    'member_count' => $chat['members_count'] ?? null,
                    'is_member' => true,
                    'has_admin_rights' => $hasAdminRights,
                    'can_invite_users' => $canInviteUsers,
                    'permissions' => [
                        'can_restrict_members' => $hasAdminRights,
                        'can_invite_users' => $canInviteUsers,
                        'can_delete_messages' => $botMemberInfo['can_delete_messages'] ?? false,
                        'can_pin_messages' => $botMemberInfo['can_pin_messages'] ?? false
                    ]
                ]
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => 'Erro ao validar grupo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém informações de um membro do chat
     *
     * @param string $token
     * @param string $groupId
     * @param int $userId
     * @return array
     */
    public function getChatMember(string $token, string $groupId, int $userId): array
    {
        try {
            $response = $this->http()->get("https://api.telegram.org/bot{$token}/getChatMember", [
                'chat_id' => $groupId,
                'user_id' => $userId
            ]);

            $responseData = $response->json() ?? [];

            if (!$response->successful() || !($responseData['ok'] ?? false)) {
                // Verifica se o erro é devido a migração de grupo para supergrupo
                $migration = $this->detectGroupMigration($responseData);

                if ($migration) {
                    // Retorna informação sobre migração para que o chamador possa atualizar
                    return [
                        'is_member' => false,
                        'status' => 'not_member',
                        'migrated' => true,
                        'new_chat_id' => $migration['new_chat_id'],
                        'old_chat_id' => $groupId
                    ];
                }

                return [
                    'is_member' => false,
                    'status' => 'not_member'
                ];
            }

            $member = $responseData['result'] ?? [];
            $status = $member['status'] ?? 'unknown';
            $isAdmin = in_array($status, ['administrator', 'creator']);

            // Para administradores, algumas permissões podem não estar explícitas mas estar disponíveis
            // Se for admin mas can_invite_users não estiver definido, assume true
            $canInviteUsers = $member['can_invite_users'] ?? ($isAdmin ? true : false);

            return [
                'is_member' => in_array($status, ['member', 'administrator', 'creator']),
                'status' => $status,
                'is_admin' => $isAdmin,
                'can_restrict_members' => $member['can_restrict_members'] ?? false,
                'can_invite_users' => $canInviteUsers,
                'can_delete_messages' => $member['can_delete_messages'] ?? false,
                'can_pin_messages' => $member['can_pin_messages'] ?? false,
                'raw_member_data' => $member // Para debug
            ];
        } catch (Exception $e) {
            return [
                'is_member' => false,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém o ID do bot a partir do token
     *
     * @param string $token
     * @return int
     */
    protected function getBotIdFromToken(string $token): int
    {
        $validation = $this->validateToken($token);
        if ($validation['valid'] && isset($validation['bot']['id'])) {
            return $validation['bot']['id'];
        }
        throw new Exception('Não foi possível obter o ID do bot');
    }

    /**
     * Obtém o link de convite de um grupo/canal
     * Tenta exportChatInviteLink primeiro, se falhar tenta createChatInviteLink
     *
     * @param string $token Token do bot
     * @param string $chatId ID do chat (grupo/canal)
     * @param int|null $botId ID do bot (opcional, será obtido do token se não fornecido)
     * @return array ['success' => bool, 'invite_link' => string|null, 'error' => string|null, 'details' => array]
     */
    public function getChatInviteLink(string $token, string $chatId, ?int $botId = null): array
    {
        try {
            // Normaliza o chat_id
            $normalizedChatId = $this->normalizeChatId($chatId);

            // Obtém o ID do bot se não fornecido
            if ($botId === null) {
                try {
                    $botId = $this->getBotIdFromToken($token);
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'invite_link' => null,
                        'error' => 'Não foi possível obter o ID do bot: ' . $e->getMessage(),
                        'details' => []
                    ];
                }
            }

            // Verifica se o bot é membro e tem permissões
            $botMemberInfo = $this->getChatMember($token, $normalizedChatId, $botId);

            // Verifica se o grupo foi migrado para supergrupo
            if (isset($botMemberInfo['migrated']) && $botMemberInfo['migrated'] === true) {
                $newChatId = $botMemberInfo['new_chat_id'] ?? null;

                if ($newChatId) {
                    LogFacade::info('🔄 Grupo migrado detectado em getChatInviteLink, usando novo chat_id', [
                        'old_chat_id' => $normalizedChatId,
                        'new_chat_id' => $newChatId
                    ]);

                    // Atualiza o chat_id e tenta novamente
                    $normalizedChatId = $newChatId;
                    $botMemberInfo = $this->getChatMember($token, $normalizedChatId, $botId);
                }
            }

            // Se o status retornado é 'member', tenta verificar novamente para garantir que não há cache
            // Às vezes a API pode retornar status desatualizado
            $initialStatus = $botMemberInfo['status'] ?? 'unknown';
            if ($initialStatus === 'member') {
                LogFacade::info('Status inicial é "member", verificando novamente para garantir precisão', [
                    'chat_id' => $normalizedChatId,
                    'bot_id' => $botId
                ]);

                // Aguarda um pouco e verifica novamente (pode ser um problema de sincronização)
                usleep(500000); // 0.5 segundos
                $botMemberInfoRetry = $this->getChatMember($token, $normalizedChatId, $botId);

                // Se o novo status for diferente (especialmente se for admin), usa o novo
                $retryStatus = $botMemberInfoRetry['status'] ?? 'unknown';
                if (in_array($retryStatus, ['administrator', 'creator']) && $retryStatus !== $initialStatus) {
                    LogFacade::info('Status atualizado após retry: de "member" para "' . $retryStatus . '"', [
                        'chat_id' => $normalizedChatId,
                        'bot_id' => $botId,
                        'old_status' => $initialStatus,
                        'new_status' => $retryStatus
                    ]);
                    $botMemberInfo = $botMemberInfoRetry;
                }
            }

            if (!$botMemberInfo['is_member']) {
                return [
                    'success' => false,
                    'invite_link' => null,
                    'error' => 'O bot não é membro deste grupo/canal. Adicione o bot primeiro.',
                    'details' => [
                        'status' => $botMemberInfo['status'] ?? 'unknown',
                        'is_member' => false
                    ]
                ];
            }

            $status = $botMemberInfo['status'] ?? 'unknown';
            $isAdmin = in_array($status, ['administrator', 'creator']);
            // Usa o valor do getChatMember que já trata o caso de admin sem can_invite_users explícito
            $canInviteUsers = $botMemberInfo['can_invite_users'] ?? ($isAdmin ? true : false);

            // Log para debug
            LogFacade::info('Verificando permissões do bot para obter link de convite', [
                'chat_id' => $normalizedChatId,
                'bot_id' => $botId,
                'status' => $status,
                'is_admin' => $isAdmin,
                'can_invite_users' => $canInviteUsers,
                'raw_status' => $botMemberInfo['raw_member_data']['status'] ?? null,
                'bot_member_info' => $botMemberInfo
            ]);

            // IMPORTANTE: Se for administrador, sempre tenta obter o link, mesmo que can_invite_users não esteja explícito
            // Isso porque alguns administradores podem ter permissões implícitas ou a API pode funcionar mesmo assim
            // Também tenta se for member mas tiver can_invite_users = true (caso raro mas possível)
            if (!$isAdmin && !$canInviteUsers) {
                // Se for member, ainda tenta obter o link pois pode ser que a API funcione
                // (alguns bots podem ter permissões especiais mesmo como member)
                if ($status === 'member') {
                    LogFacade::warning('Bot é member sem can_invite_users explícito, mas tentando obter link mesmo assim', [
                        'chat_id' => $normalizedChatId,
                        'bot_id' => $botId,
                        'status' => $status
                    ]);
                } else {
                    return [
                        'success' => false,
                        'invite_link' => null,
                        'error' => 'O bot precisa ser administrador ou ter permissão para convidar usuários. Status atual: ' . $status,
                        'details' => [
                            'status' => $status,
                            'is_admin' => $isAdmin,
                            'can_invite_users' => $canInviteUsers,
                            'bot_member_info' => $botMemberInfo
                        ]
                    ];
                }
            }

            // Se é administrador mas can_invite_users está explicitamente false, loga um aviso mas continua
            if ($isAdmin && $canInviteUsers === false) {
                LogFacade::warning('Bot é administrador mas can_invite_users está false. Tentando obter link mesmo assim.', [
                    'chat_id' => $normalizedChatId,
                    'bot_id' => $botId,
                    'status' => $status
                ]);
            }

            // Tenta exportChatInviteLink primeiro (retorna link existente se houver)
            try {
                $response = $this->http()->post("https://api.telegram.org/bot{$token}/exportChatInviteLink", [
                    'chat_id' => $normalizedChatId
                ]);

                $responseData = $response->json() ?? [];

                // Verifica se o erro é devido a migração de grupo para supergrupo
                if (!$response->successful() || !($responseData['ok'] ?? false)) {
                    $migration = $this->detectGroupMigration($responseData);

                    if ($migration) {
                        $newChatId = $migration['new_chat_id'];
                        LogFacade::info('🔄 Grupo migrado detectado em exportChatInviteLink, usando novo chat_id', [
                            'old_chat_id' => $normalizedChatId,
                            'new_chat_id' => $newChatId
                        ]);

                        // Atualiza o chat_id e tenta novamente
                        $normalizedChatId = $newChatId;
                        $response = $this->http()->post("https://api.telegram.org/bot{$token}/exportChatInviteLink", [
                            'chat_id' => $normalizedChatId
                        ]);
                        $responseData = $response->json() ?? [];
                    }
                }

                if ($response->successful() && ($responseData['ok'] ?? false) && isset($responseData['result'])) {
                    return [
                        'success' => true,
                        'invite_link' => $responseData['result'],
                        'error' => null,
                        'details' => [
                            'method' => 'exportChatInviteLink',
                            'status' => $status,
                            'is_admin' => $isAdmin
                        ]
                    ];
                }

                // Se exportChatInviteLink falhou, tenta createChatInviteLink
                $errorMessage = $responseData['description'] ?? 'Erro desconhecido';
                $errorCode = $responseData['error_code'] ?? null;

                // Se o bot é administrador mas exportChatInviteLink falhou, loga informação adicional
                if ($isAdmin) {
                    LogFacade::warning('exportChatInviteLink falhou para administrador, tentando createChatInviteLink', [
                        'chat_id' => $normalizedChatId,
                        'bot_id' => $botId,
                        'status' => $status,
                        'can_invite_users' => $canInviteUsers,
                        'error' => $errorMessage,
                        'error_code' => $errorCode
                    ]);
                } else {
                    LogFacade::info('exportChatInviteLink falhou, tentando createChatInviteLink', [
                        'chat_id' => $normalizedChatId,
                        'error' => $errorMessage
                    ]);
                }

            } catch (Exception $e) {
                LogFacade::info('exportChatInviteLink lançou exceção, tentando createChatInviteLink', [
                    'chat_id' => $normalizedChatId,
                    'error' => $e->getMessage()
                ]);
            }

            // Tenta createChatInviteLink (cria novo link)
            try {
                $response = $this->http()->post("https://api.telegram.org/bot{$token}/createChatInviteLink", [
                    'chat_id' => $normalizedChatId,
                    'creates_join_request' => false
                ]);

                $responseData = $response->json() ?? [];

                // Verifica se o erro é devido a migração de grupo para supergrupo
                if (!$response->successful() || !($responseData['ok'] ?? false)) {
                    $migration = $this->detectGroupMigration($responseData);

                    if ($migration) {
                        $newChatId = $migration['new_chat_id'];
                        LogFacade::info('🔄 Grupo migrado detectado em createChatInviteLink, usando novo chat_id', [
                            'old_chat_id' => $normalizedChatId,
                            'new_chat_id' => $newChatId
                        ]);

                        // Atualiza o chat_id e tenta novamente
                        $normalizedChatId = $newChatId;
                        $response = $this->http()->post("https://api.telegram.org/bot{$token}/createChatInviteLink", [
                            'chat_id' => $normalizedChatId,
                            'creates_join_request' => false
                        ]);
                        $responseData = $response->json() ?? [];
                    }
                }

                if ($response->successful() && ($responseData['ok'] ?? false) && isset($responseData['result']['invite_link'])) {
                    return [
                        'success' => true,
                        'invite_link' => $responseData['result']['invite_link'],
                        'error' => null,
                        'details' => [
                            'method' => 'createChatInviteLink',
                            'status' => $status,
                            'is_admin' => $isAdmin
                        ]
                    ];
                }

                $errorMessage = $responseData['description'] ?? 'Erro ao criar link de convite';
                $errorCode = $responseData['error_code'] ?? null;
                $errorDescription = $responseData['description'] ?? '';

                // Se o bot é administrador mas ainda assim falhou, fornece mensagem mais específica
                if ($isAdmin || $status === 'member') {
                    // Verifica se o erro é relacionado a permissões
                    $isPermissionError = stripos($errorDescription, 'not enough rights') !== false ||
                                        stripos($errorDescription, 'permission') !== false ||
                                        stripos($errorDescription, 'can_invite_users') !== false ||
                                        stripos($errorDescription, 'not an admin') !== false ||
                                        stripos($errorDescription, 'not administrator') !== false;

                    if ($isPermissionError) {
                        if ($isAdmin) {
                            $errorMessage = 'O bot é administrador, mas não tem a permissão "Convidar usuários" habilitada. ' .
                                           'Por favor, vá nas configurações do grupo no Telegram, edite as permissões do bot e ' .
                                           'habilite a permissão "Convidar usuários". Erro da API: ' . $errorDescription;
                        } else {
                            $errorMessage = 'O bot precisa ser administrador do grupo/canal para obter o link de convite. ' .
                                           'Status atual: ' . $status . '. Por favor, promova o bot a administrador no Telegram. ' .
                                           'Erro da API: ' . $errorDescription;
                        }
                    } else {
                        // Tenta uma última vez com getChat para verificar se o grupo existe e obter informações
                        try {
                            $chatInfo = $this->http()->get("https://api.telegram.org/bot{$token}/getChat", [
                                'chat_id' => $normalizedChatId
                            ]);

                            $chatData = $chatInfo->json() ?? [];
                            if ($chatInfo->successful() && ($chatData['ok'] ?? false)) {
                                LogFacade::info('Grupo existe e é acessível, mas não foi possível obter link', [
                                    'chat_id' => $normalizedChatId,
                                    'chat_type' => $chatData['result']['type'] ?? 'unknown',
                                    'error' => $errorDescription
                                ]);
                            }
                        } catch (Exception $e) {
                            // Ignora erro ao verificar chat
                        }

                        if ($isAdmin) {
                            $errorMessage = 'O bot é administrador, mas não conseguiu obter o link de convite. ' .
                                           'Verifique se o bot tem a permissão "Convidar usuários" habilitada nas configurações do grupo. ' .
                                           'Erro: ' . $errorDescription;
                        } else {
                            $errorMessage = 'Não foi possível obter o link de convite. ' .
                                           'Status do bot: ' . $status . '. ' .
                                           'Erro: ' . $errorDescription;
                        }
                    }
                }

                return [
                    'success' => false,
                    'invite_link' => null,
                    'error' => $errorMessage,
                    'details' => [
                        'status' => $status,
                        'is_admin' => $isAdmin,
                        'can_invite_users' => $canInviteUsers,
                        'error_code' => $errorCode ?? null,
                        'response' => $responseData
                    ]
                ];

            } catch (Exception $e) {
                return [
                    'success' => false,
                    'invite_link' => null,
                    'error' => 'Erro ao obter link de convite: ' . $e->getMessage(),
                    'details' => [
                        'status' => $status,
                        'is_admin' => $isAdmin,
                        'exception' => $e->getMessage()
                    ]
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'invite_link' => null,
                'error' => 'Erro ao processar solicitação: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * Normaliza o chat_id para o formato correto
     *
     * @param string $chatId
     * @return string
     */
    protected function normalizeChatId(string $chatId): string
    {
        // Remove espaços
        $chatId = trim($chatId);

        // Se começa com @, é um username (canal público)
        if (str_starts_with($chatId, '@')) {
            return ltrim($chatId, '@');
        }

        // Remove @ se houver no meio
        $chatId = str_replace('@', '', $chatId);

        // Se é numérico
        if (is_numeric($chatId)) {
            // Se já começa com -, retorna como está
            if (str_starts_with($chatId, '-')) {
                return $chatId;
            }

            // Se não começa com -, adiciona
            // Supergrupos geralmente começam com 100
            if (str_starts_with($chatId, '100')) {
                return '-' . $chatId;
            } else {
                // Grupo normal, adiciona apenas -
                return '-' . $chatId;
            }
        }

        // Se não é numérico e não é username, retorna como está
        return $chatId;
    }

    /**
     * Adiciona um usuário ao grupo
     *
     * @param string $token
     * @param string $groupId
     * @param int $userId
     * @return array
     */
    public function addUserToGroup(string $token, string $groupId, int $userId): array
    {
        try {
            $normalizedChatId = $this->normalizeChatId($groupId);

            // Primeiro, verifica se o usuário já é membro do grupo
            $memberInfo = $this->getChatMember($token, $normalizedChatId, $userId);

            if ($memberInfo['is_member'] ?? false) {
                return [
                    'success' => true,
                    'message' => 'Usuário já é membro do grupo',
                    'already_member' => true
                ];
            }

            // Verifica se o usuário é o dono do grupo (não pode ser adicionado/removido)
            if (($memberInfo['status'] ?? '') === 'creator') {
                return [
                    'success' => true,
                    'message' => 'Usuário já é o dono do grupo',
                    'already_member' => true
                ];
            }

            // Tenta adicionar usando inviteChatMember (requer que o bot seja admin com permissão can_invite_users)
            $response = $this->http()->post("https://api.telegram.org/bot{$token}/inviteChatMember", [
                'chat_id' => $normalizedChatId,
                'user_id' => $userId
            ]);

            if (!$response->successful() || !$response->json()['ok']) {
                $errorData = $response->json();
                $errorMessage = $errorData['description'] ?? 'Erro ao adicionar usuário ao grupo';

                // Se o erro for sobre não poder remover o dono, significa que o usuário já é o dono
                if (str_contains($errorMessage, "can't remove chat owner") ||
                    str_contains($errorMessage, "chat owner")) {
                    return [
                        'success' => true,
                        'message' => 'Usuário já é o dono do grupo',
                        'already_member' => true
                    ];
                }

                // Se inviteChatMember falhar, tenta unbanChatMember (para desbanir se estiver banido)
                $unbanResponse = $this->http()->post("https://api.telegram.org/bot{$token}/unbanChatMember", [
                    'chat_id' => $normalizedChatId,
                    'user_id' => $userId,
                    'only_if_banned' => true // Só desbane se estiver banido
                ]);

                if (!$unbanResponse->successful() || !$unbanResponse->json()['ok']) {
                    return [
                        'success' => false,
                        'error' => $errorMessage
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'Usuário desbanido e adicionado ao grupo com sucesso'
                ];
            }

            return [
                'success' => true,
                'message' => 'Usuário adicionado ao grupo com sucesso'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao adicionar usuário: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove um usuário do grupo
     *
     * @param string $token
     * @param string $groupId
     * @param int $userId
     * @return array
     */
    public function removeUserFromGroup(string $token, string $groupId, int $userId): array
    {
        try {
            $normalizedChatId = $this->normalizeChatId($groupId);

            // Primeiro, verifica se o usuário é membro do grupo
            $memberInfo = $this->getChatMember($token, $normalizedChatId, $userId);

            if (!($memberInfo['is_member'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'Usuário já não é membro do grupo',
                    'already_removed' => true
                ];
            }

            // Verifica se o usuário é o dono do grupo (não pode ser removido)
            if (($memberInfo['status'] ?? '') === 'creator') {
                return [
                    'success' => false,
                    'error' => 'Não é possível remover o dono do grupo'
                ];
            }

            $response = $this->http()->post("https://api.telegram.org/bot{$token}/banChatMember", [
                'chat_id' => $normalizedChatId,
                'user_id' => $userId,
                'revoke_messages' => false
            ]);

            if (!$response->successful() || !$response->json()['ok']) {
                $errorData = $response->json();
                $errorMessage = $errorData['description'] ?? 'Erro ao remover usuário do grupo';

                // Se o erro for sobre não poder remover o dono
                if (str_contains($errorMessage, "can't remove chat owner") ||
                    str_contains($errorMessage, "chat owner")) {
                    return [
                        'success' => false,
                        'error' => 'Não é possível remover o dono do grupo'
                    ];
                }

                return [
                    'success' => false,
                    'error' => $errorMessage
                ];
            }

            return [
                'success' => true,
                'message' => 'Usuário removido do grupo com sucesso'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao remover usuário: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Processa uma atualização do Telegram
     *
     * @param Bot $bot
     * @param array $update
     * @return void
     */
    public function processUpdate(Bot $bot, array $update): void
    {
        try {
            // Log inicial da atualização recebida
            $updateType = null;
            if (isset($update['message'])) {
                $updateType = 'message';
                $text = $update['message']['text'] ?? null;
                $chatType = $update['message']['chat']['type'] ?? 'unknown';
                $this->logBotAction($bot, "Atualização recebida: message", 'info', [
                    'chat_type' => $chatType,
                    'text' => $text ? substr($text, 0, 50) : null,
                    'has_entities' => isset($update['message']['entities'])
                ]);
            } elseif (isset($update['edited_message'])) {
                $updateType = 'edited_message';
            } elseif (isset($update['channel_post'])) {
                $updateType = 'channel_post';
            } elseif (isset($update['callback_query'])) {
                $updateType = 'callback_query';
            } elseif (isset($update['inline_query'])) {
                $updateType = 'inline_query';
            }

            // Processa mensagem (chat privado ou grupo)
            if (isset($update['message'])) {
                $this->processMessage($bot, $update['message']);
            }

            // Processa mensagem editada
            if (isset($update['edited_message'])) {
                $this->processMessage($bot, $update['edited_message'], true);
            }

            // Processa mensagem de canal
            if (isset($update['channel_post'])) {
                $this->processChannelPost($bot, $update['channel_post']);
            }

            // Processa callback query (botões inline)
            if (isset($update['callback_query'])) {
                $this->processCallbackQuery($bot, $update['callback_query']);
            }

            // Processa inline query
            if (isset($update['inline_query'])) {
                $this->processInlineQuery($bot, $update['inline_query']);
            }

            // Processa my_chat_member (quando o status do bot em um chat muda)
            if (isset($update['my_chat_member'])) {
                $this->processMyChatMember($bot, $update['my_chat_member']);
            }
        } catch (\Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar atualização: ' . $e->getMessage(), 'error', [
                'update' => $update,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Processa uma mensagem recebida
     *
     * @param Bot $bot
     * @param array $message
     * @param bool $isEdited
     * @return void
     */
    protected function processMessage(Bot $bot, array $message, bool $isEdited = false): void
    {
        $from = $message['from'] ?? null;
        $chat = $message['chat'] ?? null;
        $text = $message['text'] ?? null;

        if (!$from || !$chat) {
            return;
        }

        $chatType = $chat['type'] ?? 'private';
        $chatId = $chat['id'];

        // Log inicial para debug
        $this->logBotAction($bot, "Mensagem recebida", 'info', [
            'chat_id' => $chatId,
            'chat_type' => $chatType,
            'user_id' => $from['id'],
            'text' => $text ? substr($text, 0, 100) : null,
            'has_entities' => isset($message['entities']),
            'entities_count' => isset($message['entities']) ? count($message['entities']) : 0
        ]);

        // Salva ou atualiza contato (apenas para chats privados)
        // IMPORTANTE: Solicitação de dados pessoais (email, telefone, idioma) só deve acontecer em chats privados
        if ($chatType === 'private') {
            $contact = $this->saveOrUpdateContact($bot, $from);

            // Processa contato compartilhado (telefone compartilhado via botão)
            // Só processa em chats privados, não em grupos
            if (isset($message['contact'])) {
                $this->processSharedContact($bot, $chatId, $from, $message['contact'], $contact, $chatType);
                return;
            }
        } else {
            // Se não é chat privado, ignora qualquer tentativa de compartilhar contato
            if (isset($message['contact'])) {
                $this->logBotAction($bot, "Tentativa de compartilhar contato em grupo ignorada", 'info', [
                    'chat_id' => $chatId,
                    'chat_type' => $chatType,
                    'user_id' => $from['id']
                ]);
                return;
            }
        }

        // Verifica se é um grupo ou supergrupo
        if (in_array($chatType, ['group', 'supergroup'])) {
            $this->processGroupMessage($bot, $message, $chat);
            return;
        }

        // Em chats privados, qualquer texto que comece com / é considerado comando
        // O Telegram pode não enviar a entity bot_command em conversas existentes
        $isCommand = false;
        $command = null;

        // Primeiro, tenta usar entities se disponível (mais preciso)
        if (isset($message['entities'])) {
            foreach ($message['entities'] as $entity) {
                if (($entity['type'] ?? '') === 'bot_command') {
                    $isCommand = true;
                    // Extrai o comando do texto usando offset e length
                    $offset = $entity['offset'] ?? 0;
                    $length = $entity['length'] ?? 0;
                    if ($text && $offset >= 0 && $length > 0) {
                        $command = substr($text, $offset, $length);
                    }
                    break;
                }
            }
        }

        // Se não encontrou command entity, verifica se começa com / (fallback)
        // Em chats privados, sempre processa comandos que começam com /
        if (!$isCommand && $text) {
            $textTrimmed = trim($text);
            if (str_starts_with($textTrimmed, '/')) {
                $isCommand = true;
                $commandParts = explode(' ', $textTrimmed);
                $command = $commandParts[0];
                $this->logBotAction($bot, "Comando detectado por fallback (sem entity): {$command}", 'info', [
                    'chat_id' => $chatId,
                    'user_id' => $from['id'],
                    'original_text' => $text,
                    'text_trimmed' => $textTrimmed
                ]);
            }
        }

        // Processa comandos (em chats privados, sempre processa qualquer comando)
        if ($isCommand && $command) {
            // Remove @username do comando se houver
            $command = preg_replace('/@\w+/', '', $command);
            $this->logBotAction($bot, "Comando detectado em chat privado: {$command}", 'info', [
                'chat_id' => $chatId,
                'user_id' => $from['id'],
                'original_text' => $text,
                'has_entity' => isset($message['entities']),
                'chat_type' => $chatType,
                'command_before_clean' => $command
            ]);
            $this->processCommand($bot, $chatId, $from, $command, $chatType);
        } else {
            // Log quando não é comando para debug
            if ($text) {
                $this->logBotAction($bot, "Mensagem de texto (não é comando)", 'info', [
                    'chat_id' => $chatId,
                    'user_id' => $from['id'],
                    'text' => substr($text, 0, 50),
                    'starts_with_slash' => str_starts_with(trim($text), '/'),
                    'has_entities' => isset($message['entities'])
                ]);
            }
            // Processa mensagem de texto normal
            $this->processTextMessage($bot, $chatId, $from, $text);
        }
    }

    /**
     * Processa mensagem em grupo ou supergrupo
     *
     * @param Bot $bot
     * @param array $message
     * @param array $chat
     * @return void
     */
    protected function processGroupMessage(Bot $bot, array $message, array $chat): void
    {
        $text = $message['text'] ?? null;
        $from = $message['from'] ?? null;
        $chatId = $chat['id'];
        $chatTitle = $chat['title'] ?? 'Grupo';

        // Salva contato mesmo em grupos (para estatísticas)
        // E verifica se é membro do grupo para atualizar status
        if ($from) {
            $contact = $this->saveOrUpdateContact($bot, $from);

            // Verifica se o contato é membro do grupo e atualiza status
            if (!empty($bot->telegram_group_id)) {
                try {
                    $memberInfo = $this->getChatMember($bot->token, $bot->telegram_group_id, $contact->telegram_id);
                    if ($memberInfo['is_member'] ?? false) {
                        $contact->update(['telegram_status' => 'active']);
                    }
                } catch (Exception $e) {
                    // Ignora erro, mantém status atual
                }
            }
        }

        // Processa comandos em grupos
        // IMPORTANTE: Em grupos, o Telegram só envia mensagens de comandos para o bot se:
        // 1. O bot foi mencionado explicitamente no comando (ex: /start@botname), OU
        // 2. O bot tem can_read_all_group_messages = true (privacidade desabilitada)
        // Se o bot recebeu a mensagem, significa que uma dessas condições foi atendida.
        if ($text && str_starts_with(trim($text), '/')) {
            // Extrai o comando do texto
            $commandParts = explode(' ', trim($text));
            $command = $commandParts[0];

            // Log detalhado para debug
            $this->logBotAction($bot, "Processando comando em grupo", 'info', [
                'chat_id' => $chatId,
                'user_id' => $from['id'] ?? null,
                'command' => $command,
                'full_text' => substr($text, 0, 100),
                'has_entities' => isset($message['entities']),
                'entities' => $message['entities'] ?? []
            ]);

            // Obtém o username do bot (cache para evitar múltiplas chamadas)
            $botUsername = null;
            $botIdFromToken = null;
            try {
                $botInfo = $this->validateToken($bot->token);
                if ($botInfo['valid'] && isset($botInfo['bot']['username'])) {
                    $botUsername = $botInfo['bot']['username'];
                    $botIdFromToken = $botInfo['bot']['id'] ?? null;
                }
            } catch (Exception $e) {
                LogFacade::warning('Não foi possível obter username do bot', [
                    'bot_id' => $bot->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Verifica se o comando menciona algum bot pelo texto
            $commandMentionsOurBot = false;
            $commandMentionsOtherBot = false;

            if (str_contains($command, '@')) {
                if ($botUsername && str_contains($command, '@' . $botUsername)) {
                    $commandMentionsOurBot = true;
                } else {
                    // Menciona outro bot
                    $commandMentionsOtherBot = true;
                }
            }

            // Verifica entities para detecção mais precisa
            $entityMentionsOurBot = false;
            $entityMentionsOtherBot = false;
            $hasCommandEntity = false;

            if (isset($message['entities']) && is_array($message['entities'])) {
                foreach ($message['entities'] as $entity) {
                    if (($entity['type'] ?? '') === 'bot_command') {
                        $hasCommandEntity = true;

                        // Se a entity tem 'user', significa que menciona um bot específico
                        // Isso é a forma mais confiável de detectar qual bot foi mencionado
                        if (isset($entity['user']) && isset($entity['user']['id'])) {
                            $mentionedBotId = $entity['user']['id'];
                            if ($botIdFromToken) {
                                if ($mentionedBotId == $botIdFromToken) {
                                    $entityMentionsOurBot = true;
                                    $this->logBotAction($bot, "Entity indica menção ao nosso bot via user.id", 'info', [
                                        'mentioned_bot_id' => $mentionedBotId,
                                        'our_bot_id' => $botIdFromToken
                                    ]);
                                    break;
                                } else {
                                    $entityMentionsOtherBot = true;
                                    $this->logBotAction($bot, "Entity indica menção a outro bot", 'info', [
                                        'mentioned_bot_id' => $mentionedBotId,
                                        'our_bot_id' => $botIdFromToken
                                    ]);
                                    break;
                                }
                            }
                        }

                        // Se não tem 'user', verifica pelo texto da entity
                        // Isso é um fallback para casos onde a entity não tem user.id
                        if (!$entityMentionsOurBot && !$entityMentionsOtherBot) {
                            $entityOffset = $entity['offset'] ?? 0;
                            $entityLength = $entity['length'] ?? 0;

                            if ($entityOffset >= 0 && $entityLength > 0 && $entityOffset + $entityLength <= strlen($text)) {
                                $entityText = substr($text, $entityOffset, $entityLength);

                                if (str_contains($entityText, '@')) {
                                    if ($botUsername && str_contains($entityText, '@' . $botUsername)) {
                                        $entityMentionsOurBot = true;
                                        $this->logBotAction($bot, "Entity indica menção ao nosso bot via texto", 'info', [
                                            'entity_text' => $entityText,
                                            'bot_username' => $botUsername
                                        ]);
                                        break;
                                    } else {
                                        $entityMentionsOtherBot = true;
                                        $this->logBotAction($bot, "Entity indica menção a outro bot via texto", 'info', [
                                            'entity_text' => $entityText
                                        ]);
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Se não encontrou entity de comando, mas o texto começa com /,
            // significa que é um comando genérico (sem menção a bot específico)
            if (!$hasCommandEntity && str_starts_with(trim($text), '/')) {
                $this->logBotAction($bot, "Comando sem entity de bot_command (comando genérico)", 'info', [
                    'command' => $command
                ]);
            }

            // Decide se deve processar o comando
            // REGRA PRINCIPAL: Se o bot recebeu a mensagem de comando em um grupo, significa que:
            // 1. O bot foi mencionado explicitamente (ex: /start@botname), OU
            // 2. O bot tem can_read_all_group_messages = true (privacidade desabilitada)
            // Em ambos os casos, devemos processar o comando, EXCETO se mencionar outro bot
            $shouldProcess = false;
            $processReason = '';

            // Caso 1: Comando menciona nosso bot explicitamente (via texto ou entity)
            if ($commandMentionsOurBot || $entityMentionsOurBot) {
                $shouldProcess = true;
                $processReason = 'menciona_nosso_bot';
            }
            // Caso 2: Comando não menciona nenhum bot (genérico como /start)
            // Se o bot recebeu a mensagem, significa que tem permissão para ler todas as mensagens
            // IMPORTANTE: Se não há entity de comando, também processa (comando genérico)
            elseif (!$commandMentionsOtherBot && !$entityMentionsOtherBot) {
                $shouldProcess = true;
                $processReason = 'comando_generico_bot_tem_permissao';
            }
            // Caso 3: Comando menciona outro bot - não processa
            else {
                $processReason = 'menciona_outro_bot';
            }

            // Log da decisão
            $this->logBotAction($bot, "Decisão de processamento: {$processReason}", 'info', [
                'chat_id' => $chatId,
                'user_id' => $from['id'] ?? null,
                'command' => $command,
                'should_process' => $shouldProcess,
                'command_mentions_our_bot' => $commandMentionsOurBot,
                'command_mentions_other_bot' => $commandMentionsOtherBot,
                'entity_mentions_our_bot' => $entityMentionsOurBot,
                'entity_mentions_other_bot' => $entityMentionsOtherBot,
                'bot_username' => $botUsername
            ]);

            if ($shouldProcess) {
                // Remove @username do comando antes de processar
                $cleanCommand = preg_replace('/@\w+/', '', $command);
                $this->logBotAction($bot, "Processando comando limpo: {$cleanCommand}", 'info', [
                    'chat_id' => $chatId,
                    'user_id' => $from['id'] ?? null,
                    'original_command' => $command,
                    'clean_command' => $cleanCommand
                ]);
                $this->processCommand($bot, $chatId, $from, $cleanCommand);
            }
        }

        $this->logBotAction($bot, "Mensagem em grupo processada: {$chatTitle}", 'info', [
            'chat_type' => $chat['type'],
            'chat_id' => $chatId
        ]);
    }

    /**
     * Processa mensagem de canal
     *
     * @param Bot $bot
     * @param array $channelPost
     * @return void
     */
    protected function processChannelPost(Bot $bot, array $channelPost): void
    {
        $chat = $channelPost['chat'] ?? null;
        $text = $channelPost['text'] ?? null;

        if (!$chat) {
            return;
        }

        $chatId = $chat['id'];
        $chatTitle = $chat['title'] ?? 'Canal';

        $this->logBotAction($bot, "Post em canal processado: {$chatTitle}", 'info', [
            'chat_id' => $chatId,
            'text' => substr($text ?? '', 0, 100)
        ]);
    }

    /**
     * Processa inline query
     *
     * @param Bot $bot
     * @param array $inlineQuery
     * @return void
     */
    protected function processInlineQuery(Bot $bot, array $inlineQuery): void
    {
        $queryId = $inlineQuery['id'] ?? null;
        $from = $inlineQuery['from'] ?? null;
        $query = $inlineQuery['query'] ?? '';

        if (!$queryId || !$from) {
            return;
        }

        // Salva contato
        $this->saveOrUpdateContact($bot, $from);

        // Por padrão, responde com resultados vazios
        // Pode ser customizado para retornar resultados específicos
        try {
            $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerInlineQuery", [
                'inline_query_id' => $queryId,
                'results' => json_encode([])
            ]);
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar inline query: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Processa um comando
     *
     * @param Bot $bot
     * @param int $chatId
     * @param array $from
     * @param string $command
     * @return void
     */
    protected function processCommand(Bot $bot, int $chatId, array $from, string $command, string $chatType = 'private'): void
    {
        $command = trim($command);

        // Remove @username se houver no comando
        $command = preg_replace('/@\w+/', '', $command);

        // Remove a barra para obter o nome do comando
        $commandName = ltrim($command, '/');
        $commandName = trim($commandName);

        // Normaliza para minúsculas para comparação
        $commandLower = strtolower($command);
        $commandNameLower = strtolower($commandName);

        // Log para debug
        $this->logBotAction($bot, "Processando comando: '{$command}' -> nome: '{$commandName}'", 'info', [
            'chat_id' => $chatId,
            'user_id' => $from['id'],
            'command_original' => $command,
            'command_name' => $commandName
        ]);

        // Busca ou cria contato para registrar ações
        $contact = $this->saveOrUpdateContact($bot, $from);
        $actionService = new ContactActionService();

        // Comandos padrão do sistema (verifica múltiplos formatos)
        if ($commandLower === '/start' || $commandNameLower === 'start' ||
            $command === '/start' || $command === 'start') {
            $this->logBotAction($bot, "Comando /start detectado, executando...", 'info');
            $actionService->logCommand($bot, $contact, 'start', [
                'chat_id' => $chatId,
                'command' => $commandName
            ]);
            $this->handleStartCommand($bot, $chatId, $from, $chatType);
            return;
        }

        if ($commandLower === '/help' || $commandLower === '/comandos' ||
            $commandNameLower === 'help' || $commandNameLower === 'comandos') {
            $actionService->logCommand($bot, $contact, 'help', [
                'chat_id' => $chatId,
                'command' => $commandName
            ]);
            $this->handleHelpCommand($bot, $chatId);
            return;
        }

        if ($commandLower === '/planos' || $commandNameLower === 'planos') {
            $actionService->logCommand($bot, $contact, 'planos', [
                'chat_id' => $chatId,
                'command' => $commandName
            ]);
            $this->handlePlansCommand($bot, $chatId, $from);
            return;
        }

        if ($commandLower === '/meuplano' || $commandNameLower === 'meuplano' ||
            $commandLower === '/status' || $commandNameLower === 'status') {
            $actionService->logCommand($bot, $contact, 'meuplano', [
                'chat_id' => $chatId,
                'command' => $commandName
            ]);
            $this->handleMyPlanCommand($bot, $chatId, $from);
            return;
        }

        // Busca comandos personalizados do bot
        $customCommand = BotCommand::where('bot_id', $bot->id)
            ->where('command', $commandName)
            ->where('active', true)
            ->first();

        if ($customCommand) {
            // Incrementa contador de uso
            $customCommand->incrementUsage();

            // Registra ação
            $actionService->logCommand($bot, $contact, $commandName, [
                'chat_id' => $chatId,
                'command' => $commandName,
                'command_id' => $customCommand->id,
                'is_custom' => true
            ]);

            // Envia resposta do comando personalizado
            $this->sendMessage($bot, $chatId, $customCommand->response);

            $this->logBotAction($bot, "Comando personalizado executado: {$command}", 'info', [
                'command_id' => $customCommand->id
            ]);
        } else {
            // Comando não encontrado
            $actionService->logCommand($bot, $contact, 'unknown', [
                'chat_id' => $chatId,
                'command' => $commandName,
                'error' => 'Comando não reconhecido'
            ]);
            $this->sendMessage($bot, $chatId, "Comando não reconhecido. Use /help para ver os comandos disponíveis.");
        }
    }


    /**
     * Processa comando /start
     *
     * @param Bot $bot
     * @param int $chatId
     * @param array $from
     * @return void
     */
    protected function handleStartCommand(Bot $bot, int $chatId, array $from, string $chatType = 'private'): void
    {
        try {
            $this->logBotAction($bot, "Iniciando processamento do comando /start", 'info', [
                'chat_id' => $chatId,
                'user_id' => $from['id'],
                'chat_type' => $chatType
            ]);

            // IMPORTANTE: Verifica se é um chat privado antes de solicitar dados
            // Comandos em grupos não devem solicitar dados pessoais
            $isPrivateChat = ($chatType === 'private');

            // Busca ou cria contato (apenas para chats privados)
            $contact = null;
            if ($isPrivateChat) {
                $contact = $this->saveOrUpdateContact($bot, $from);

                // Recarrega o contato para garantir que temos os dados mais recentes do banco
                $contact->refresh();
            }

            // Envia mensagem superior (se configurada)
            if ($bot->top_message) {
                $this->logBotAction($bot, "Enviando mensagem superior", 'info');
                $this->sendMessage($bot, $chatId, $bot->top_message);
            }

            // Envia mídias configuradas
            $this->logBotAction($bot, "Enviando mídias", 'info');
            $this->sendMedia($bot, $chatId);

            // Envia mensagem inicial
            $message = $bot->initial_message ?? 'Bem-vindo!';

            // Monta o teclado com botões de redirecionamento e botão de ativação
            $keyboardButtons = [];

            // Adiciona botão de ativação se configurado
            if ($bot->activate_cta && $bot->button_message) {
                $keyboardButtons[] = [
                    [
                        'text' => $bot->button_message,
                        'callback_data' => 'activate'
                    ]
                ];
            }

            // Busca botões de redirecionamento do bot
            $redirectButtons = \App\Models\RedirectButton::where('bot_id', $bot->id)
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            // Adiciona botões de redirecionamento ao teclado
            if ($redirectButtons->isNotEmpty()) {
                $redirectRow = [];
                foreach ($redirectButtons as $redirectButton) {
                    $redirectRow[] = [
                        'text' => $redirectButton->title,
                        'url' => $redirectButton->link
                    ];
                }
                // Adiciona os botões de redirecionamento em uma linha
                // Se houver mais de 2 botões, divide em múltiplas linhas
                if (count($redirectRow) <= 2) {
                    $keyboardButtons[] = $redirectRow;
                } else {
                    // Divide em linhas de 2 botões cada
                    foreach (array_chunk($redirectRow, 2) as $chunk) {
                        $keyboardButtons[] = $chunk;
                    }
                }
            }

            $keyboard = null;
            if (!empty($keyboardButtons)) {
                $keyboard = [
                    'inline_keyboard' => $keyboardButtons
                ];
            }

            $this->logBotAction($bot, "Enviando mensagem inicial", 'info', [
                'has_activate_button' => ($bot->activate_cta && $bot->button_message),
                'redirect_buttons_count' => $redirectButtons->count()
            ]);
            $this->sendMessage($bot, $chatId, $message, $keyboard);

            // IMPORTANTE: Só solicita dados pessoais em chats privados
            // Em grupos, não deve solicitar email, telefone ou idioma
            if ($isPrivateChat && $contact) {
                // Verifica se todos os dados necessários foram coletados
                $needsEmail = $bot->request_email && !$contact->email;
                $needsPhone = $bot->request_phone && !$contact->phone;
                $needsLanguage = $bot->request_language && !$contact->language;

                // Se o bot está configurado para solicitar dados, solicita após mensagem inicial
                // Verifica na ordem: email -> telefone -> idioma
                if ($needsEmail) {
                    $this->logBotAction($bot, "Solicitando email", 'info');
                    $this->sendMessage($bot, $chatId, '📧 Por favor, envie seu email:');
                } elseif ($needsPhone) {
                    // Usa botão nativo do Telegram para solicitar telefone
                    $this->logBotAction($bot, "Solicitando telefone", 'info');
                    $phoneKeyboard = [
                        'keyboard' => [[
                            [
                                'text' => '📱 Compartilhar meu telefone',
                                'request_contact' => true
                            ]
                        ]],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ];
                    $this->sendMessage($bot, $chatId, '📱 Por favor, compartilhe seu número de telefone ou envie o número:', $phoneKeyboard);
                } elseif ($needsLanguage) {
                    $this->logBotAction($bot, "Solicitando idioma", 'info');
                    $this->sendMessage($bot, $chatId, '🌐 Por favor, escolha um idioma (pt, en, es, fr):');
                } else {
                    // Todos os dados foram coletados, sempre exibe os planos disponíveis
                    $this->logBotAction($bot, "Exibindo planos disponíveis após /start", 'info');
                    $this->handlePlansCommand($bot, $chatId, $from);
                }
            } else {
                // Se não é chat privado ou não tem contato, exibe planos diretamente
                $this->logBotAction($bot, "Exibindo planos disponíveis após /start", 'info');
                $this->handlePlansCommand($bot, $chatId, $from);
            }

            $this->logBotAction($bot, "Comando /start processado com sucesso para chat {$chatId}", 'info');
        } catch (\Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar /start: ' . $e->getMessage(), 'error', [
                'chat_id' => $chatId,
                'user_id' => $from['id'] ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            // Tenta enviar mensagem de erro ao usuário
            try {
                $this->sendMessage($bot, $chatId, 'Desculpe, ocorreu um erro ao processar seu comando. Por favor, tente novamente.');
            } catch (\Exception $sendError) {
                // Ignora erro ao enviar mensagem de erro
            }
        }
    }

    /**
     * Processa comando /help
     *
     * @param Bot $bot
     * @param int $chatId
     * @return void
     */
    protected function handleHelpCommand(Bot $bot, int $chatId): void
    {
        try {
            $helpText = "📋 <b>Comandos disponíveis:</b>\n\n";
            $helpText .= "/start - Iniciar conversa com o bot\n";
            $helpText .= "/help - Ver esta mensagem de ajuda\n";
            $helpText .= "/comandos - Listar comandos disponíveis\n";
            $helpText .= "/planos - Ver planos de pagamento disponíveis\n";
            $helpText .= "/meuplano - Verificar status do meu plano atual\n\n";

            // Busca comandos personalizados ativos
            $customCommands = BotCommand::where('bot_id', $bot->id)
                ->where('active', true)
                ->orderBy('command')
                ->get();

            if ($customCommands->isNotEmpty()) {
                $helpText .= "<b>Comandos personalizados:</b>\n";
                foreach ($customCommands as $cmd) {
                    $description = $cmd->description ? " - {$cmd->description}" : '';
                    $helpText .= "/{$cmd->command}{$description}\n";
                }
            }

            $this->sendMessage($bot, $chatId, $helpText);
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar /help: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Processa comando /planos
     *
     * @param Bot $bot
     * @param int $chatId
     * @param array $from
     * @return void
     */
    protected function handlePlansCommand(Bot $bot, int $chatId, array $from): void
    {
        try {
            $this->logBotAction($bot, "Comando /planos detectado", 'info', [
                'chat_id' => $chatId,
                'user_id' => $from['id']
            ]);

            // Busca planos ativos do bot
            $paymentPlans = \App\Models\PaymentPlan::where('bot_id', $bot->id)
                ->where('active', true)
                ->orderBy('price', 'asc')
                ->get();

            if ($paymentPlans->isEmpty()) {
                $this->sendMessage($bot, $chatId, '📋 Não há planos de pagamento disponíveis no momento.');
                return;
            }

            $message = "💳 <b>Planos Disponíveis:</b>\n\n";

            $keyboardButtons = [];
            foreach ($paymentPlans as $plan) {
                $price = number_format($plan->price, 2, ',', '.');
                $message .= "📦 <b>{$plan->title}</b>\n";
                $message .= "💰 R$ {$price}\n";

                if ($plan->message) {
                    $message .= "📝 " . substr($plan->message, 0, 100) . "\n";
                }

                $message .= "\n";

                // Adiciona botão para cada plano
                $keyboardButtons[] = [[
                    'text' => "📦 {$plan->title} - R$ {$price}",
                    'callback_data' => "plan_{$plan->id}"
                ]];
            }

            $keyboard = [
                'inline_keyboard' => $keyboardButtons
            ];

            $this->sendMessage($bot, $chatId, $message, $keyboard);
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar /planos: ' . $e->getMessage(), 'error', [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendMessage($bot, $chatId, 'Desculpe, ocorreu um erro ao carregar os planos. Por favor, tente novamente.');
        }
    }

    /**
     * Processa comando /meuplano
     */
    protected function handleMyPlanCommand(Bot $bot, int $chatId, array $from): void
    {
        try {
            $contact = Contact::where('bot_id', $bot->id)
                ->where('telegram_id', $from['id'])
                ->first();

            if (!$contact) {
                $this->sendMessage($bot, $chatId, 'Você ainda não possui cadastro. Use /start para iniciar.');
                return;
            }

            // Busca a última transação aprovada
            $lastApprovedTransaction = \App\Models\Transaction::where('contact_id', $contact->id)
                ->whereIn('status', ['approved', 'paid', 'completed'])
                ->orderBy('created_at', 'desc')
                ->with(['paymentPlan', 'paymentCycle'])
                ->first();

            if ($lastApprovedTransaction && $lastApprovedTransaction->paymentCycle) {
                $expiresAt = \Carbon\Carbon::parse($lastApprovedTransaction->created_at)
                    ->addDays($lastApprovedTransaction->paymentCycle->days);

                $daysRemaining = (int) ceil(now()->diffInDays($expiresAt, false));
                $paymentPlan = $lastApprovedTransaction->paymentPlan;

                if ($daysRemaining >= 0) {
                    $message = "✅ <b>Seu Plano Atual:</b>\n\n";
                    $message .= "📦 <b>Plano:</b> " . ($paymentPlan->title ?? 'N/A') . "\n";
                    $message .= "📅 <b>Expira em:</b> " . $expiresAt->format('d/m/Y') . "\n";
                    $message .= "⏳ <b>Dias restantes:</b> {$daysRemaining} dia(s)\n\n";
                    $message .= "Para renovar ou alterar, use /planos";
                } else {
                    $message = "❌ <b>Seu plano expirou!</b>\n\n";
                    $message .= "📦 <b>Último Plano:</b> " . ($paymentPlan->title ?? 'N/A') . "\n";
                    $message .= "📅 <b>Expirou em:</b> " . $expiresAt->format('d/m/Y') . "\n\n";
                    $message .= "Use /planos para renovar seu acesso.";
                }
            } else {
                $message = "❌ <b>Você não possui nenhum plano ativo.</b>\n\n";
                $message .= "Use /planos para ver as opções disponíveis e assinar.";
            }

            $this->sendMessage($bot, $chatId, $message);

        } catch (\Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar /meuplano: ' . $e->getMessage(), 'error');
            $this->sendMessage($bot, $chatId, 'Erro ao verificar seu plano. Tente novamente.');
        }
    }

    /**
     * Configura o menu fixo de comandos no Telegram
     * O menu fixo exibirá todos os comandos registrados, incluindo /planos
     *
     * @param Bot $bot
     * @return void
     */
    protected function setPlansMenuButton(Bot $bot): void
    {
        try {
            // Configura o menu button para exibir os comandos disponíveis
            // Isso faz com que o botão "Menu" no chat mostre todos os comandos registrados
            // O Laravel HTTP client já faz o JSON encoding automaticamente quando usamos asJson()

            // Configura o menu button globalmente (para todos os chats privados)
            // O tipo 'commands' faz com que o botão Menu mostre os comandos registrados via setMyCommands
            $response = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/setChatMenuButton", [
                    'menu_button' => [
                        'type' => 'commands'
                    ]
                ]);

            if ($response->successful() && $response->json()['ok']) {
                $this->logBotAction($bot, 'Menu de comandos configurado com sucesso', 'info');
            } else {
                $error = $response->json()['description'] ?? 'Erro desconhecido';
                $this->logBotAction($bot, 'Erro ao configurar menu de comandos: ' . $error, 'warning', [
                    'response' => $response->json()
                ]);
            }
        } catch (Exception $e) {
            // Não é crítico se falhar, o Telegram ainda mostrará os comandos se estiverem registrados via setMyCommands
            $this->logBotAction($bot, 'Aviso ao configurar menu de comandos: ' . $e->getMessage(), 'warning');
        }
    }

    /**
     * Processa seleção de plano pelo usuário
     *
     * @param Bot $bot
     * @param int $chatId
     * @param array $from
     * @param int $planId
     * @param string $callbackQueryId
     * @return void
     */
    protected function handlePlanSelection(Bot $bot, int $chatId, array $from, int $planId, string $callbackQueryId): void
    {
        try {
            $plan = \App\Models\PaymentPlan::where('bot_id', $bot->id)
                ->where('id', $planId)
                ->where('active', true)
                ->first();

            if (!$plan) {
                $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId,
                    'text' => 'Plano não encontrado ou indisponível',
                    'show_alert' => true
                ]);
                return;
            }

            // Busca ou cria contato e registra ação
            $contact = $this->saveOrUpdateContact($bot, $from);
            $actionService = new ContactActionService();
            $actionService->logPlanSelection($bot, $contact, $planId, $plan->title, $plan->price);

            $price = number_format($plan->price, 2, ',', '.');
            $message = "💳 <b>{$plan->title}</b>\n\n";
            $message .= "💰 <b>Valor:</b> R$ {$price}\n\n";

            if ($plan->message) {
                $message .= "📝 {$plan->message}\n\n";
            }

            $message .= "Escolha o método de pagamento:";

            // Obtém métodos de pagamento habilitados no bot
            $botPaymentMethods = is_array($bot->payment_method) ? $bot->payment_method : ($bot->payment_method ? [$bot->payment_method] : ['credit_card']);

            // Cria botões apenas para métodos habilitados
            $keyboardButtons = [];

            if (in_array('pix', $botPaymentMethods)) {
                $keyboardButtons[] = [[
                    'text' => '💰 Pagar com PIX',
                    'callback_data' => "payment_{$planId}_pix"
                ]];
            }

            if (in_array('credit_card', $botPaymentMethods)) {
                $keyboardButtons[] = [[
                    'text' => '💳 Pagar com Cartão de Crédito',
                    'callback_data' => "payment_{$planId}_card"
                ]];
            }

            // Se nenhum método estiver habilitado, mostra mensagem de erro
            if (empty($keyboardButtons)) {
                $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId,
                    'text' => 'Nenhum método de pagamento está configurado para este bot.',
                    'show_alert' => true
                ]);
                return;
            }

            $keyboard = [
                'inline_keyboard' => $keyboardButtons
            ];

            $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId
            ]);

            $this->sendMessage($bot, $chatId, $message, $keyboard);

            $this->logBotAction($bot, "Plano selecionado pelo usuário", 'info', [
                'chat_id' => $chatId,
                'user_id' => $from['id'] ?? null,
                'plan_id' => $planId,
                'plan_title' => $plan->title
            ]);
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar seleção de plano: ' . $e->getMessage(), 'error', [
                'chat_id' => $chatId,
                'plan_id' => $planId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Processa seleção de método de pagamento
     *
     * @param Bot $bot
     * @param int $chatId
     * @param array $from
     * @param int $planId
     * @param string $method
     * @param string $callbackQueryId
     * @return void
     */
    protected function handlePaymentMethod(Bot $bot, int $chatId, array $from, int $planId, string $method, string $callbackQueryId): void
    {
        try {
            $plan = \App\Models\PaymentPlan::where('bot_id', $bot->id)
                ->where('id', $planId)
                ->where('active', true)
                ->first();

            if (!$plan) {
                $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId,
                    'text' => 'Plano não encontrado',
                    'show_alert' => true
                ]);
                return;
            }

            $price = number_format($plan->price, 2, ',', '.');

            // Valida se o bot tem o método de pagamento configurado
            $botPaymentMethods = is_array($bot->payment_method) ? $bot->payment_method : ($bot->payment_method ? [$bot->payment_method] : []);

            if ($method === 'pix' && !in_array('pix', $botPaymentMethods)) {
                $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId,
                    'text' => 'Método de pagamento PIX não está habilitado para este bot.',
                    'show_alert' => true
                ]);
                return;
            }

            if ($method === 'card' && !in_array('credit_card', $botPaymentMethods)) {
                $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId,
                    'text' => 'Método de pagamento com cartão não está habilitado para este bot.',
                    'show_alert' => true
                ]);
                return;
            }

            if ($method === 'pix') {
                // Responde ao callback query
                $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId,
                    'text' => 'Gerando QR Code PIX...'
                ]);

                // Busca ou cria contato
                $contact = $this->saveOrUpdateContact($bot, $from);

                // Busca ou cria contato e registra início de pagamento
                $contact = $this->saveOrUpdateContact($bot, $from);
                $actionService = new ContactActionService();

                // Gera QR Code PIX
                $paymentService = new PaymentService();
                $pixResult = $paymentService->generatePixQrCode($bot, $plan, $contact);

                if (!$pixResult['success']) {
                    $errorMessage = $pixResult['error'] ?? 'Erro desconhecido ao gerar código PIX';
                    $this->sendMessage($bot, $chatId, "❌ Erro ao gerar código PIX: {$errorMessage}\n\nPor favor, tente novamente ou entre em contato com o suporte.");
                    $this->logBotAction($bot, "Erro ao gerar código PIX", 'error', [
                        'chat_id' => $chatId,
                        'user_id' => $from['id'] ?? null,
                        'plan_id' => $planId,
                        'error' => $errorMessage
                    ]);
                    return;
                }

                // O código PIX já vem correto do PaymentService (exatamente como o Mercado Pago retornou)
                // NÃO devemos normalizar, corrigir ou modificar

                // Registra pagamento pendente
                $transaction = $pixResult['transaction'] ?? null;
                if ($transaction) {
                    $actionService->logPaymentPending(
                        $bot,
                        $contact,
                        $transaction,
                        'pix',
                        $pixResult['pix_key'] ?? null,
                        $pixResult['pix_code'] ?? null
                    );
                }

                // Monta mensagem inicial
                $message = "💳 <b>Pagamento via PIX</b>\n\n";
                $message .= "📦 <b>Plano:</b> {$plan->title}\n";
                $message .= "💰 <b>Valor:</b> R$ {$price}\n\n";

                if ($plan->pix_message) {
                    $message .= $plan->pix_message . "\n\n";
                }

                // Exibe código PIX apenas se disponível
                // CRÍTICO: O código PIX já vem do PaymentService EXATAMENTE como o Mercado Pago retornou
                // NÃO devemos modificar o código de forma alguma - apenas usar diretamente
                if (!empty($pixResult['pix_code'])) {
                    // CRÍTICO: O código PIX já vem do PaymentService EXATAMENTE como o Mercado Pago retornou
                    // NÃO devemos limpar, modificar ou alterar o código - usa EXATAMENTE como recebido
                    $pixCode = $pixResult['pix_code'];

                    // Log do código que será enviado ao usuário (EXATO do Mercado Pago)
                    $this->logBotAction($bot, "✅ Código PIX que será enviado ao usuário (EXATO do Mercado Pago)", 'info', [
                        'chat_id' => $chatId,
                        'plan_id' => $planId,
                        'pix_code_length' => strlen($pixCode),
                        'pix_code_full' => $pixCode,
                        'note' => 'Código usado EXATAMENTE como recebido do Mercado Pago - SEM MODIFICAÇÕES'
                    ]);

                    // Adiciona informações na mensagem principal
                    $message .= "📱 <b>Escaneie o QR Code abaixo para pagar:</b>\n\n";
                    $message .= "💡 <i>Ou use o código PIX copia e cola abaixo</i>\n\n";
                    $message .= "⏰ Este QR Code expira em 30 minutos.";

                    // Envia mensagem informativa primeiro
                    $this->sendMessage($bot, $chatId, $message);

                    // Aguarda um pequeno delay para garantir ordem das mensagens
                    usleep(500000); // 0.5 segundos

                    // Envia o código PIX EXATAMENTE como recebido (sem modificações)
                    // Apenas remove quebras de linha para exibição (não altera o código em si)
                    $pixCodeForDisplay = str_replace(["\r\n", "\r", "\n"], '', $pixCode);

                    // Envia o código PIX em uma mensagem isolada usando HTML
                    try {
                        $response = $this->http()->post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                            'chat_id' => $chatId,
                            'text' => "<pre><code>{$pixCodeForDisplay}</code></pre>",
                            'parse_mode' => 'HTML'
                        ]);

                        if ($response->successful()) {
                            LogFacade::info('✅ Código PIX enviado ao usuário', [
                                'chat_id' => $chatId,
                                'pix_code_length' => strlen($pixCodeForDisplay),
                                'note' => 'Código enviado EXATAMENTE como recebido do Mercado Pago'
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Fallback: envia sem formatação HTML
                        try {
                            $this->http()->post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                                'chat_id' => $chatId,
                                'text' => $pixCodeForDisplay
                            ]);
                        } catch (\Exception $e2) {
                            LogFacade::error('Erro ao enviar código PIX', [
                                'error' => $e2->getMessage(),
                                'chat_id' => $chatId
                            ]);
                        }
                    }

                    // Define $message como vazio para não enviar novamente abaixo
                    $message = '';
                } else {
                    // Se não tem código PIX, monta mensagem normal
                    $message .= "📱 <b>Escaneie o QR Code abaixo para pagar:</b>\n\n";
                    $message .= "⏰ Este QR Code expira em 30 minutos.";
                }

                // Envia mensagem apenas se não foi enviada acima (quando tem código PIX)
                if (!empty($message)) {
                    $this->sendMessage($bot, $chatId, $message);
                }

                // Envia QR Code como imagem
                try {
                    // O QR Code vem como base64 string do PaymentService
                    $qrCodeImageData = $pixResult['qr_code_image'] ?? null;

                    if (empty($qrCodeImageData)) {
                        throw new Exception('QR Code image data está vazio');
                    }

                    // O QR Code já foi gerado usando o código PIX correto do Mercado Pago
                    // Não precisa validar novamente - apenas envia

                    // Decodifica o base64 para obter os dados binários da imagem
                    $decoded = base64_decode($qrCodeImageData, true);
                    if ($decoded === false) {
                        // Se não for base64 válido, assume que já está decodificado
                        $decoded = $qrCodeImageData;
                    }

                    // Valida se os dados decodificados são uma imagem válida
                    if (empty($decoded) || strlen($decoded) < 100) {
                        throw new Exception('QR Code image data inválido ou muito pequeno');
                    }

                    // Verifica se é PNG (começa com PNG signature)
                    $isPng = substr($decoded, 0, 8) === "\x89PNG\r\n\x1a\n";
                    // Verifica se é SVG (começa com <svg ou <?xml)
                    $isSvg = strpos($decoded, '<svg') !== false || strpos($decoded, '<?xml') !== false;

                    if (!$isPng && !$isSvg) {
                        // Se não for PNG nem SVG, tenta usar como PNG mesmo assim
                        // (pode ser que a biblioteca tenha retornado dados binários sem header)
                        LogFacade::warning('QR Code image não tem signature PNG ou SVG válida', [
                            'data_start' => bin2hex(substr($decoded, 0, 20))
                        ]);
                    }

                    $fileExtension = $isPng ? 'png' : ($isSvg ? 'svg' : 'png');
                    $tempFile = tempnam(sys_get_temp_dir(), 'pix_qr_') . '.' . $fileExtension;

                    // Salva os dados binários no arquivo temporário
                    $bytesWritten = file_put_contents($tempFile, $decoded);
                    if ($bytesWritten === false || $bytesWritten === 0) {
                        throw new Exception('Erro ao salvar QR Code em arquivo temporário');
                    }

                    LogFacade::debug('QR Code salvo em arquivo temporário', [
                        'file' => $tempFile,
                        'size' => $bytesWritten,
                        'format' => $fileExtension
                    ]);

                    // Envia foto usando multipart/form-data
                    $response = $this->http()->asMultipart()
                        ->attach('photo', file_get_contents($tempFile), 'qrcode.' . $fileExtension)
                        ->post("https://api.telegram.org/bot{$bot->token}/sendPhoto", [
                            'chat_id' => $chatId,
                            'caption' => "📱 QR Code PIX - {$plan->title} - R$ {$price}"
                        ]);

                    // Remove arquivo temporário
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }

                    if (!$response->successful()) {
                        $errorBody = $response->body();
                        LogFacade::error('Erro ao enviar QR Code para Telegram', [
                            'status' => $response->status(),
                            'body' => $errorBody
                        ]);
                        throw new Exception('Erro ao enviar foto: ' . $errorBody);
                    }

                    LogFacade::info('QR Code enviado com sucesso para Telegram', [
                        'chat_id' => $chatId,
                        'plan_id' => $planId
                    ]);
                } catch (Exception $e) {
                    $this->logBotAction($bot, "Erro ao enviar QR Code como imagem", 'warning', [
                        'chat_id' => $chatId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Continua mesmo se falhar ao enviar imagem
                }

                $this->logBotAction($bot, "QR Code PIX gerado com sucesso", 'info', [
                    'chat_id' => $chatId,
                    'user_id' => $from['id'] ?? null,
                    'plan_id' => $planId,
                    'plan_title' => $plan->title,
                    'price' => $plan->price,
                    'transaction_id' => $pixResult['transaction']->id ?? null
                ]);

            } elseif ($method === 'card') {
                // Responde ao callback query
                $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId,
                    'text' => 'Gerando link de pagamento...'
                ]);

                // Busca ou cria contato
                $contact = $this->saveOrUpdateContact($bot, $from);
                $actionService = new ContactActionService();

                // Gera link de pagamento
                $paymentService = new PaymentService();
                $cardResult = $paymentService->generateCardPaymentLink($bot, $plan, $contact);

                if (!$cardResult['success']) {
                    $this->sendMessage($bot, $chatId, "❌ Erro ao gerar link de pagamento. Por favor, tente novamente.");
                    $this->logBotAction($bot, "Erro ao gerar link de pagamento com cartão", 'error', [
                        'chat_id' => $chatId,
                        'user_id' => $from['id'] ?? null,
                        'plan_id' => $planId,
                        'error' => $cardResult['error'] ?? 'Erro desconhecido'
                    ]);
                    return;
                }

                $transaction = $cardResult['transaction'];
                $paymentUrl = $cardResult['payment_url'];

                // Registra início de pagamento
                $actionService->logPaymentInitiated($bot, $contact, 'card', $planId, $plan->title, $plan->price, $transaction);

                // Monta mensagem com link de pagamento
                $message = "💳 <b>Pagamento com Cartão de Crédito</b>\n\n";
                $message .= "📦 <b>Plano:</b> {$plan->title}\n";
                $message .= "💰 <b>Valor:</b> R$ {$price}\n\n";
                $message .= "🔗 <b>Clique no botão abaixo para acessar o formulário de pagamento:</b>\n\n";
                $message .= "⏰ Este link expira em 24 horas.";

                // Cria botão inline com o link de pagamento
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '💳 Preencher Dados do Cartão',
                                'url' => $paymentUrl
                            ]
                        ]
                    ]
                ];

                $this->sendMessage($bot, $chatId, $message, $keyboard);

                // Envia também o link como texto para facilitar cópia
                $this->sendMessage($bot, $chatId, "🔗 <b>Ou copie e cole este link no seu navegador:</b>\n\n<code>{$paymentUrl}</code>", null, true);

                $this->logBotAction($bot, "Link de pagamento com cartão gerado", 'info', [
                    'chat_id' => $chatId,
                    'user_id' => $from['id'] ?? null,
                    'plan_id' => $planId,
                    'plan_title' => $plan->title,
                    'price' => $plan->price,
                    'transaction_id' => $transaction->id,
                    'payment_url' => $paymentUrl
                ]);
            } else {
                $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId,
                    'text' => 'Método de pagamento inválido',
                    'show_alert' => true
                ]);
            }
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar método de pagamento: ' . $e->getMessage(), 'error', [
                'chat_id' => $chatId,
                'plan_id' => $planId,
                'method' => $method,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Processa mensagem de texto normal
     *
     * @param Bot $bot
     * @param int $chatId
     * @param array $from
     * @param string|null $text
     * @return void
     */
    protected function processTextMessage(Bot $bot, int $chatId, array $from, ?string $text): void
    {
        if (!$text) {
            return;
        }

        // IMPORTANTE: Este método só deve ser chamado para chats privados
        // A verificação do tipo de chat deve ser feita antes de chamar este método

        // Busca contato para verificar se precisa coletar dados
        $contact = Contact::where('bot_id', $bot->id)
            ->where('telegram_id', $from['id'])
            ->first();

        // Se não existe contato, cria um
        if (!$contact) {
            $contact = $this->saveOrUpdateContact($bot, $from);
        }

        // Se o bot está configurado para solicitar email/telefone/idioma
        // IMPORTANTE: Só solicita dados em chats privados (não em grupos)
        if ($contact) {
            $actionService = new ContactActionService();

            // Verifica se precisa coletar email
            if ($bot->request_email && !$contact->email) {
                if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    $contact->email = $text;
                    $contact->save();

                    // Registra coleta de email
                    $actionService->logDataCollection($bot, $contact, 'email', $text);

                    $this->sendMessage($bot, $chatId, '✅ Email registrado com sucesso!');

                    // Recarrega o contato para ter os dados atualizados
                    $contact->refresh();

                    // Verifica se ainda precisa coletar outros dados
                    // IMPORTANTE: Verifica telefone primeiro se configurado
                    if ($bot->request_phone && !$contact->phone) {
                        // Usa botão nativo do Telegram para solicitar telefone
                        $phoneKeyboard = [
                            'keyboard' => [[
                                [
                                    'text' => '📱 Compartilhar meu telefone',
                                    'request_contact' => true
                                ]
                            ]],
                            'resize_keyboard' => true,
                            'one_time_keyboard' => true
                        ];
                        $this->sendMessage($bot, $chatId, '📱 Por favor, compartilhe seu número de telefone ou envie o número:', $phoneKeyboard);
                        return;
                    } elseif ($bot->request_language && !$contact->language) {
                        $this->sendMessage($bot, $chatId, '🌐 Por favor, escolha um idioma (pt, en, es, fr):');
                        return;
                    }

                    // Remove teclado se todos os dados foram coletados
                    $this->removeKeyboard($bot, $chatId);

                    // Verifica se todos os dados necessários foram coletados
                    $allDataCollected = (!$bot->request_email || $contact->email) &&
                                       (!$bot->request_phone || $contact->phone) &&
                                       (!$bot->request_language || $contact->language);

                    if ($allDataCollected) {
                        // Mensagem de confirmação final
                        $this->sendMessage($bot, $chatId, '✅ Obrigado! Seus dados foram registrados com sucesso.');

                        // Verifica se tem plano ativo e lista planos se necessário
                        $this->checkAndShowPlansIfNeeded($bot, $chatId, $contact);
                    }
                    return;
                } else {
                    $this->sendMessage($bot, $chatId, '❌ Email inválido. Por favor, envie um email válido:');
                    return;
                }
            }

            // Verifica se precisa coletar telefone (apenas se já tem email ou não precisa de email)
            if ($bot->request_phone && !$contact->phone) {
                // Remove caracteres não numéricos (exceto + no início)
                $phone = preg_replace('/[^\d+]/', '', $text);
                // Remove + se estiver no início para normalizar
                $phone = ltrim($phone, '+');

                if (strlen($phone) >= 10) {
                    $contact->phone = $phone;
                    $contact->save();

                    // Registra coleta de telefone
                    $actionService->logDataCollection($bot, $contact, 'phone', $phone);

                    $this->sendMessage($bot, $chatId, '✅ Telefone registrado com sucesso!');

                    // Recarrega o contato
                    $contact->refresh();

                    // Remove o teclado
                    $this->removeKeyboard($bot, $chatId);

                    // Verifica se ainda precisa coletar idioma
                    if ($bot->request_language && !$contact->language) {
                        $this->sendMessage($bot, $chatId, '🌐 Por favor, escolha um idioma (pt, en, es, fr):');
                        return;
                    }

                    // Verifica se todos os dados necessários foram coletados
                    $allDataCollected = (!$bot->request_email || $contact->email) &&
                                       (!$bot->request_phone || $contact->phone) &&
                                       (!$bot->request_language || $contact->language);

                    if ($allDataCollected) {
                        // Mensagem de confirmação final
                        $this->sendMessage($bot, $chatId, '✅ Obrigado! Seus dados foram registrados com sucesso.');

                        // Verifica se tem plano ativo e lista planos se necessário
                        $this->checkAndShowPlansIfNeeded($bot, $chatId, $contact);
                    }
                    return;
                } else {
                    $this->sendMessage($bot, $chatId, '❌ Telefone inválido. Por favor, envie um número de telefone válido (mínimo 10 dígitos) ou use o botão para compartilhar:');
                    return;
                }
            }

            // Verifica se precisa coletar idioma (apenas se já tem email/telefone ou não precisa deles)
            if ($bot->request_language && !$contact->language) {
                $validLanguages = ['pt', 'en', 'es', 'fr'];
                if (in_array(strtolower($text), $validLanguages)) {
                    $contact->language = strtolower($text);
                    $contact->save();

                    // Registra coleta de idioma
                    $actionService->logDataCollection($bot, $contact, 'language', strtolower($text));

                    $this->sendMessage($bot, $chatId, '✅ Idioma registrado com sucesso!');

                    // Remove teclado se todos os dados foram coletados
                    $this->removeKeyboard($bot, $chatId);

                    // Verifica se todos os dados necessários foram coletados
                    $allDataCollected = (!$bot->request_email || $contact->email) &&
                                       (!$bot->request_phone || $contact->phone) &&
                                       (!$bot->request_language || $contact->language);

                    if ($allDataCollected) {
                        // Mensagem de confirmação final
                        $this->sendMessage($bot, $chatId, '✅ Obrigado! Seus dados foram registrados com sucesso.');

                        // Verifica se tem plano ativo e lista planos se necessário
                        $this->checkAndShowPlansIfNeeded($bot, $chatId, $contact);
                    }
                    return;
                } else {
                    $this->sendMessage($bot, $chatId, '❌ Idioma inválido. Por favor, escolha um idioma válido (pt, en, es, fr):');
                    return;
                }
            }
        }

        // Se não precisa coletar dados, responde com mensagem padrão ou processa texto
        // Por enquanto, apenas registra a mensagem
        $this->logBotAction($bot, "Mensagem de texto recebida: " . substr($text, 0, 100), 'info', [
            'chat_id' => $chatId,
            'user_id' => $from['id']
        ]);
    }

    /**
     * Processa callback query (botões inline)
     *
     * @param Bot $bot
     * @param array $callbackQuery
     * @return void
     */
    protected function processCallbackQuery(Bot $bot, array $callbackQuery): void
    {
        try {
            $data = $callbackQuery['data'] ?? null;
            $from = $callbackQuery['from'] ?? null;
            $chatId = $callbackQuery['message']['chat']['id'] ?? null;
            $messageId = $callbackQuery['message']['message_id'] ?? null;
            $callbackQueryId = $callbackQuery['id'] ?? null;

            if (!$data || !$chatId || !$callbackQueryId) {
                return;
            }

            // Se não tem informações do usuário, tenta obter do message
            if (!$from && isset($callbackQuery['message']['from'])) {
                $from = $callbackQuery['message']['from'];
            }

            // Se ainda não tem $from, cria um array básico com o chat_id
            if (!$from) {
                $from = [
                    'id' => $chatId,
                    'first_name' => 'Usuário',
                    'is_bot' => false
                ];
            }

            // Responde ao callback query
            $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
                'text' => 'Processando...'
            ]);

            // Processa callbacks de planos (plan_{id})
            if (str_starts_with($data, 'plan_')) {
                $planId = str_replace('plan_', '', $data);
                $this->handlePlanSelection($bot, $chatId, $from, (int)$planId, $callbackQueryId);
                return;
            }

            // Processa callbacks de método de pagamento (payment_{planId}_{method})
            if (str_starts_with($data, 'payment_')) {
                $parts = explode('_', $data);
                if (count($parts) >= 3) {
                    $planId = (int)$parts[1];
                    $method = $parts[2]; // 'pix' ou 'card'
                    $this->handlePaymentMethod($bot, $chatId, $from, $planId, $method, $callbackQueryId);
                }
                return;
            }

            switch ($data) {
                case 'activate':
                    // Lógica de ativação aqui
                    $this->sendMessage($bot, $chatId, 'Ativação processada com sucesso!');
                    break;
            }
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar callback: ' . $e->getMessage(), 'error', [
                'callback_query' => $callbackQuery,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Envia uma mensagem de texto
     *
     * @param Bot $bot
     * @param int $chatId
     * @param string $text
     * @param array|null $keyboard
     * @return void
     */
    public function sendMessage(Bot $bot, int $chatId, string $text, ?array $keyboard = null): void
    {
        try {
            $data = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML'
            ];

            if ($keyboard) {
                $data['reply_markup'] = json_encode($keyboard);
            }

            $response = $this->http()->post("https://api.telegram.org/bot{$bot->token}/sendMessage", $data);

            if (!$response->successful()) {
                $errorData = $response->json();
                $this->logBotAction($bot, "Erro ao enviar mensagem: " . ($errorData['description'] ?? $response->body()), 'error', [
                    'chat_id' => $chatId,
                    'response' => $errorData
                ]);
            }
        } catch (\Exception $e) {
            $this->logBotAction($bot, "Erro ao enviar mensagem: " . $e->getMessage(), 'error', [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Envia mídias configuradas
     *
     * @param Bot $bot
     * @param int $chatId
     * @return void
     */
    protected function sendMedia(Bot $bot, int $chatId): void
    {
        $mediaUrls = array_filter([
            $bot->media_1_url,
            $bot->media_2_url,
            $bot->media_3_url
        ]);

        foreach ($mediaUrls as $url) {
            try {
                // Detecta tipo de mídia pela extensão
                $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

                $data = [
                    'chat_id' => $chatId
                ];

                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $this->http()->post("https://api.telegram.org/bot{$bot->token}/sendPhoto", array_merge($data, ['photo' => $url]));
                } elseif (in_array($extension, ['mp4', 'mov', 'avi'])) {
                    $this->http()->post("https://api.telegram.org/bot{$bot->token}/sendVideo", array_merge($data, ['video' => $url]));
                } else {
                    $this->http()->post("https://api.telegram.org/bot{$bot->token}/sendDocument", array_merge($data, ['document' => $url]));
                }
            } catch (Exception $e) {
                $this->logBotAction($bot, "Erro ao enviar mídia {$url}: " . $e->getMessage(), 'error');
            }
        }
    }

    /**
     * Salva ou atualiza um contato
     *
     * @param Bot $bot
     * @param array $from
     * @return Contact
     */
    protected function saveOrUpdateContact(Bot $bot, array $from): Contact
    {
        $contact = Contact::updateOrCreate(
            [
                'bot_id' => $bot->id,
                'telegram_id' => $from['id']
            ],
            [
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'is_bot' => $from['is_bot'] ?? false,
                'is_blocked' => false,
                'telegram_status' => 'active', // Marca como ativo quando há interação
                'last_interaction_at' => now()
            ]
        );

        // Sempre atualiza o status e última interação (updateOrCreate já faz isso, mas garantimos)
        $contact->update([
            'telegram_status' => 'active',
            'last_interaction_at' => now()
        ]);

        return $contact;
    }

    /**
     * Registra comandos do bot no Telegram usando setMyCommands
     *
     * @param Bot $bot
     * @return bool
     */
    public function registerBotCommands(Bot $bot): bool
    {
        try {
            // Comandos padrão do sistema
            $commands = [
                [
                    'command' => 'start',
                    'description' => 'Iniciar conversa com o bot'
                ],
                [
                    'command' => 'help',
                    'description' => 'Ver comandos disponíveis'
                ],
                [
                    'command' => 'planos',
                    'description' => 'Ver planos de pagamento disponíveis'
                ],
                [
                    'command' => 'meuplano',
                    'description' => 'Verificar status do meu plano atual'
                ]
            ];

            // Busca comandos personalizados ativos
            $customCommands = BotCommand::where('bot_id', $bot->id)
                ->where('active', true)
                ->orderBy('command')
                ->get();

            foreach ($customCommands as $cmd) {
                $commands[] = [
                    'command' => $cmd->command,
                    'description' => $cmd->description ?? 'Comando personalizado'
                ];
            }

            // Valida comandos antes de enviar
            foreach ($commands as $index => $cmd) {
                // Remove barras iniciais se houver
                $cmd['command'] = ltrim($cmd['command'], '/');
                // Garante que a descrição não esteja vazia
                if (empty($cmd['description'])) {
                    $cmd['description'] = 'Comando do bot';
                }
                // Limita tamanho da descrição (máximo 256 caracteres)
                $cmd['description'] = mb_substr($cmd['description'], 0, 256);
                $commands[$index] = $cmd;
            }

            // Registra comandos no Telegram
            // IMPORTANTE: Usa scope para garantir que os comandos apareçam em todos os chats privados
            // O Laravel HTTP client já faz o JSON encoding automaticamente quando usamos asJson()
            $response = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/setMyCommands", [
                    'commands' => $commands,
                    'scope' => [
                        'type' => 'all_private_chats'
                    ]
                ]);

            if ($response->successful() && $response->json()['ok']) {
                $this->logBotAction($bot, 'Comandos registrados no Telegram com sucesso', 'info', [
                    'commands_count' => count($commands),
                    'commands' => $commands,
                    'scope' => 'all_private_chats'
                ]);

                // Configura menu button para exibir os comandos
                // Isso garante que o botão "Menu" mostre todos os comandos registrados
                // IMPORTANTE: O menu só mostrará os comandos se eles estiverem registrados via setMyCommands
                $this->setPlansMenuButton($bot);

                return true;
            } else {
                $error = $response->json()['description'] ?? 'Erro desconhecido';
                $this->logBotAction($bot, 'Erro ao registrar comandos no Telegram: ' . $error, 'error', [
                    'response' => $response->json(),
                    'commands_sent' => $commands,
                    'scope' => 'all_private_chats',
                    'status_code' => $response->status()
                ]);
            }

            return false;
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao registrar comandos: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Processa contato compartilhado (telefone compartilhado via botão)
     *
     * @param Bot $bot
     * @param int $chatId
     * @param array $from
     * @param array $contactData
     * @param Contact|null $contact
     * @return void
     */
    protected function processSharedContact(Bot $bot, int $chatId, array $from, array $contactData, ?Contact $contact = null, string $chatType = 'private'): void
    {
        try {
            // IMPORTANTE: Só processa contato compartilhado em chats privados
            $isPrivateChat = ($chatType === 'private');
            if (!$isPrivateChat) {
                $this->logBotAction($bot, "Tentativa de processar contato compartilhado em grupo ignorada", 'warning', [
                    'chat_id' => $chatId,
                    'chat_type' => $chatType,
                    'user_id' => $from['id']
                ]);
                return;
            }

            if (!$contact) {
                $contact = $this->saveOrUpdateContact($bot, $from);
            }

            $actionService = new ContactActionService();

            // Extrai o número de telefone do contato compartilhado
            $phoneNumber = $contactData['phone_number'] ?? null;

            if ($phoneNumber) {
                // Remove caracteres não numéricos (exceto + no início)
                $phone = preg_replace('/[^\d+]/', '', $phoneNumber);
                if (strlen($phone) >= 10) {
                    $contact->phone = $phone;
                    $contact->save();

                    // Registra coleta de telefone
                    $actionService->logDataCollection($bot, $contact, 'phone', $phone);

                    $this->sendMessage($bot, $chatId, '✅ Telefone registrado com sucesso!');

                    // Recarrega o contato
                    $contact->refresh();

                    // Remove o teclado
                    $this->removeKeyboard($bot, $chatId);

                    // Verifica se ainda precisa coletar outros dados
                    if ($bot->request_email && !$contact->email) {
                        $this->sendMessage($bot, $chatId, '📧 Por favor, envie seu email:');
                    } elseif ($bot->request_language && !$contact->language) {
                        $this->sendMessage($bot, $chatId, '🌐 Por favor, escolha um idioma (pt, en, es, fr):');
                    } else {
                        // Todos os dados foram coletados, verifica planos
                        $allDataCollected = (!$bot->request_email || $contact->email) &&
                                           (!$bot->request_phone || $contact->phone) &&
                                           (!$bot->request_language || $contact->language);

                        if ($allDataCollected) {
                            $this->checkAndShowPlansIfNeeded($bot, $chatId, $contact);
                        }
                    }
                } else {
                    $this->sendMessage($bot, $chatId, '❌ Telefone inválido. Por favor, tente novamente.');
                }
            } else {
                $this->sendMessage($bot, $chatId, '❌ Não foi possível obter o número de telefone. Por favor, tente novamente.');
            }
        } catch (\Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar contato compartilhado: ' . $e->getMessage(), 'error', [
                'chat_id' => $chatId,
                'contact_data' => $contactData,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Remove o teclado personalizado
     *
     * @param Bot $bot
     * @param int $chatId
     * @return void
     */
    protected function removeKeyboard(Bot $bot, int $chatId): void
    {
        try {
            // Usa editMessageReplyMarkup se houver mensagem recente, senão usa sendMessage com texto vazio
            $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => ' ',
                    'reply_markup' => [
                        'remove_keyboard' => true
                    ]
                ]);
        } catch (\Exception $e) {
            // Ignora erro ao remover teclado
            $this->logBotAction($bot, 'Aviso ao remover teclado: ' . $e->getMessage(), 'warning');
        }
    }

    /**
     * Obtém lista de comandos registrados no Telegram
     *
     * @param Bot $bot
     * @param array|null $scope Scope opcional para buscar comandos específicos
     * @return array
     */
    public function getMyCommands(Bot $bot, ?array $scope = null): array
    {
        try {
            // Se não especificado, usa o mesmo scope usado ao registrar (all_private_chats)
            if ($scope === null) {
                $scope = [
                    'type' => 'all_private_chats'
                ];
            }

            $data = [
                'scope' => $scope
            ];

            $response = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/getMyCommands", $data);

            if ($response->successful() && $response->json()['ok']) {
                $result = $response->json()['result'] ?? [];
                // Se result é um array de comandos, retorna diretamente
                // Se result tem uma estrutura diferente, extrai os comandos
                if (isset($result[0]) && is_array($result[0])) {
                    $this->logBotAction($bot, 'Comandos obtidos do Telegram com sucesso', 'info', [
                        'commands_count' => count($result),
                        'scope' => $scope
                    ]);
                    return $result;
                }
                $commands = is_array($result) ? $result : [];
                $this->logBotAction($bot, 'Comandos obtidos do Telegram (estrutura diferente)', 'info', [
                    'commands_count' => count($commands),
                    'result_structure' => gettype($result),
                    'scope' => $scope
                ]);
                return $commands;
            }

            $this->logBotAction($bot, 'Erro ao obter comandos do Telegram', 'warning', [
                'response' => $response->json() ?? null,
                'status' => $response->status(),
                'scope' => $scope
            ]);

            return [];
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao obter comandos: ' . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Deleta todos os comandos registrados no Telegram
     *
     * @param Bot $bot
     * @return bool
     */
    public function deleteBotCommands(Bot $bot): bool
    {
        try {
            // Envia array vazio para deletar todos os comandos
            $response = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/setMyCommands", [
                    'commands' => []
                ]);

            if ($response->successful() && $response->json()['ok']) {
                $this->logBotAction($bot, 'Todos os comandos foram deletados do Telegram', 'info');
                return true;
            } else {
                $error = $response->json()['description'] ?? 'Erro desconhecido';
                $this->logBotAction($bot, 'Erro ao deletar comandos do Telegram: ' . $error, 'error', [
                    'response' => $response->json()
                ]);
                return false;
            }
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao deletar comandos: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Deleta um comando específico do Telegram
     *
     * @param Bot $bot
     * @param string $commandName Nome do comando a ser deletado (sem a barra /)
     * @return bool
     */
    public function deleteBotCommand(Bot $bot, string $commandName): bool
    {
        try {
            // Remove a barra se houver
            $commandName = ltrim($commandName, '/');

            // Obtém lista atual de comandos do Telegram
            $currentCommands = $this->getMyCommands($bot);

            // Filtra removendo o comando específico
            $filteredCommands = array_filter($currentCommands, function($cmd) use ($commandName) {
                return ($cmd['command'] ?? '') !== $commandName;
            });

            // Reindexa o array
            $filteredCommands = array_values($filteredCommands);

            // Re-registra os comandos restantes
            $response = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/setMyCommands", [
                    'commands' => $filteredCommands
                ]);

            if ($response->successful() && $response->json()['ok']) {
                $this->logBotAction($bot, "Comando '{$commandName}' deletado do Telegram", 'info', [
                    'command' => $commandName,
                    'remaining_commands' => count($filteredCommands)
                ]);
                return true;
            } else {
                $error = $response->json()['description'] ?? 'Erro desconhecido';
                $this->logBotAction($bot, 'Erro ao deletar comando do Telegram: ' . $error, 'error', [
                    'command' => $commandName,
                    'response' => $response->json()
                ]);
                return false;
            }
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao deletar comando: ' . $e->getMessage(), 'error', [
                'command' => $commandName
            ]);
            return false;
        }
    }

    /**
     * Envia documento
     *
     * @param Bot $bot
     * @param int $chatId
     * @param string $documentUrl
     * @param string|null $caption
     * @return void
     */
    public function sendDocument(Bot $bot, int $chatId, string $documentUrl, ?string $caption = null): void
    {
        try {
            $data = [
                'chat_id' => $chatId,
                'document' => $documentUrl
            ];

            if ($caption) {
                $data['caption'] = $caption;
            }

            $this->http()->post("https://api.telegram.org/bot{$bot->token}/sendDocument", $data);
        } catch (Exception $e) {
            $this->logBotAction($bot, "Erro ao enviar documento: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Envia teclado personalizado (ReplyKeyboardMarkup)
     *
     * @param Bot $bot
     * @param int $chatId
     * @param string $text
     * @param array $keyboard
     * @param bool $resizeKeyboard
     * @param bool $oneTimeKeyboard
     * @return void
     */
    public function sendMessageWithKeyboard(Bot $bot, int $chatId, string $text, array $keyboard, bool $resizeKeyboard = true, bool $oneTimeKeyboard = false): void
    {
        try {
            $replyMarkup = [
                'keyboard' => $keyboard,
                'resize_keyboard' => $resizeKeyboard,
                'one_time_keyboard' => $oneTimeKeyboard
            ];

            $data = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($replyMarkup)
            ];

            $this->http()->post("https://api.telegram.org/bot{$bot->token}/sendMessage", $data);
        } catch (Exception $e) {
            $this->logBotAction($bot, "Erro ao enviar mensagem com teclado: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Registra uma ação do bot
     *
     * @param Bot $bot
     * @param string $message
     * @param string $level
     * @param array $context
     * @return void
     */
    protected function logBotAction(Bot $bot, string $message, string $level = 'info', array $context = []): void
    {
        try {
            $user = auth()->user();
            Log::create([
                'bot_id' => $bot->id,
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'user_email' => $user ? $user->email : null,
                'ip_address' => request()->ip()
            ]);
        } catch (Exception $e) {
            LogFacade::error('Erro ao salvar log do bot', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtém lista de administradores do grupo
     *
     * @param string $token Token do bot
     * @param string $chatId ID do chat (grupo/canal)
     * @return array ['success' => bool, 'administrators' => array, 'error' => string|null]
     */
    /**
     * Detecta se um erro do Telegram indica migração de grupo para supergrupo
     * e extrai o novo chat_id dos parâmetros da resposta
     *
     * @param array $responseData Dados da resposta JSON do Telegram
     * @return array|null ['migrated' => true, 'new_chat_id' => string] ou null se não for migração
     */
    protected function detectGroupMigration(array $responseData): ?array
    {
        $description = $responseData['description'] ?? '';
        $parameters = $responseData['parameters'] ?? [];

        // Verifica se o erro indica migração de grupo para supergrupo
        if (strpos($description, 'group chat was upgraded to a supergroup chat') !== false) {
            // O Telegram retorna o novo chat_id nos parâmetros
            $newChatId = $parameters['migrate_to_chat_id'] ?? null;

            if ($newChatId) {
                LogFacade::info('🔄 Grupo migrado para supergrupo detectado', [
                    'old_chat_id' => $parameters['chat_id'] ?? 'unknown',
                    'new_chat_id' => $newChatId
                ]);

                return [
                    'migrated' => true,
                    'new_chat_id' => (string)$newChatId
                ];
            }
        }

        return null;
    }

    public function getChatAdministrators(string $token, string $chatId): array
    {
        try {
            $normalizedChatId = $this->normalizeChatId($chatId);

            $response = $this->http()->get("https://api.telegram.org/bot{$token}/getChatAdministrators", [
                'chat_id' => $normalizedChatId
            ]);

            $responseData = $response->json() ?? [];

            if (!$response->successful() || !($responseData['ok'] ?? false)) {
                // Verifica se o erro é devido a migração de grupo para supergrupo
                $migration = $this->detectGroupMigration($responseData);

                if ($migration) {
                    // Retorna informação sobre a migração para que o chamador possa atualizar
                    return [
                        'success' => false,
                        'administrators' => [],
                        'error' => 'Grupo foi atualizado para supergrupo',
                        'migrated' => true,
                        'new_chat_id' => $migration['new_chat_id'],
                        'old_chat_id' => $normalizedChatId
                    ];
                }

                return [
                    'success' => false,
                    'administrators' => [],
                    'error' => 'Erro ao obter administradores: ' . ($responseData['description'] ?? 'Erro desconhecido')
                ];
            }

            $administrators = $responseData['result'] ?? [];

            return [
                'success' => true,
                'administrators' => $administrators,
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'administrators' => [],
                'error' => 'Erro ao obter administradores: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém contagem de membros do grupo
     *
     * @param string $token Token do bot
     * @param string $chatId ID do chat (grupo/canal)
     * @return array ['success' => bool, 'member_count' => int, 'error' => string|null]
     */
    public function getChatMemberCount(string $token, string $chatId): array
    {
        try {
            $normalizedChatId = $this->normalizeChatId($chatId);

            $response = $this->http()->get("https://api.telegram.org/bot{$token}/getChatMemberCount", [
                'chat_id' => $normalizedChatId
            ]);

            $responseData = $response->json() ?? [];

            if (!$response->successful() || !($responseData['ok'] ?? false)) {
                // Verifica se o erro é devido a migração de grupo para supergrupo
                $migration = $this->detectGroupMigration($responseData);

                if ($migration) {
                    return [
                        'success' => false,
                        'member_count' => 0,
                        'error' => 'Grupo foi atualizado para supergrupo',
                        'migrated' => true,
                        'new_chat_id' => $migration['new_chat_id'],
                        'old_chat_id' => $normalizedChatId
                    ];
                }

                return [
                    'success' => false,
                    'member_count' => 0,
                    'error' => 'Erro ao obter contagem de membros: ' . ($responseData['description'] ?? 'Erro desconhecido')
                ];
            }

            $memberCount = $responseData['result'] ?? 0;

            return [
                'success' => true,
                'member_count' => $memberCount,
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'member_count' => 0,
                'error' => 'Erro ao obter contagem de membros: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sincroniza membros do grupo salvando-os como contatos
     * Obtém administradores e os salva no banco de dados
     *
     * @param Bot $bot
     * @return array ['success' => bool, 'synced_count' => int, 'error' => string|null, 'details' => array]
     */
    public function syncGroupMembers(Bot $bot): array
    {
        try {
            if (empty($bot->telegram_group_id)) {
                return [
                    'success' => false,
                    'synced_count' => 0,
                    'error' => 'Bot não tem grupo configurado',
                    'details' => []
                ];
            }

            // Obtém administradores do grupo
            $adminsResult = $this->getChatAdministrators($bot->token, $bot->telegram_group_id);

            // Verifica se o grupo foi migrado para supergrupo
            if (isset($adminsResult['migrated']) && $adminsResult['migrated'] === true) {
                $newChatId = $adminsResult['new_chat_id'] ?? null;

                if ($newChatId) {
                    LogFacade::info('🔄 Atualizando telegram_group_id do bot após migração para supergrupo', [
                        'bot_id' => $bot->id,
                        'old_chat_id' => $bot->telegram_group_id,
                        'new_chat_id' => $newChatId
                    ]);

                    // Atualiza o telegram_group_id do bot no banco de dados
                    $bot->update(['telegram_group_id' => $newChatId]);
                    $bot->refresh();

                    // Tenta novamente com o novo chat_id
                    $adminsResult = $this->getChatAdministrators($bot->token, $newChatId);

                    if (!$adminsResult['success']) {
                        return [
                            'success' => false,
                            'synced_count' => 0,
                            'error' => 'Erro ao obter administradores após migração: ' . ($adminsResult['error'] ?? 'Erro desconhecido'),
                            'details' => [],
                            'migrated' => true,
                            'new_chat_id' => $newChatId
                        ];
                    }

                    LogFacade::info('✅ Sincronização continuada após migração para supergrupo', [
                        'bot_id' => $bot->id,
                        'new_chat_id' => $newChatId
                    ]);
                } else {
                    return [
                        'success' => false,
                        'synced_count' => 0,
                        'error' => 'Grupo foi migrado para supergrupo, mas novo chat_id não foi encontrado',
                        'details' => []
                    ];
                }
            }

            if (!$adminsResult['success']) {
                return [
                    'success' => false,
                    'synced_count' => 0,
                    'error' => $adminsResult['error'],
                    'details' => []
                ];
            }

            $administrators = $adminsResult['administrators'];
            $syncedCount = 0;
            $details = [];

            foreach ($administrators as $admin) {
                $user = $admin['user'] ?? null;
                if (!$user) {
                    continue; // Pula se não tem dados do usuário
                }

                try {
                    $isBot = $user['is_bot'] ?? false;
                    $contact = Contact::updateOrCreate(
                        [
                            'bot_id' => $bot->id,
                            'telegram_id' => (string)$user['id']
                        ],
                        [
                            'username' => $user['username'] ?? null,
                            'first_name' => $user['first_name'] ?? null,
                            'last_name' => $user['last_name'] ?? null,
                            'is_bot' => $isBot, // Inclui bots também
                            'is_blocked' => false,
                            'telegram_status' => 'active' // Membros do grupo são ativos
                        ]
                    );

                    // Se o contato já existia, atualiza o status para ativo
                    if (!$contact->wasRecentlyCreated) {
                        $contact->update(['telegram_status' => 'active']);
                    }

                    $syncedCount++;
                    $details[] = [
                        'telegram_id' => $user['id'],
                        'username' => $user['username'] ?? null,
                        'first_name' => $user['first_name'] ?? null,
                        'status' => $admin['status'] ?? 'unknown',
                        'is_bot' => $isBot,
                        'synced' => true
                    ];
                } catch (Exception $e) {
                    $details[] = [
                        'telegram_id' => $user['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'synced' => false
                    ];
                }
            }

            return [
                'success' => true,
                'synced_count' => $syncedCount,
                'error' => null,
                'details' => $details
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'synced_count' => 0,
                'error' => 'Erro ao sincronizar membros: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * Verifica se o contato tem um plano ativo e lista planos se não tiver
     *
     * @param Bot $bot
     * @param int $chatId
     * @param Contact $contact
     * @return void
     */
    protected function checkAndShowPlansIfNeeded(Bot $bot, int $chatId, Contact $contact): void
    {
        try {
            $this->logBotAction($bot, "Verificando se contato tem plano ativo", 'info', [
                'contact_id' => $contact->id,
                'chat_id' => $chatId
            ]);

            // Busca a última transação aprovada do contato
            $lastApprovedTransaction = \App\Models\Transaction::where('contact_id', $contact->id)
                ->whereIn('status', ['approved', 'paid', 'completed'])
                ->orderBy('created_at', 'desc')
                ->with(['paymentPlan', 'paymentCycle'])
                ->first();

            $hasActivePlan = false;

            if ($lastApprovedTransaction) {
                $paymentCycle = $lastApprovedTransaction->paymentCycle;
                if ($paymentCycle) {
                    // Calcula data de expiração baseada na data de criação + dias do ciclo
                    $expiresAt = \Carbon\Carbon::parse($lastApprovedTransaction->created_at)
                        ->addDays($paymentCycle->days ?? 30);

                    // Verifica se ainda não expirou
                    $hasActivePlan = \Carbon\Carbon::now()->lessThanOrEqualTo($expiresAt);

                    if ($hasActivePlan) {
                        $paymentPlan = $lastApprovedTransaction->paymentPlan;
                        $daysRemaining = \Carbon\Carbon::now()->diffInDays($expiresAt, false);

                        $this->logBotAction($bot, "Contato tem plano ativo", 'info', [
                            'contact_id' => $contact->id,
                            'plan_id' => $paymentPlan->id ?? null,
                            'days_remaining' => $daysRemaining
                        ]);

                        $message = "✅ <b>Você já possui um plano ativo!</b>\n\n";
                        $message .= "📦 <b>Plano:</b> " . ($paymentPlan->title ?? 'N/A') . "\n";
                        $message .= "⏰ <b>Expira em:</b> " . $expiresAt->format('d/m/Y') . "\n";
                        $message .= "📅 <b>Dias restantes:</b> {$daysRemaining} dia(s)\n\n";
                        $message .= "Obrigado por ser nosso cliente!";

                        $this->sendMessage($bot, $chatId, $message);
                        // Continua para exibir os planos disponíveis mesmo tendo plano ativo
                    }
                }
            }

            // Sempre lista os planos disponíveis (mesmo se tiver plano ativo)
            $this->logBotAction($bot, "Listando planos disponíveis", 'info', [
                'contact_id' => $contact->id,
                'has_active_plan' => $hasActivePlan
            ]);

            $this->handlePlansCommand($bot, $chatId, [
                'id' => $contact->telegram_id,
                'first_name' => $contact->first_name,
                'username' => $contact->username
            ]);
        } catch (\Exception $e) {
            $this->logBotAction($bot, 'Erro ao verificar planos do contato: ' . $e->getMessage(), 'error', [
                'contact_id' => $contact->id,
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString()
            ]);

            // Em caso de erro, tenta listar os planos mesmo assim
            try {
                $this->handlePlansCommand($bot, $chatId, [
                    'id' => $contact->telegram_id,
                    'first_name' => $contact->first_name,
                    'username' => $contact->username
                ]);
            } catch (\Exception $e2) {
                // Ignora erro ao listar planos
            }
        }
    }

    /**
     * Processa evento my_chat_member (quando o status do bot em um chat muda)
     * Aplica os direitos padrão quando o bot é promovido a administrador
     *
     * @param Bot $bot
     * @param array $myChatMember
     * @return void
     */
    protected function processMyChatMember(Bot $bot, array $myChatMember): void
    {
        try {
            $chat = $myChatMember['chat'] ?? null;
            $newChatMember = $myChatMember['new_chat_member'] ?? null;
            $oldChatMember = $myChatMember['old_chat_member'] ?? null;

            if (!$chat || !$newChatMember) {
                return;
            }

            $chatId = $chat['id'];
            $chatType = $chat['type'] ?? 'unknown';
            $newStatus = $newChatMember['status'] ?? 'unknown';
            $oldStatus = $oldChatMember['status'] ?? 'unknown';

            $this->logBotAction($bot, "Evento my_chat_member recebido", 'info', [
                'chat_id' => $chatId,
                'chat_type' => $chatType,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

            // Verifica se o bot foi promovido a administrador
            if ($newStatus === 'administrator' && $oldStatus !== 'administrator') {
                $this->logBotAction($bot, "Bot foi promovido a administrador", 'info', [
                    'chat_id' => $chatId,
                    'chat_type' => $chatType
                ]);

                // Busca os direitos padrão configurados
                $defaultRights = $this->getMyDefaultAdministratorRights($bot->token, $chatType === 'channel');

                if ($defaultRights && is_array($defaultRights)) {
                    // Aplica os direitos padrão usando promoteChatMember
                    $this->applyAdministratorRights($bot, $chatId, $defaultRights);
                } else {
                    $this->logBotAction($bot, "Nenhum direito padrão configurado para aplicar", 'info', [
                        'chat_id' => $chatId
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar my_chat_member: ' . $e->getMessage(), 'error', [
                'my_chat_member' => $myChatMember,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Obtém os direitos padrão de administrador do bot
     *
     * @param string $token
     * @param bool $forChannels
     * @return array|null
     */
    protected function getMyDefaultAdministratorRights(string $token, bool $forChannels = false): ?array
    {
        try {
            $data = [];
            if ($forChannels) {
                $data['for_channels'] = true;
            }

            $result = $this->http()
                ->post("https://api.telegram.org/bot{$token}/getMyDefaultAdministratorRights", $data);

            $responseData = $result->json();

            if ($result->successful() && ($responseData['ok'] ?? false)) {
                return $responseData['result'] ?? null;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao obter direitos padrão de administrador', [
                'error' => $e->getMessage()
            ]);
        }
        return null;
    }

    /**
     * Aplica direitos de administrador a um bot em um chat usando promoteChatMember
     *
     * @param Bot $bot
     * @param string $chatId
     * @param array $rights
     * @return void
     */
    protected function applyAdministratorRights(Bot $bot, string $chatId, array $rights): void
    {
        try {
            $botId = $this->getBotIdFromToken($bot->token);

            // Prepara os dados para promoteChatMember
            $data = [
                'chat_id' => $chatId,
                'user_id' => $botId,
                'is_anonymous' => $rights['is_anonymous'] ?? false,
            ];

            // Adiciona apenas as permissões que são true
            // O Telegram interpreta a ausência de uma permissão como false
            foreach ($rights as $key => $value) {
                if ($key !== 'is_anonymous' && $value === true) {
                    $data[$key] = true;
                }
            }

            $this->logBotAction($bot, "Aplicando direitos de administrador", 'info', [
                'chat_id' => $chatId,
                'rights' => $data,
                'rights_count' => count($data)
            ]);

            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/promoteChatMember", $data);

            $responseData = $result->json();

            if ($result->successful() && ($responseData['ok'] ?? false)) {
                $this->logBotAction($bot, "Direitos de administrador aplicados com sucesso", 'info', [
                    'chat_id' => $chatId,
                    'response' => $responseData
                ]);
            } else {
                $errorDescription = $responseData['description'] ?? 'Erro desconhecido';
                $this->logBotAction($bot, "Erro ao aplicar direitos de administrador: {$errorDescription}", 'error', [
                    'chat_id' => $chatId,
                    'response' => $responseData,
                    'sent_data' => $data
                ]);
            }
        } catch (\Exception $e) {
            $this->logBotAction($bot, 'Erro ao aplicar direitos de administrador: ' . $e->getMessage(), 'error', [
                'chat_id' => $chatId,
                'rights' => $rights,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Atualiza o status do Telegram de um contato baseado em se é membro do grupo
     *
     * @param Bot $bot
     * @param Contact $contact
     * @return void
     */
    public function updateContactTelegramStatus(Bot $bot, Contact $contact): void
    {
        try {
            if (empty($bot->telegram_group_id)) {
                // Se não tem grupo, marca como ativo se interagiu recentemente
                if ($contact->last_interaction_at && $contact->last_interaction_at->isAfter(now()->subDays(30))) {
                    $contact->update(['telegram_status' => 'active']);
                } else {
                    $contact->update(['telegram_status' => 'inactive']);
                }
                return;
            }

            // Verifica se é membro do grupo
            $memberInfo = $this->getChatMember($bot->token, $bot->telegram_group_id, $contact->telegram_id);

            if ($memberInfo['is_member'] ?? false) {
                $contact->update(['telegram_status' => 'active']);
            } else {
                // Se não é membro, verifica última interação
                if ($contact->last_interaction_at && $contact->last_interaction_at->isAfter(now()->subDays(7))) {
                    $contact->update(['telegram_status' => 'active']);
                } else {
                    $contact->update(['telegram_status' => 'inactive']);
                }
            }
        } catch (Exception $e) {
            // Em caso de erro, mantém status atual ou marca como inativo
            LogFacade::warning('Erro ao atualizar status do Telegram do contato', [
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
