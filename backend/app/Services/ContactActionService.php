<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Contact;
use App\Models\ContactAction;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\Log;

class ContactActionService
{
    /**
     * Registra uma ação do contato
     *
     * @param Bot $bot
     * @param Contact $contact
     * @param string $actionType
     * @param string $action
     * @param string|null $description
     * @param array|null $metadata
     * @param string $status
     * @param Transaction|null $transaction
     * @return ContactAction
     */
    public function logAction(
        Bot $bot,
        Contact $contact,
        string $actionType,
        string $action,
        ?string $description = null,
        ?array $metadata = null,
        string $status = 'completed',
        ?Transaction $transaction = null
    ): ContactAction {
        try {
            return ContactAction::create([
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'transaction_id' => $transaction?->id,
                'action_type' => $actionType,
                'action' => $action,
                'description' => $description,
                'metadata' => $metadata,
                'status' => $status,
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao registrar ação do contato', [
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'action_type' => $actionType,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Registra comando executado
     */
    public function logCommand(Bot $bot, Contact $contact, string $command, ?array $metadata = null): ContactAction
    {
        return $this->logAction(
            $bot,
            $contact,
            'command',
            $command,
            "Comando /{$command} executado",
            $metadata
        );
    }

    /**
     * Registra seleção de plano
     */
    public function logPlanSelection(Bot $bot, Contact $contact, int $planId, string $planTitle, float $price): ContactAction
    {
        return $this->logAction(
            $bot,
            $contact,
            'payment',
            'plan_selected',
            "Plano selecionado: {$planTitle} - R$ " . number_format($price, 2, ',', '.'),
            [
                'plan_id' => $planId,
                'plan_title' => $planTitle,
                'price' => $price
            ]
        );
    }

    /**
     * Registra início de pagamento
     */
    public function logPaymentInitiated(
        Bot $bot,
        Contact $contact,
        string $method,
        int $planId,
        string $planTitle,
        float $amount,
        ?Transaction $transaction = null
    ): ContactAction {
        $methodLabel = $method === 'pix' ? 'PIX' : 'Cartão de Crédito';
        
        return $this->logAction(
            $bot,
            $contact,
            'payment',
            'payment_initiated',
            "Pagamento {$methodLabel} iniciado - {$planTitle} - R$ " . number_format($amount, 2, ',', '.'),
            [
                'payment_method' => $method,
                'plan_id' => $planId,
                'plan_title' => $planTitle,
                'amount' => $amount
            ],
            'pending',
            $transaction
        );
    }

    /**
     * Registra pagamento pendente
     */
    public function logPaymentPending(
        Bot $bot,
        Contact $contact,
        Transaction $transaction,
        string $method,
        ?string $pixKey = null,
        ?string $pixCode = null
    ): ContactAction {
        $methodLabel = $method === 'pix' ? 'PIX' : 'Cartão de Crédito';
        $description = "Pagamento {$methodLabel} pendente - R$ " . number_format($transaction->amount, 2, ',', '.');
        
        $metadata = [
            'payment_method' => $method,
            'plan_id' => $transaction->payment_plan_id,
            'amount' => $transaction->amount,
            'transaction_id' => $transaction->id,
            'gateway_transaction_id' => $transaction->gateway_transaction_id
        ];

        if ($pixKey) {
            $metadata['pix_key'] = $pixKey;
        }
        if ($pixCode) {
            $metadata['pix_code'] = $pixCode;
        }

        return $this->logAction(
            $bot,
            $contact,
            'payment',
            'payment_pending',
            $description,
            $metadata,
            'pending',
            $transaction
        );
    }

    /**
     * Registra coleta de dados
     */
    public function logDataCollection(Bot $bot, Contact $contact, string $dataType, string $value): ContactAction
    {
        $typeLabels = [
            'email' => 'E-mail',
            'phone' => 'Telefone',
            'language' => 'Idioma'
        ];

        $label = $typeLabels[$dataType] ?? ucfirst($dataType);

        return $this->logAction(
            $bot,
            $contact,
            'data_collection',
            "{$dataType}_collected",
            "{$label} coletado: {$value}",
            [
                'data_type' => $dataType,
                'value' => $value
            ]
        );
    }

    /**
     * Registra bloqueio/desbloqueio
     */
    public function logBlockAction(Bot $bot, Contact $contact, bool $blocked, ?string $reason = null): ContactAction
    {
        $action = $blocked ? 'blocked' : 'unblocked';
        $description = $blocked ? 'Contato bloqueado' : 'Contato desbloqueado';

        return $this->logAction(
            $bot,
            $contact,
            'moderation',
            $action,
            $description . ($reason ? " - Motivo: {$reason}" : ''),
            [
                'blocked' => $blocked,
                'reason' => $reason
            ]
        );
    }

    /**
     * Registra mensagem enviada
     */
    public function logMessageSent(Bot $bot, Contact $contact, string $messageType, ?string $content = null): ContactAction
    {
        return $this->logAction(
            $bot,
            $contact,
            'message',
            'message_sent',
            "Mensagem do tipo '{$messageType}' enviada",
            [
                'message_type' => $messageType,
                'content_preview' => $content ? substr($content, 0, 100) : null
            ]
        );
    }

    /**
     * Obtém histórico de ações de um contato
     *
     * @param Bot $bot
     * @param int $contactId
     * @param int|null $limit
     * @return array
     */
    public function getContactHistory(Bot $bot, int $contactId, ?int $limit = 100): array
    {
        $actions = ContactAction::where('bot_id', $bot->id)
            ->where('contact_id', $contactId)
            ->with(['transaction', 'transaction.paymentPlan'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $actions->map(function ($action) {
            return [
                'id' => $action->id,
                'action_type' => $action->action_type,
                'action' => $action->action,
                'description' => $action->description,
                'status' => $action->status,
                'metadata' => $action->metadata,
                'transaction' => $action->transaction ? [
                    'id' => $action->transaction->id,
                    'amount' => $action->transaction->amount,
                    'status' => $action->transaction->status,
                    'payment_method' => $action->transaction->payment_method,
                    'plan' => $action->transaction->paymentPlan ? [
                        'id' => $action->transaction->paymentPlan->id,
                        'title' => $action->transaction->paymentPlan->title
                    ] : null
                ] : null,
                'created_at' => $action->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $action->created_at->diffForHumans()
            ];
        })->toArray();
    }
}

