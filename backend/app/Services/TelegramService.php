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

            // Registra comandos do bot no Telegram
            $this->registerBotCommands($bot);

            $this->logBotAction($bot, 'Bot inicializado com sucesso', 'info');

            $message = 'Bot inicializado com sucesso. ';
            if ($hasWebhook) {
                $message .= 'Webhook já está configurado. O bot receberá atualizações automaticamente.';
            } else {
                $message .= 'Para receber atualizações do Telegram, configure o webhook através da interface ou via API: POST /api/telegram/webhook/' . $bot->id . '/set';
            }

            return [
                'success' => true,
                'message' => $message,
                'bot_info' => $validation['bot'],
                'has_webhook' => $hasWebhook,
                'webhook_url' => $webhookInfo['url'] ?? null,
                'next_steps' => $hasWebhook ? [] : [
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
            $canInviteUsers = $botMemberInfo['can_invite_users'] ?? false;

            // Log para debug
            LogFacade::info('Verificando permissões do bot para obter link de convite', [
                'chat_id' => $normalizedChatId,
                'bot_id' => $botId,
                'status' => $status,
                'is_admin' => $isAdmin,
                'can_invite_users' => $canInviteUsers,
                'bot_member_info' => $botMemberInfo
            ]);

            // Se não é admin e não tem permissão de convidar, retorna erro
            // Mas se for admin, tenta mesmo assim (alguns admins podem ter permissões implícitas)
            if (!$isAdmin && !$canInviteUsers) {
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

            // Tenta exportChatInviteLink primeiro (retorna link existente se houver)
            try {
                $response = $this->http()->post("https://api.telegram.org/bot{$token}/exportChatInviteLink", [
                    'chat_id' => $normalizedChatId
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['ok']) && $data['ok'] && isset($data['result'])) {
                        return [
                            'success' => true,
                            'invite_link' => $data['result'],
                            'error' => null,
                            'details' => [
                                'method' => 'exportChatInviteLink',
                                'status' => $status,
                                'is_admin' => $isAdmin
                            ]
                        ];
                    }
                }

                // Se exportChatInviteLink falhou, tenta createChatInviteLink
                $errorData = $response->json();
                $errorMessage = $errorData['description'] ?? 'Erro desconhecido';
                
                LogFacade::info('exportChatInviteLink falhou, tentando createChatInviteLink', [
                    'chat_id' => $normalizedChatId,
                    'error' => $errorMessage
                ]);

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

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['ok']) && $data['ok'] && isset($data['result']['invite_link'])) {
                        return [
                            'success' => true,
                            'invite_link' => $data['result']['invite_link'],
                            'error' => null,
                            'details' => [
                                'method' => 'createChatInviteLink',
                                'status' => $status,
                                'is_admin' => $isAdmin
                            ]
                        ];
                    }
                }

                $errorData = $response->json();
                $errorMessage = $errorData['description'] ?? 'Erro ao criar link de convite';

                return [
                    'success' => false,
                    'invite_link' => null,
                    'error' => $errorMessage,
                    'details' => [
                        'status' => $status,
                        'is_admin' => $isAdmin,
                        'can_invite_users' => $canInviteUsers,
                        'response' => $errorData
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
        if ($chatType === 'private') {
            $this->saveOrUpdateContact($bot, $from);
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
            $this->processCommand($bot, $chatId, $from, $command);
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

        // Processa comandos em grupos (se o bot foi mencionado ou é comando direto)
        if ($text && str_starts_with($text, '/')) {
            $commandParts = explode(' ', $text);
            $command = $commandParts[0];
            
            // Obtém o username do bot do bot_info
            $botUsername = null;
            try {
                $botInfo = $this->validateToken($bot->token);
                if ($botInfo['valid'] && isset($botInfo['bot']['username'])) {
                    $botUsername = $botInfo['bot']['username'];
                }
            } catch (Exception $e) {
                // Se não conseguir obter, tenta usar o nome do bot como fallback
                LogFacade::warning('Não foi possível obter username do bot', [
                    'bot_id' => $bot->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Verifica se o comando menciona o bot
            $commandMentionsBot = false;
            if ($botUsername && str_contains($command, '@' . $botUsername)) {
                $commandMentionsBot = true;
            }
            
            // Verifica se há entities que indicam menção ao bot
            $botMentioned = false;
            if (isset($message['entities'])) {
                foreach ($message['entities'] as $entity) {
                    if (($entity['type'] ?? '') === 'bot_command') {
                        // Se o comando tem @username, verifica se é do nosso bot
                        if ($botUsername && isset($entity['user'])) {
                            // Entity pode ter user_id do bot mencionado
                            $mentionedBotId = $entity['user']['id'] ?? null;
                            if ($mentionedBotId) {
                                try {
                                    $botIdFromToken = $this->getBotIdFromToken($bot->token);
                                    if ($mentionedBotId == $botIdFromToken) {
                                        $botMentioned = true;
                                        break;
                                    }
                                } catch (Exception $e) {
                                    // Ignora erro
                                }
                            }
                        }
                        // Se não tem user na entity, verifica pelo texto
                        if (!$botMentioned && $botUsername) {
                            $entityText = substr($text, $entity['offset'] ?? 0, $entity['length'] ?? 0);
                            if (str_contains($entityText, '@' . $botUsername)) {
                                $botMentioned = true;
                                break;
                            }
                        }
                    }
                }
            }
            
            // Processa o comando se mencionar o bot ou se for um comando sem menção (em grupos, comandos sem @ são para todos os bots)
            // Mas vamos processar apenas se mencionar nosso bot especificamente
            if ($commandMentionsBot || $botMentioned) {
                // Remove o @username do comando antes de processar
                $cleanCommand = preg_replace('/@\w+/', '', $command);
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
    protected function processCommand(Bot $bot, int $chatId, array $from, string $command): void
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
        
        // Comandos padrão do sistema (verifica múltiplos formatos)
        if ($commandLower === '/start' || $commandNameLower === 'start' || 
            $command === '/start' || $command === 'start') {
            $this->logBotAction($bot, "Comando /start detectado, executando...", 'info');
            $this->handleStartCommand($bot, $chatId, $from);
            return;
        }
        
        if ($commandLower === '/help' || $commandLower === '/comandos' || 
            $commandNameLower === 'help' || $commandNameLower === 'comandos') {
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
            $this->logBotAction($bot, "Iniciando processamento do comando /start", 'info', [
                'chat_id' => $chatId,
                'user_id' => $from['id']
            ]);
            
            // Busca ou cria contato
            $contact = $this->saveOrUpdateContact($bot, $from);
            
            // Recarrega o contato para garantir que temos os dados mais recentes do banco
            $contact->refresh();

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

            $this->logBotAction($bot, "Enviando mensagem inicial", 'info');
            $this->sendMessage($bot, $chatId, $message, $keyboard);

            // Se o bot está configurado para solicitar dados, solicita após mensagem inicial
            // Verifica na ordem: email -> telefone -> idioma
            if ($bot->request_email && !$contact->email) {
                $this->logBotAction($bot, "Solicitando email", 'info');
                $this->sendMessage($bot, $chatId, 'Por favor, envie seu email:');
            } elseif ($bot->request_phone && !$contact->phone) {
                $this->logBotAction($bot, "Solicitando telefone", 'info');
                $this->sendMessage($bot, $chatId, 'Por favor, envie seu número de telefone:');
            } elseif ($bot->request_language && !$contact->language) {
                $this->logBotAction($bot, "Solicitando idioma", 'info');
                $this->sendMessage($bot, $chatId, 'Por favor, escolha um idioma (pt, en, es, fr):');
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
        if (!$text) {
            return;
        }

        // Busca contato para verificar se precisa coletar dados
        $contact = Contact::where('bot_id', $bot->id)
            ->where('telegram_id', $from['id'])
            ->first();

        // Se não existe contato, cria um
        if (!$contact) {
            $contact = $this->saveOrUpdateContact($bot, $from);
        }

        // Se o bot está configurado para solicitar email/telefone/idioma
        if ($contact) {
            // Verifica se precisa coletar email
            if ($bot->request_email && !$contact->email) {
                if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    $contact->email = $text;
                    $contact->save();
                    $this->sendMessage($bot, $chatId, '✅ Email registrado com sucesso!');
                    
                    // Recarrega o contato para ter os dados atualizados
                    $contact->refresh();
                    
                    // Verifica se ainda precisa coletar outros dados
                    if ($bot->request_phone && !$contact->phone) {
                        $this->sendMessage($bot, $chatId, 'Por favor, envie seu número de telefone:');
                        return;
                    } elseif ($bot->request_language && !$contact->language) {
                        $this->sendMessage($bot, $chatId, 'Por favor, escolha um idioma (pt, en, es, fr):');
                        return;
                    }
                    return;
                } else {
                    $this->sendMessage($bot, $chatId, '❌ Email inválido. Por favor, envie um email válido:');
                    return;
                }
            }

            // Verifica se precisa coletar telefone (apenas se já tem email ou não precisa de email)
            if ($bot->request_phone && !$contact->phone) {
                // Remove caracteres não numéricos
                $phone = preg_replace('/\D/', '', $text);
                if (strlen($phone) >= 10) {
                    $contact->phone = $phone;
                    $contact->save();
                    $this->sendMessage($bot, $chatId, '✅ Telefone registrado com sucesso!');
                    
                    // Recarrega o contato
                    $contact->refresh();
                    
                    // Verifica se ainda precisa coletar idioma
                    if ($bot->request_language && !$contact->language) {
                        $this->sendMessage($bot, $chatId, 'Por favor, escolha um idioma (pt, en, es, fr):');
                        return;
                    }
                    return;
                } else {
                    $this->sendMessage($bot, $chatId, '❌ Telefone inválido. Por favor, envie um número de telefone válido (mínimo 10 dígitos):');
                    return;
                }
            }

            // Verifica se precisa coletar idioma (apenas se já tem email/telefone ou não precisa deles)
            if ($bot->request_language && !$contact->language) {
                $validLanguages = ['pt', 'en', 'es', 'fr'];
                if (in_array(strtolower($text), $validLanguages)) {
                    $contact->language = strtolower($text);
                    $contact->save();
                    $this->sendMessage($bot, $chatId, '✅ Idioma registrado com sucesso!');
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

            // Registra comandos no Telegram
            $response = $this->http()->post("https://api.telegram.org/bot{$bot->token}/setMyCommands", [
                'commands' => json_encode($commands)
            ]);

            if ($response->successful() && $response->json()['ok']) {
                $this->logBotAction($bot, 'Comandos registrados no Telegram com sucesso', 'info', [
                    'commands_count' => count($commands)
                ]);
                return true;
            }

            return false;
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao registrar comandos: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Obtém lista de comandos registrados no Telegram
     *
     * @param Bot $bot
     * @return array
     */
    public function getMyCommands(Bot $bot): array
    {
        try {
            $response = $this->http()->get("https://api.telegram.org/bot{$bot->token}/getMyCommands");

            if ($response->successful() && $response->json()['ok']) {
                return $response->json()['result'] ?? [];
            }

            return [];
        } catch (Exception $e) {
            $this->logBotAction($bot, 'Erro ao obter comandos: ' . $e->getMessage(), 'error');
            return [];
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

    /**
     * Obtém lista de administradores do grupo
     *
     * @param string $token Token do bot
     * @param string $chatId ID do chat (grupo/canal)
     * @return array ['success' => bool, 'administrators' => array, 'error' => string|null]
     */
    public function getChatAdministrators(string $token, string $chatId): array
    {
        try {
            $normalizedChatId = $this->normalizeChatId($chatId);
            
            $response = $this->http()->get("https://api.telegram.org/bot{$token}/getChatAdministrators", [
                'chat_id' => $normalizedChatId
            ]);

            if (!$response->successful() || !$response->json()['ok']) {
                return [
                    'success' => false,
                    'administrators' => [],
                    'error' => 'Erro ao obter administradores: ' . ($response->json()['description'] ?? 'Erro desconhecido')
                ];
            }

            $administrators = $response->json()['result'] ?? [];
            
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

            if (!$response->successful() || !$response->json()['ok']) {
                return [
                    'success' => false,
                    'member_count' => 0,
                    'error' => 'Erro ao obter contagem de membros: ' . ($response->json()['description'] ?? 'Erro desconhecido')
                ];
            }

            $memberCount = $response->json()['result'] ?? 0;
            
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

