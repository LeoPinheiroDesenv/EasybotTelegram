<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotCommand;
use App\Models\Contact;
use App\Models\Log;
use App\Services\PaymentService;
use App\Services\ContactActionService;
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

            // IMPORTANTE: Só solicita dados pessoais em chats privados
            // Em grupos, não deve solicitar email, telefone ou idioma
            if ($isPrivateChat && $contact) {
                // Se o bot está configurado para solicitar dados, solicita após mensagem inicial
                // Verifica na ordem: email -> telefone -> idioma
                // IMPORTANTE: Sempre solicita email primeiro se configurado e não tiver
                if ($bot->request_email && !$contact->email) {
                    $this->logBotAction($bot, "Solicitando email", 'info');
                    $this->sendMessage($bot, $chatId, '📧 Por favor, envie seu email:');
                } elseif ($bot->request_phone && !$contact->phone) {
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
                } elseif ($bot->request_language && !$contact->language) {
                    $this->logBotAction($bot, "Solicitando idioma", 'info');
                    $this->sendMessage($bot, $chatId, '🌐 Por favor, escolha um idioma (pt, en, es, fr):');
                }
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
            $helpText .= "/planos - Ver planos de pagamento disponíveis\n\n";

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

            // Cria botões para métodos de pagamento
            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '💳 Pagar com PIX',
                            'callback_data' => "payment_{$planId}_pix"
                        ]
                    ],
                    [
                        [
                            'text' => '💳 Pagar com Cartão de Crédito',
                            'callback_data' => "payment_{$planId}_card"
                        ]
                    ]
                ]
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
                    $this->sendMessage($bot, $chatId, "❌ Erro ao gerar QR Code PIX. Por favor, tente novamente.");
                    $this->logBotAction($bot, "Erro ao gerar QR Code PIX", 'error', [
                        'chat_id' => $chatId,
                        'user_id' => $from['id'] ?? null,
                        'plan_id' => $planId,
                        'error' => $pixResult['error'] ?? 'Erro desconhecido'
                    ]);
                    return;
                }

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

                // Monta mensagem
                $message = "💳 <b>Pagamento via PIX</b>\n\n";
                $message .= "📦 <b>Plano:</b> {$plan->title}\n";
                $message .= "💰 <b>Valor:</b> R$ {$price}\n\n";

                if ($plan->pix_message) {
                    $message .= $plan->pix_message . "\n\n";
                }

                $message .= "📱 <b>Escaneie o QR Code abaixo para pagar:</b>\n\n";
                
                // Exibe código PIX apenas se disponível
                if (!empty($pixResult['pix_code'])) {
                    $message .= "📋 <b>Código PIX:</b>\n<code>{$pixResult['pix_code']}</code>\n\n";
                }
                
                $message .= "⏰ Este QR Code expira em 30 minutos.";

                // Envia mensagem com texto
                $this->sendMessage($bot, $chatId, $message);

                // Envia QR Code como imagem
                try {
                    // Verifica se o QR Code já está em base64 ou precisa ser decodificado
                    $qrCodeImageData = $pixResult['qr_code_image'];
                    if (is_string($qrCodeImageData) && !empty($qrCodeImageData)) {
                        // Tenta decodificar se for base64 válido
                        $decoded = base64_decode($qrCodeImageData, true);
                        if ($decoded !== false && base64_encode($decoded) === $qrCodeImageData) {
                            $qrCodeImageData = $decoded;
                        }
                    }
                    
                    $tempFile = tempnam(sys_get_temp_dir(), 'pix_qr_') . '.png';
                    file_put_contents($tempFile, $qrCodeImageData);

                    // Envia foto usando multipart/form-data
                    $response = $this->http()->asMultipart()
                        ->attach('photo', file_get_contents($tempFile), 'qrcode.png')
                        ->post("https://api.telegram.org/bot{$bot->token}/sendPhoto", [
                            'chat_id' => $chatId,
                            'caption' => "📱 QR Code PIX - {$plan->title} - R$ {$price}"
                        ]);

                    // Remove arquivo temporário
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }

                    if (!$response->successful()) {
                        throw new Exception('Erro ao enviar foto: ' . $response->body());
                    }
                } catch (Exception $e) {
                    $this->logBotAction($bot, "Erro ao enviar QR Code como imagem", 'warning', [
                        'chat_id' => $chatId,
                        'error' => $e->getMessage()
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
                    
                    // Mensagem de confirmação final
                    $this->sendMessage($bot, $chatId, '✅ Obrigado! Seus dados foram registrados com sucesso.');
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
                    
                    // Mensagem de confirmação final
                    $this->sendMessage($bot, $chatId, '✅ Obrigado! Seus dados foram registrados com sucesso.');
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
                    
                    // Mensagem de confirmação final
                    $this->sendMessage($bot, $chatId, '✅ Obrigado! Seus dados foram registrados com sucesso.');
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
                    'commands' => $commands
                ]);
                
                // Configura menu button para exibir os comandos
                // Isso garante que o botão "Menu" mostre todos os comandos registrados
                // IMPORTANTE: O menu só mostrará os comandos se eles estiverem registrados via setMyCommands
                $this->setPlansMenuButton($bot);
                
                return true;
            } else {
                $error = $response->json()['description'] ?? 'Erro desconhecido';
                $this->logBotAction($bot, 'Erro ao registrar comandos no Telegram: ' . $error, 'error', [
                    'response' => $response->json()
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

