<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Contact;
use App\Models\Bot;
use App\Models\PaymentPlan;
use App\Services\GroupManagementService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentStatusService
{
    protected $groupManagementService;
    protected $telegramService;

    public function __construct(
        GroupManagementService $groupManagementService,
        TelegramService $telegramService
    ) {
        $this->groupManagementService = $groupManagementService;
        $this->telegramService = $telegramService;
    }

    /**
     * Obtém o status de pagamento de um contato
     *
     * @param Contact $contact
     * @return array
     */
    public function getContactPaymentStatus(Contact $contact): array
    {
        // Busca a última transação aprovada do contato
        $lastApprovedTransaction = Transaction::where('contact_id', $contact->id)
            ->whereIn('status', ['approved', 'paid', 'completed'])
            ->orderBy('created_at', 'desc')
            ->with(['paymentPlan', 'paymentCycle', 'bot'])
            ->first();

        if (!$lastApprovedTransaction) {
            return [
                'has_active_payment' => false,
                'status' => 'no_payment',
                'message' => 'Nenhum pagamento ativo encontrado'
            ];
        }

        $paymentPlan = $lastApprovedTransaction->paymentPlan;
        $paymentCycle = $lastApprovedTransaction->paymentCycle;
        
        // Calcula data de expiração baseada na data de criação + dias do ciclo
        $expiresAt = Carbon::parse($lastApprovedTransaction->created_at)
            ->addDays($paymentCycle->days ?? 30);

        $now = Carbon::now();
        $isExpired = $now->greaterThan($expiresAt);
        // Arredonda o número de dias para o inteiro mais próximo
        $daysUntilExpiration = (int) round($now->diffInDays($expiresAt, false));
        $isExpiringSoon = $daysUntilExpiration <= 7 && $daysUntilExpiration >= 0;

        return [
            'has_active_payment' => !$isExpired,
            'status' => $isExpired ? 'expired' : ($isExpiringSoon ? 'expiring_soon' : 'active'),
            'transaction' => [
                'id' => $lastApprovedTransaction->id,
                'amount' => $lastApprovedTransaction->amount,
                'currency' => $lastApprovedTransaction->currency,
                'status' => $lastApprovedTransaction->status,
                'created_at' => $lastApprovedTransaction->created_at,
                'payment_method' => $lastApprovedTransaction->payment_method,
                'gateway' => $lastApprovedTransaction->gateway,
                'gateway_transaction_id' => $lastApprovedTransaction->gateway_transaction_id,
            ],
            'payment_plan' => [
                'id' => $paymentPlan->id,
                'title' => $paymentPlan->title,
                'price' => $paymentPlan->price,
            ],
            'payment_cycle' => [
                'id' => $paymentCycle->id,
                'name' => $paymentCycle->name,
                'days' => $paymentCycle->days,
            ],
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_at_formatted' => $expiresAt->format('d/m/Y H:i'),
            'is_expired' => $isExpired,
            'is_expiring_soon' => $isExpiringSoon,
            'days_until_expiration' => $daysUntilExpiration,
            'bot' => [
                'id' => $lastApprovedTransaction->bot->id,
                'name' => $lastApprovedTransaction->bot->name,
            ]
        ];
    }

    /**
     * Obtém status de pagamentos de todos os contatos de um bot
     *
     * @param Bot $bot
     * @return array
     */
    public function getBotPaymentStatuses(Bot $bot): array
    {
        $contacts = Contact::where('bot_id', $bot->id)
            ->with(['actions.transaction'])
            ->get();

        $statuses = [];
        foreach ($contacts as $contact) {
            $status = $this->getContactPaymentStatus($contact);
            $status['contact'] = [
                'id' => $contact->id,
                'telegram_id' => $contact->telegram_id,
                'username' => $contact->username,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
            ];
            $statuses[] = $status;
        }

        return $statuses;
    }

    /**
     * Verifica e processa pagamentos expirados
     *
     * @param Bot|null $bot Se fornecido, verifica apenas esse bot
     * @return array
     */
    public function checkAndProcessExpiredPayments(?Bot $bot = null): array
    {
        $query = Transaction::whereIn('status', ['approved', 'paid', 'completed'])
            ->with(['paymentPlan', 'paymentCycle', 'contact', 'bot']);

        if ($bot) {
            $query->where('bot_id', $bot->id);
        }

        $transactions = $query->get();
        $expired = [];
        $notified = [];
        $removed = [];

        foreach ($transactions as $transaction) {
            $paymentCycle = $transaction->paymentCycle;
            if (!$paymentCycle) {
                Log::warning('Transação sem ciclo de pagamento', [
                    'transaction_id' => $transaction->id
                ]);
                continue;
            }
            
            $expiresAt = Carbon::parse($transaction->created_at)
                ->addDays($paymentCycle->days ?? 30);

            if (Carbon::now()->greaterThan($expiresAt)) {
                // Pagamento expirado
                $expired[] = $transaction->id;

                // Atualiza status da transação apenas se ainda não estiver como expirado
                if ($transaction->status !== 'expired') {
                    $transaction->update(['status' => 'expired']);
                }

                // Remove do grupo se necessário
                $contact = $transaction->contact;
                $bot = $transaction->bot;

                if ($contact && $bot && !empty($bot->telegram_group_id)) {
                    try {
                        // Verifica se o contato ainda está no grupo
                        $memberStatus = $this->groupManagementService->checkMemberStatus($bot, $contact);
                        
                        if (($memberStatus['is_member'] ?? false) && ($memberStatus['success'] ?? false)) {
                            // Remove do grupo
                            $removeResult = $this->groupManagementService->removeMemberAfterPaymentExpiry($transaction);
                            if ($removeResult['success'] ?? false) {
                                $removed[] = $transaction->id;
                                Log::info('Usuário removido do grupo após expiração de pagamento', [
                                    'transaction_id' => $transaction->id,
                                    'contact_id' => $contact->id,
                                    'bot_id' => $bot->id
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Erro ao verificar/remover membro do grupo', [
                            'transaction_id' => $transaction->id,
                            'contact_id' => $contact->id ?? null,
                            'bot_id' => $bot->id ?? null,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Notifica o usuário
                if ($contact && $bot) {
                    try {
                        $this->notifyPaymentExpired($bot, $contact, $transaction);
                        $notified[] = $transaction->id;
                        Log::info('Notificação de pagamento expirado enviada', [
                            'transaction_id' => $transaction->id,
                            'contact_id' => $contact->id
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Erro ao notificar pagamento expirado', [
                            'transaction_id' => $transaction->id,
                            'contact_id' => $contact->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        return [
            'expired_count' => count($expired),
            'notified_count' => count($notified),
            'removed_count' => count($removed),
            'expired_transactions' => $expired,
        ];
    }

    /**
     * Verifica pagamentos próximos de expirar e notifica
     *
     * @param Bot|null $bot
     * @param int $daysBeforeExpiration
     * @return array
     */
    public function checkAndNotifyExpiringPayments(?Bot $bot = null, int $daysBeforeExpiration = 7): array
    {
        $query = Transaction::whereIn('status', ['approved', 'paid', 'completed'])
            ->with(['paymentPlan', 'paymentCycle', 'contact', 'bot']);

        if ($bot) {
            $query->where('bot_id', $bot->id);
        }

        $transactions = $query->get();
        $expiring = [];
        $notified = [];

        foreach ($transactions as $transaction) {
            $paymentCycle = $transaction->paymentCycle;
            $expiresAt = Carbon::parse($transaction->created_at)
                ->addDays($paymentCycle->days ?? 30);

            // Arredonda o número de dias para o inteiro mais próximo
            $daysUntilExpiration = (int) round(Carbon::now()->diffInDays($expiresAt, false));

            // Se está entre 1 e $daysBeforeExpiration dias para expirar
            if ($daysUntilExpiration > 0 && $daysUntilExpiration <= $daysBeforeExpiration) {
                $expiring[] = $transaction->id;

                // Verifica se já foi notificado (evita spam)
                $metadata = $transaction->metadata ?? [];
                $lastNotification = $metadata['expiration_notification_sent_at'] ?? null;

                // Notifica apenas uma vez por dia
                if (!$lastNotification || Carbon::parse($lastNotification)->lt(Carbon::now()->subDay())) {
                    try {
                        $this->notifyPaymentExpiringSoon($transaction->bot, $transaction->contact, $transaction, $daysUntilExpiration);
                        
                        // Marca como notificado
                        $transaction->update([
                            'metadata' => array_merge($metadata, [
                                'expiration_notification_sent_at' => Carbon::now()->toIso8601String()
                            ])
                        ]);
                        
                        $notified[] = $transaction->id;
                    } catch (\Exception $e) {
                        Log::error('Erro ao notificar pagamento próximo de expirar', [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        return [
            'expiring_count' => count($expiring),
            'notified_count' => count($notified),
            'expiring_transactions' => $expiring,
        ];
    }

    /**
     * Notifica usuário sobre pagamento expirado
     *
     * @param Bot $bot
     * @param Contact $contact
     * @param Transaction $transaction
     * @return void
     */
    protected function notifyPaymentExpired(Bot $bot, Contact $contact, Transaction $transaction): void
    {
        $paymentPlan = $transaction->paymentPlan;
        $message = "⚠️ <b>Pagamento Expirado</b>\n\n";
        $message .= "Olá {$contact->first_name},\n\n";
        $message .= "Seu pagamento do plano <b>{$paymentPlan->title}</b> expirou.\n\n";
        $message .= "Para continuar tendo acesso, por favor, efetue um novo pagamento.\n\n";
        $message .= "Use o comando /start para ver os planos disponíveis.";

        $this->telegramService->sendMessage($bot, $contact->telegram_id, $message);
    }

    /**
     * Notifica usuário sobre pagamento próximo de expirar
     *
     * @param Bot $bot
     * @param Contact $contact
     * @param Transaction $transaction
     * @param int $daysUntilExpiration
     * @return void
     */
    protected function notifyPaymentExpiringSoon(Bot $bot, Contact $contact, Transaction $transaction, int $daysUntilExpiration): void
    {
        $paymentPlan = $transaction->paymentPlan;
        $message = "⏰ <b>Lembrete de Pagamento</b>\n\n";
        $message .= "Olá {$contact->first_name},\n\n";
        $message .= "Seu pagamento do plano <b>{$paymentPlan->title}</b> expira em <b>{$daysUntilExpiration} dia(s)</b>.\n\n";
        $message .= "Para continuar tendo acesso, por favor, renove seu pagamento.\n\n";
        $message .= "Use o comando /start para ver os planos disponíveis.";

        $this->telegramService->sendMessage($bot, $contact->telegram_id, $message);
    }
}

