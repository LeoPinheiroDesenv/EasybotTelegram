<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotCommand;
use App\Models\Contact;
use App\Models\Log;
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

            $this->logBotAction($bot, 'Bot inicializado com sucesso', 'info');

            $message = 'Bot inicializado com sucesso. ';
            if ($hasWebhook) {
                $message .= 'Webhook já está configurado. O bot receberá atualizações automaticamente.';
            } else {
                $message .= 'Para receber atualizações, execute: php artisan telegram:polling --bot-id=' . $bot->id . ' OU configure webhook via POST /api/telegram/webhook/' . $bot->id . '/set';
            }

            return [
                'success' => true,
                'message' => $message,
                'bot_info' => $validation['bot'],
                'has_webhook' => $hasWebhook,
                'webhook_url' => $webhookInfo['url'] ?? null,
                'next_steps' => $hasWebhook ? [] : [
                    'polling' => 'Execute: php artisan telegram:polling --bot-id=' . $bot->id,
                    'webhook' => 'Configure: POST /api/telegram/webhook/' . $bot->id . '/set'
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
            
            return [
                'bot_id' => $bot->id,
                'active' => $bot->active,
                'activated' => $bot->activated,
                'token_valid' => $validation['valid'],
                'bot_info' => $validation['valid'] ? $validation['bot'] : null,
                'error' => $validation['valid'] ? null : $validation['error']
            ];
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

            if (!$response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['description'] ?? 'Erro ao acessar grupo';
                
                return [
                    'valid' => false,
                    'error' => $errorMessage
                ];
            }

            $data = $response->json();
            
            if (!$data['ok']) {
                return [
                    'valid' => false,
                    'error' => $data['description'] ?? 'Grupo não encontrado ou inacessível'
                ];
            }

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

            if (!$response->successful() || !$response->json()['ok']) {
                return [
                    'is_member' => false,
                    'status' => 'not_member'
                ];
            }

            $member = $response->json()['result'];
            $status = $member['status'] ?? 'unknown';

            return [
                'is_member' => in_array($status, ['member', 'administrator', 'creator']),
                'status' => $status,
                'can_restrict_members' => $member['can_restrict_members'] ?? false,
                'can_invite_users' => $member['can_invite_users'] ?? false,
                'can_delete_messages' => $member['can_delete_messages'] ?? false,
                'can_pin_messages' => $member['can_pin_messages'] ?? false
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
            $response = $this->http()->post("https://api.telegram.org/bot{$token}/unbanChatMember", [
                'chat_id' => $groupId,
                'user_id' => $userId,
                'only_if_banned' => false
            ]);

            if (!$response->successful() || !$response->json()['ok']) {
                $errorData = $response->json();
                return [
                    'success' => false,
                    'error' => $errorData['description'] ?? 'Erro ao adicionar usuário ao grupo'
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
            $response = $this->http()->post("https://api.telegram.org/bot{$token}/banChatMember", [
                'chat_id' => $groupId,
                'user_id' => $userId,
                'revoke_messages' => false
            ]);

            if (!$response->successful() || !$response->json()['ok']) {
                $errorData = $response->json();
                return [
                    'success' => false,
                    'error' => $errorData['description'] ?? 'Erro ao remover usuário do grupo'
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
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar atualização: ' . $e->getMessage(), 'error', [
                'update' => $update
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

        // Salva ou atualiza contato (apenas para chats privados)
        if ($chatType === 'private') {
            $this->saveOrUpdateContact($bot, $from);
        }

        // Verifica se é um grupo ou supergrupo
        if (in_array($chatType, ['group', 'supergroup'])) {
            $this->processGroupMessage($bot, $message, $chat);
            return;
        }

        // Processa comandos
        if ($text && str_starts_with($text, '/')) {
            $commandParts = explode(' ', $text);
            $command = $commandParts[0];
            $this->processCommand($bot, $chatId, $from, $command);
        } else {
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
        if ($from) {
            $this->saveOrUpdateContact($bot, $from);
        }

        // Processa comandos em grupos (se o bot foi mencionado ou é comando direto)
        if ($text && str_starts_with($text, '/')) {
            $commandParts = explode(' ', $text);
            $command = $commandParts[0];
            
            // Verifica se o comando menciona o bot ou é um comando direto
            $botMentioned = isset($message['entities']) && collect($message['entities'])
                ->contains(function ($entity) use ($text) {
                    return $entity['type'] === 'mention' || $entity['type'] === 'bot_command';
                });

            if ($botMentioned || str_contains($command, '@' . ($bot->name ?? ''))) {
                $this->processCommand($bot, $chatId, $from, $command);
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
    protected function processCommand(Bot $bot, int $chatId, array $from, string $command): void
    {
        $command = trim($command);
        $commandName = str_replace('/', '', $command); // Remove a barra
        
        // Comandos padrão do sistema
        switch ($command) {
            case '/start':
                $this->handleStartCommand($bot, $chatId, $from);
                return;
            case '/help':
            case '/comandos':
                $this->handleHelpCommand($bot, $chatId);
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
            
            // Envia resposta do comando personalizado
            $this->sendMessage($bot, $chatId, $customCommand->response);
            
            $this->logBotAction($bot, "Comando personalizado executado: {$command}", 'info', [
                'command_id' => $customCommand->id
            ]);
        } else {
            // Comando não encontrado
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
    protected function handleStartCommand(Bot $bot, int $chatId, array $from): void
    {
        try {
            // Envia mensagem superior (se configurada)
            if ($bot->top_message) {
                $this->sendMessage($bot, $chatId, $bot->top_message);
            }

            // Envia mídias configuradas
            $this->sendMedia($bot, $chatId);

            // Envia mensagem inicial
            $message = $bot->initial_message ?? 'Bem-vindo!';
            
            $keyboard = null;
            if ($bot->activate_cta && $bot->button_message) {
                $keyboard = [
                    'inline_keyboard' => [[
                        [
                            'text' => $bot->button_message,
                            'callback_data' => 'activate'
                        ]
                    ]]
                ];
            }

            $this->sendMessage($bot, $chatId, $message, $keyboard);

            $this->logBotAction($bot, "Comando /start processado para chat {$chatId}", 'info');
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar /start: ' . $e->getMessage(), 'error');
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
            $helpText .= "/comandos - Listar comandos disponíveis\n\n";

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
        // Por enquanto, apenas salva o contato
        // Lógica adicional pode ser adicionada aqui
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
            $chatId = $callbackQuery['message']['chat']['id'] ?? null;
            $messageId = $callbackQuery['message']['message_id'] ?? null;
            $callbackQueryId = $callbackQuery['id'] ?? null;

            if (!$data || !$chatId || !$callbackQueryId) {
                return;
            }

            // Responde ao callback query
            $this->http()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
                'text' => 'Processando...'
            ]);

            switch ($data) {
                case 'activate':
                    // Lógica de ativação aqui
                    $this->sendMessage($bot, $chatId, 'Ativação processada com sucesso!');
                    break;
            }
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao processar callback: ' . $e->getMessage(), 'error');
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

            $this->http()->post("https://api.telegram.org/bot{$bot->token}/sendMessage", $data);
        } catch (Exception $e) {
            $this->logBotAction($bot, "Erro ao enviar mensagem: " . $e->getMessage(), 'error');
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
        return Contact::updateOrCreate(
            [
                'bot_id' => $bot->id,
                'telegram_id' => $from['id']
            ],
            [
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'is_bot' => $from['is_bot'] ?? false,
                'is_blocked' => false
            ]
        );
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
            Log::create([
                'bot_id' => $bot->id,
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'user_email' => auth()->user()->email ?? null,
                'ip_address' => request()->ip()
            ]);
        } catch (Exception $e) {
            LogFacade::error('Erro ao salvar log do bot', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

