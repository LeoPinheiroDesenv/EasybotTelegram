<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Contact;
use App\Models\Transaction;
use App\Models\Log;
use App\Services\TelegramService;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Facades\Log as LogFacade;

class GroupManagementService
{
    protected $telegramService;
    protected $notificationService;

    public function __construct(TelegramService $telegramService, NotificationService $notificationService)
    {
        $this->telegramService = $telegramService;
        $this->notificationService = $notificationService;
    }

    /**
     * Adiciona um contato ao grupo após pagamento confirmado
     *
     * @param Transaction $transaction
     * @return array
     */
    public function addMemberAfterPayment(Transaction $transaction): array
    {
        try {
            $bot = $transaction->bot;
            $contact = $transaction->contact;

            if (!$bot || !$contact) {
                return [
                    'success' => false,
                    'error' => 'Bot ou contato não encontrado na transação'
                ];
            }

            if (empty($bot->telegram_group_id)) {
                return [
                    'success' => false,
                    'error' => 'Bot não tem grupo configurado'
                ];
            }

            // Adiciona usuário ao grupo
            $result = $this->telegramService->addUserToGroup(
                $bot->token,
                $bot->telegram_group_id,
                $contact->telegram_id
            );

            if ($result['success']) {
                // Registra log
                $this->logGroupAction(
                    $bot,
                    $contact,
                    'add',
                    "Usuário adicionado ao grupo após pagamento confirmado. Transação: {$transaction->id}",
                    [
                        'transaction_id' => $transaction->id,
                        'payment_plan_id' => $transaction->payment_plan_id,
                        'amount' => $transaction->amount
                    ]
                );

                // Envia notificação
                $this->notificationService->notifyMemberAdded($bot, $contact, null, $transaction);

                return [
                    'success' => true,
                    'message' => 'Usuário adicionado ao grupo com sucesso'
                ];
            }

            return $result;
        } catch (Exception $e) {
            LogFacade::error('Erro ao adicionar membro após pagamento', [
                'transaction_id' => $transaction->id ?? null,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao adicionar membro: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove um contato do grupo quando pagamento expira ou é cancelado
     *
     * @param Transaction $transaction
     * @return array
     */
    public function removeMemberAfterPaymentExpiry(Transaction $transaction): array
    {
        try {
            $bot = $transaction->bot;
            $contact = $transaction->contact;

            if (!$bot || !$contact) {
                return [
                    'success' => false,
                    'error' => 'Bot ou contato não encontrado na transação'
                ];
            }

            if (empty($bot->telegram_group_id)) {
                return [
                    'success' => false,
                    'error' => 'Bot não tem grupo configurado'
                ];
            }

            // Remove usuário do grupo
            $result = $this->telegramService->removeUserFromGroup(
                $bot->token,
                $bot->telegram_group_id,
                $contact->telegram_id
            );

            if ($result['success']) {
                // Registra log
                $this->logGroupAction(
                    $bot,
                    $contact,
                    'remove',
                    "Usuário removido do grupo após expiração/cancelamento de pagamento. Transação: {$transaction->id}",
                    [
                        'transaction_id' => $transaction->id,
                        'payment_plan_id' => $transaction->payment_plan_id,
                        'reason' => 'payment_expired'
                    ]
                );

                // Envia notificação
                $this->notificationService->notifyMemberRemoved(
                    $bot,
                    $contact,
                    'Pagamento expirado ou cancelado',
                    $transaction
                );

                return [
                    'success' => true,
                    'message' => 'Usuário removido do grupo com sucesso'
                ];
            }

            return $result;
        } catch (Exception $e) {
            LogFacade::error('Erro ao remover membro após expiração de pagamento', [
                'transaction_id' => $transaction->id ?? null,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao remover membro: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Adiciona um membro manualmente ao grupo
     *
     * @param Bot $bot
     * @param Contact $contact
     * @param string|null $reason
     * @return array
     */
    public function addMemberManually(Bot $bot, Contact $contact, ?string $reason = null): array
    {
        try {
            if (empty($bot->telegram_group_id)) {
                return [
                    'success' => false,
                    'error' => 'Bot não tem grupo configurado'
                ];
            }

            $result = $this->telegramService->addUserToGroup(
                $bot->token,
                $bot->telegram_group_id,
                $contact->telegram_id
            );

            if ($result['success']) {
                $this->logGroupAction(
                    $bot,
                    $contact,
                    'add',
                    "Usuário adicionado manualmente ao grupo" . ($reason ? ". Motivo: {$reason}" : ''),
                    [
                        'reason' => $reason ?? 'manual',
                        'action_type' => 'manual'
                    ]
                );

                // Envia notificação
                $this->notificationService->notifyMemberAdded($bot, $contact, $reason);
            }

            return $result;
        } catch (Exception $e) {
            LogFacade::error('Erro ao adicionar membro manualmente', [
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao adicionar membro: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove um membro manualmente do grupo
     *
     * @param Bot $bot
     * @param Contact $contact
     * @param string|null $reason
     * @return array
     */
    public function removeMemberManually(Bot $bot, Contact $contact, ?string $reason = null): array
    {
        try {
            if (empty($bot->telegram_group_id)) {
                return [
                    'success' => false,
                    'error' => 'Bot não tem grupo configurado'
                ];
            }

            $result = $this->telegramService->removeUserFromGroup(
                $bot->token,
                $bot->telegram_group_id,
                $contact->telegram_id
            );

            if ($result['success']) {
                $this->logGroupAction(
                    $bot,
                    $contact,
                    'remove',
                    "Usuário removido manualmente do grupo" . ($reason ? ". Motivo: {$reason}" : ''),
                    [
                        'reason' => $reason ?? 'manual',
                        'action_type' => 'manual'
                    ]
                );

                // Envia notificação
                $this->notificationService->notifyMemberRemoved($bot, $contact, $reason);
            }

            return $result;
        } catch (Exception $e) {
            LogFacade::error('Erro ao remover membro manualmente', [
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao remover membro: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verifica se um contato é membro do grupo
     *
     * @param Bot $bot
     * @param Contact $contact
     * @return array
     */
    public function checkMemberStatus(Bot $bot, Contact $contact): array
    {
        try {
            if (empty($bot->telegram_group_id)) {
                return [
                    'success' => false,
                    'error' => 'Bot não tem grupo configurado'
                ];
            }

            $botInfo = $this->telegramService->validateToken($bot->token);
            if (!$botInfo['valid']) {
                return [
                    'success' => false,
                    'error' => 'Token do bot inválido'
                ];
            }

            $memberInfo = $this->telegramService->getChatMember(
                $bot->token,
                $bot->telegram_group_id,
                $contact->telegram_id
            );

            return [
                'success' => true,
                'is_member' => $memberInfo['is_member'] ?? false,
                'status' => $memberInfo['status'] ?? 'unknown',
                'member_info' => $memberInfo
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao verificar status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lista todos os membros do grupo (limitado pela API do Telegram)
     *
     * @param Bot $bot
     * @return array
     */
    public function listGroupMembers(Bot $bot): array
    {
        try {
            if (empty($bot->telegram_group_id)) {
                return [
                    'success' => false,
                    'error' => 'Bot não tem grupo configurado'
                ];
            }

            // A API do Telegram não fornece um método direto para listar todos os membros
            // Retornamos informações do grupo e sugestão de usar getChatMember para membros específicos
            $groupValidation = $this->telegramService->validateGroup($bot->token, $bot->telegram_group_id);

            if (!$groupValidation['valid']) {
                return [
                    'success' => false,
                    'error' => $groupValidation['error'] ?? 'Grupo inválido'
                ];
            }

            return [
                'success' => true,
                'group_info' => $groupValidation['group_info'],
                'note' => 'Para verificar membros específicos, use checkMemberStatus com o contact_id'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao listar membros: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Registra uma ação de gerenciamento de grupo
     *
     * @param Bot $bot
     * @param Contact $contact
     * @param string $action 'add' ou 'remove'
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function logGroupAction(
        Bot $bot,
        Contact $contact,
        string $action,
        string $message,
        array $context = []
    ): void {
        try {
            Log::create([
                'bot_id' => $bot->id,
                'level' => 'info',
                'message' => $message,
                'context' => array_merge($context, [
                    'action' => $action,
                    'contact_id' => $contact->id,
                    'contact_telegram_id' => $contact->telegram_id,
                    'contact_username' => $contact->username,
                    'group_id' => $bot->telegram_group_id
                ]),
                'user_email' => auth()->user()->email ?? null,
                'ip_address' => request()->ip()
            ]);
        } catch (Exception $e) {
            LogFacade::error('Erro ao salvar log de ação de grupo', [
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

