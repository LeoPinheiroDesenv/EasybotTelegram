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
            $message = $this->buildMemberAddedMessage($contact, $reason, $transaction);
            
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
     * @param Contact $contact
     * @param string|null $reason
     * @param Transaction|null $transaction
     * @return string
     */
    protected function buildMemberAddedMessage(
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
}

