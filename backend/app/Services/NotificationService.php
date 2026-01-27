<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Contact;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\Log as LogFacade;

class NotificationService
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Envia notificação quando membro é adicionado ao grupo
     *
     * @param Bot $bot
     * @param Contact $contact
     * @param string|null $reason
     * @param Transaction|null $transaction
     * @return bool
     */
    public function notifyMemberAdded(
        Bot $bot,
        Contact $contact,
        ?string $reason = null,
        ?Transaction $transaction = null
    ): bool {
        try {
            $message = $this->buildMemberAddedMessage($bot, $contact, $reason, $transaction);
            
            // Envia mensagem privada ao usuário
            $this->telegramService->sendMessage($bot, $contact->telegram_id, $message);
            
            LogFacade::info('Notificação de membro adicionado enviada', [
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'telegram_id' => $contact->telegram_id
            ]);
            
            return true;
        } catch (Exception $e) {
            LogFacade::error('Erro ao enviar notificação de membro adicionado', [
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Envia notificação quando membro é removido do grupo
     *
     * @param Bot $bot
     * @param Contact $contact
     * @param string|null $reason
     * @param Transaction|null $transaction
     * @return bool
     */
    public function notifyMemberRemoved(
        Bot $bot,
        Contact $contact,
        ?string $reason = null,
        ?Transaction $transaction = null
    ): bool {
        try {
            $message = $this->buildMemberRemovedMessage($contact, $reason, $transaction);
            
            // Envia mensagem privada ao usuário
            $this->telegramService->sendMessage($bot, $contact->telegram_id, $message);
            
            LogFacade::info('Notificação de membro removido enviada', [
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'telegram_id' => $contact->telegram_id
            ]);
            
            return true;
        } catch (Exception $e) {
            LogFacade::error('Erro ao enviar notificação de membro removido', [
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Constrói mensagem de membro adicionado
     *
     * @param Bot $bot
     * @param Contact $contact
     * @param string|null $reason
     * @param Transaction|null $transaction
     * @return string
     */
    protected function buildMemberAddedMessage(
        Bot $bot,
        Contact $contact,
        ?string $reason = null,
        ?Transaction $transaction = null
    ): string {
        $name = $contact->first_name ?? $contact->username ?? 'Usuário';
        
        $message = "🎉 <b>Bem-vindo ao grupo!</b>\n\n";
        $message .= "Olá, {$name}!\n\n";
        $message .= "Você foi adicionado ao grupo com sucesso.\n";
        
        if ($transaction) {
            $message .= "\n📋 <b>Informações do pagamento:</b>\n";
            $message .= "• Valor: R$ " . number_format($transaction->amount, 2, ',', '.') . "\n";
            $message .= "• Status: " . ucfirst($transaction->status) . "\n";
        }
        
        if ($reason) {
            $message .= "\n📝 <b>Motivo:</b> {$reason}\n";
        }
        
        // Busca o link do grupo para incluir na mensagem
        $groupLink = $this->findGroupInviteLink($bot, $transaction);
        if ($groupLink) {
            $message .= "\n🔗 <b>Acesse nosso grupo exclusivo:</b>\n";
            $message .= "{$groupLink}\n";
        }
        
        $message .= "\nAproveite o acesso ao grupo!";
        
        return $message;
    }

    /**
     * Constrói mensagem de membro removido
     *
     * @param Contact $contact
     * @param string|null $reason
     * @param Transaction|null $transaction
     * @return string
     */
    protected function buildMemberRemovedMessage(
        Contact $contact,
        ?string $reason = null,
        ?Transaction $transaction = null
    ): string {
        $name = $contact->first_name ?? $contact->username ?? 'Usuário';
        
        $message = "⚠️ <b>Acesso ao grupo removido</b>\n\n";
        $message .= "Olá, {$name}!\n\n";
        $message .= "Seu acesso ao grupo foi removido.\n";
        
        if ($transaction) {
            $message .= "\n📋 <b>Informações:</b>\n";
            $message .= "• Status do pagamento: " . ucfirst($transaction->status) . "\n";
            
            if (in_array($transaction->status, ['expired', 'cancelled', 'refunded'])) {
                $message .= "• Motivo: Pagamento " . ucfirst($transaction->status) . "\n";
            }
        }
        
        if ($reason) {
            $message .= "\n📝 <b>Motivo:</b> {$reason}\n";
        }
        
        $message .= "\nPara recuperar o acesso, realize um novo pagamento.";
        
        return $message;
    }

    /**
     * Busca o link de convite do grupo usando múltiplas estratégias
     *
     * @param Bot $bot
     * @param Transaction|null $transaction
     * @return string|null
     */
    protected function findGroupInviteLink(Bot $bot, ?Transaction $transaction = null): ?string
    {
        try {
            // ESTRATÉGIA 1: Se há transação, busca grupo associado ao plano de pagamento
            if ($transaction) {
                // Garante que o relacionamento paymentPlan está carregado
                if (!$transaction->relationLoaded('paymentPlan')) {
                    $transaction->load('paymentPlan');
                }
                
                if ($transaction->paymentPlan) {
                    $telegramGroup = \App\Models\TelegramGroup::where('bot_id', $bot->id)
                        ->where('payment_plan_id', $transaction->paymentPlan->id)
                        ->where('active', true)
                        ->first();
                    
                    if ($telegramGroup) {
                        $groupLink = $this->getLinkFromGroup($telegramGroup, $bot);
                        if ($groupLink) {
                            return $groupLink;
                        }
                    }
                }
            }
            
            // ESTRATÉGIA 2: Busca qualquer grupo ativo do bot (prioriza grupos com link salvo)
            $anyGroupWithLink = \App\Models\TelegramGroup::where('bot_id', $bot->id)
                ->where('active', true)
                ->whereNotNull('invite_link')
                ->orderBy('updated_at', 'desc')
                ->first();
            
            if ($anyGroupWithLink && $anyGroupWithLink->invite_link) {
                return $anyGroupWithLink->invite_link;
            }
            
            // ESTRATÉGIA 3: Busca qualquer grupo ativo do bot
            $anyGroup = \App\Models\TelegramGroup::where('bot_id', $bot->id)
                ->where('active', true)
                ->whereNotNull('telegram_group_id')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($anyGroup) {
                $groupLink = $this->getLinkFromGroup($anyGroup, $bot);
                if ($groupLink) {
                    return $groupLink;
                }
            }
            
            // ESTRATÉGIA 4: Usa o grupo do bot (telegram_group_id do modelo Bot)
            if (!empty($bot->telegram_group_id)) {
                $groupLink = $this->getLinkFromTelegramId($bot->telegram_group_id, $bot);
                if ($groupLink) {
                    return $groupLink;
                }
            }
            
            // ESTRATÉGIA 5: Busca grupos inativos também (última tentativa)
            $inactiveGroup = \App\Models\TelegramGroup::where('bot_id', $bot->id)
                ->whereNotNull('telegram_group_id')
                ->whereNotNull('invite_link')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($inactiveGroup && $inactiveGroup->invite_link) {
                return $inactiveGroup->invite_link;
            }
            
            return null;
        } catch (Exception $e) {
            LogFacade::error('Erro ao buscar link do grupo', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtém o link de um grupo Telegram usando múltiplas estratégias
     *
     * @param \App\Models\TelegramGroup $telegramGroup
     * @param Bot $bot
     * @return string|null
     */
    protected function getLinkFromGroup(\App\Models\TelegramGroup $telegramGroup, Bot $bot): ?string
    {
        // CRÍTICO: Sempre obtém um link FRESCO via API para evitar links expirados
        
        // Estratégia 1: Gerar link para grupos com username (@) - sempre válido
        if ($telegramGroup->telegram_group_id && str_starts_with($telegramGroup->telegram_group_id, '@')) {
            $generatedLink = $telegramGroup->generateInviteLink();
            if ($generatedLink) {
                // Salva o link gerado
                try {
                    $telegramGroup->update(['invite_link' => $generatedLink]);
                } catch (\Exception $e) {
                    LogFacade::warning('Erro ao salvar link gerado, mas retornando mesmo assim', [
                        'bot_id' => $bot->id,
                        'group_id' => $telegramGroup->id,
                        'error' => $e->getMessage()
                    ]);
                }
                return $generatedLink;
            }
        }
        
        // Estratégia 2: Obter link FRESCO via API do Telegram - CRÍTICO: Sempre tenta obter novo link
        if ($telegramGroup->telegram_group_id) {
            try {
                $botInfo = $this->telegramService->validateToken($bot->token);
                $botIdForLink = $botInfo['valid'] && isset($botInfo['bot']['id']) ? $botInfo['bot']['id'] : null;
                
                // Tenta criar um link NOVO primeiro (garante que não está expirado)
                $freshLink = $this->getFreshInviteLink(
                    $bot->token,
                    $telegramGroup->telegram_group_id,
                    $botIdForLink,
                    $bot->id,
                    $telegramGroup->id
                );
                
                if ($freshLink) {
                    // Salva o link novo no banco
                    try {
                        $telegramGroup->update(['invite_link' => $freshLink]);
                    } catch (\Exception $e) {
                        LogFacade::warning('Erro ao salvar link obtido via API, mas retornando mesmo assim', [
                            'bot_id' => $bot->id,
                            'group_id' => $telegramGroup->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                    return $freshLink;
                }
                
                // Se não conseguiu criar novo, tenta obter link existente
                $linkResult = $this->telegramService->getChatInviteLink(
                    $bot->token,
                    $telegramGroup->telegram_group_id,
                    $botIdForLink
                );
                
                if ($linkResult['success'] && $linkResult['invite_link']) {
                    $link = $linkResult['invite_link'];
                    // Salva o link no banco para uso futuro
                    try {
                        $telegramGroup->update(['invite_link' => $link]);
                    } catch (\Exception $e) {
                        LogFacade::warning('Erro ao salvar link obtido via API', [
                            'bot_id' => $bot->id,
                            'group_id' => $telegramGroup->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                    return $link;
                }
            } catch (\Exception $e) {
                LogFacade::error('Erro ao obter link via API', [
                    'bot_id' => $bot->id,
                    'group_id' => $telegramGroup->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // NUNCA usa link salvo no banco - sempre obtém link novo para evitar links expirados
        LogFacade::warning('Não foi possível obter link novo via API', [
            'bot_id' => $bot->id,
            'group_id' => $telegramGroup->id
        ]);
        
        return null;
    }

    /**
     * Obtém um link FRESCO de convite via API do Telegram
     * Prioriza createChatInviteLink (cria novo link) ao invés de exportChatInviteLink (pode retornar link expirado)
     *
     * @param string $token
     * @param string $chatId
     * @param int|null $botId
     * @param int $botIdForLog
     * @param int $groupId
     * @return string|null
     */
    protected function getFreshInviteLink(
        string $token,
        string $chatId,
        ?int $botId,
        int $botIdForLog,
        int $groupId
    ): ?string {
        try {
            // CRÍTICO: Estratégia 1 - SEMPRE tenta criar um link NOVO via createChatInviteLink
            // Isso garante que o link não está expirado e é válido
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(30)
                    ->retry(2, 1000)
                    ->post("https://api.telegram.org/bot{$token}/createChatInviteLink", [
                        'chat_id' => $chatId,
                        'creates_join_request' => false,
                        'name' => 'Link automático - ' . now()->format('Y-m-d H:i:s'),
                        'expire_date' => null, // Sem data de expiração
                        'member_limit' => null // Sem limite de membros
                    ]);

                $responseData = $response->json() ?? [];
                
                if ($response->successful() && ($responseData['ok'] ?? false) && isset($responseData['result']['invite_link'])) {
                    $freshLink = $responseData['result']['invite_link'];
                    
                    // Valida o link antes de retornar
                    if ($this->validateInviteLink($freshLink, $token, $chatId)) {
                        LogFacade::info('✅ Link FRESCO criado e VALIDADO via createChatInviteLink', [
                            'bot_id' => $botIdForLog,
                            'group_id' => $groupId,
                            'invite_link' => $freshLink
                        ]);
                        return $freshLink;
                    } else {
                        LogFacade::warning('Link criado mas falhou na validação, tentando novamente', [
                            'bot_id' => $botIdForLog,
                            'group_id' => $groupId
                        ]);
                    }
                } else {
                    $errorMsg = $responseData['description'] ?? 'Erro desconhecido';
                    $errorCode = $responseData['error_code'] ?? null;
                    LogFacade::warning('Falha ao criar link novo via createChatInviteLink, tentando exportChatInviteLink', [
                        'bot_id' => $botIdForLog,
                        'group_id' => $groupId,
                        'error' => $errorMsg,
                        'error_code' => $errorCode
                    ]);
                }
            } catch (\Exception $e) {
                LogFacade::warning('Exceção ao criar link novo via createChatInviteLink, tentando exportChatInviteLink', [
                    'bot_id' => $botIdForLog,
                    'group_id' => $groupId,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Estratégia 2: Se não conseguiu criar novo, tenta obter link existente via exportChatInviteLink
            // Mas ainda valida antes de retornar
            try {
                $linkResult = $this->telegramService->getChatInviteLink($token, $chatId, $botId);
                
                if ($linkResult['success'] && !empty($linkResult['invite_link'])) {
                    $link = $linkResult['invite_link'];
                    
                    // Valida o link antes de retornar
                    if ($this->validateInviteLink($link, $token, $chatId)) {
                        LogFacade::info('✅ Link obtido e VALIDADO via exportChatInviteLink', [
                            'bot_id' => $botIdForLog,
                            'group_id' => $groupId,
                            'invite_link' => $link,
                            'method' => $linkResult['details']['method'] ?? 'unknown'
                        ]);
                        return $link;
                    } else {
                        LogFacade::warning('Link obtido via exportChatInviteLink mas falhou na validação', [
                            'bot_id' => $botIdForLog,
                            'group_id' => $groupId
                        ]);
                    }
                }
            } catch (\Exception $e) {
                LogFacade::error('Erro ao obter link via exportChatInviteLink', [
                    'bot_id' => $botIdForLog,
                    'group_id' => $groupId,
                    'error' => $e->getMessage()
                ]);
            }
            
            return null;
        } catch (\Exception $e) {
            LogFacade::error('Exceção ao obter link fresco', [
                'bot_id' => $botIdForLog,
                'group_id' => $groupId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Valida se um link de convite está válido e não expirado
     * Tenta obter informações do link via API do Telegram
     *
     * @param string $inviteLink
     * @param string $token
     * @param string $chatId
     * @return bool
     */
    protected function validateInviteLink(string $inviteLink, string $token, string $chatId): bool
    {
        try {
            // Extrai o invite_hash do link
            // Formato: https://t.me/joinchat/INVITE_HASH ou https://t.me/+INVITE_HASH
            $inviteHash = null;
            
            if (preg_match('/joinchat\/([a-zA-Z0-9_-]+)/', $inviteLink, $matches)) {
                $inviteHash = $matches[1];
            } elseif (preg_match('/\+([a-zA-Z0-9_-]+)/', $inviteLink, $matches)) {
                $inviteHash = $matches[1];
            }
            
            if (!$inviteHash) {
                // Se não tem hash, pode ser um link de username (@grupo) que sempre é válido
                if (str_contains($inviteLink, 't.me/') && !str_contains($inviteLink, 'joinchat') && !str_contains($inviteLink, '+')) {
                    return true; // Links de username são sempre válidos
                }
                LogFacade::warning('Não foi possível extrair invite_hash do link', [
                    'invite_link' => $inviteLink
                ]);
                return false;
            }
            
            // Tenta obter informações do link via getChatInviteLink
            // Se conseguir, o link é válido
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(10)
                    ->get("https://api.telegram.org/bot{$token}/getChatInviteLink", [
                        'chat_id' => $chatId,
                        'invite_link' => $inviteLink
                    ]);
                
                $responseData = $response->json() ?? [];
                
                if ($response->successful() && ($responseData['ok'] ?? false)) {
                    $inviteLinkInfo = $responseData['result'] ?? [];
                    
                    // Verifica se o link está expirado
                    if (isset($inviteLinkInfo['expire_date']) && $inviteLinkInfo['expire_date']) {
                        $expireDate = \Carbon\Carbon::createFromTimestamp($inviteLinkInfo['expire_date']);
                        if (now()->greaterThan($expireDate)) {
                            LogFacade::warning('Link está expirado', [
                                'invite_link' => $inviteLink,
                                'expire_date' => $expireDate->toDateTimeString()
                            ]);
                            return false;
                        }
                    }
                    
                    // Verifica se atingiu o limite de membros
                    if (isset($inviteLinkInfo['member_limit']) && $inviteLinkInfo['member_limit']) {
                        $memberCount = $inviteLinkInfo['member_count'] ?? 0;
                        if ($memberCount >= $inviteLinkInfo['member_limit']) {
                            LogFacade::warning('Link atingiu limite de membros', [
                                'invite_link' => $inviteLink,
                                'member_count' => $memberCount,
                                'member_limit' => $inviteLinkInfo['member_limit']
                            ]);
                            return false;
                        }
                    }
                    
                    // Se chegou aqui, o link é válido
                    LogFacade::info('Link validado com sucesso', [
                        'invite_link' => $inviteLink,
                        'is_creates_join_request' => $inviteLinkInfo['creates_join_request'] ?? false,
                        'is_primary' => $inviteLinkInfo['is_primary'] ?? false
                    ]);
                    return true;
                } else {
                    $errorMsg = $responseData['description'] ?? 'Erro desconhecido';
                    LogFacade::warning('Falha ao validar link - pode estar expirado ou inválido', [
                        'invite_link' => $inviteLink,
                        'error' => $errorMsg
                    ]);
                    return false;
                }
            } catch (\Exception $e) {
                LogFacade::warning('Exceção ao validar link, assumindo que é válido', [
                    'invite_link' => $inviteLink,
                    'error' => $e->getMessage()
                ]);
                // Em caso de erro na validação, assume que o link é válido
                // (melhor enviar um link que pode estar válido do que não enviar nada)
                return true;
            }
        } catch (\Exception $e) {
            LogFacade::error('Erro ao validar link de convite', [
                'invite_link' => $inviteLink,
                'error' => $e->getMessage()
            ]);
            // Em caso de erro, assume que o link é válido
            return true;
        }
    }

    /**
     * Obtém o link de um grupo usando apenas o telegram_group_id
     *
     * @param string $telegramGroupId
     * @param Bot $bot
     * @return string|null
     */
    protected function getLinkFromTelegramId(string $telegramGroupId, Bot $bot): ?string
    {
        try {
            // Se começa com @, gera link direto
            if (str_starts_with($telegramGroupId, '@')) {
                return 'https://t.me/' . ltrim($telegramGroupId, '@');
            }
            
            // Tenta obter via API
            $botInfo = $this->telegramService->validateToken($bot->token);
            $botIdForLink = $botInfo['valid'] && isset($botInfo['bot']['id']) ? $botInfo['bot']['id'] : null;
            
            $linkResult = $this->telegramService->getChatInviteLink(
                $bot->token,
                $telegramGroupId,
                $botIdForLink
            );
            
            if ($linkResult['success'] && $linkResult['invite_link']) {
                return $linkResult['invite_link'];
            }
        } catch (\Exception $e) {
            LogFacade::error('Erro ao obter link do grupo via ID', [
                'bot_id' => $bot->id,
                'telegram_group_id' => $telegramGroupId,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
}

