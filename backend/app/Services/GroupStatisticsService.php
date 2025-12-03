<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Log;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class GroupStatisticsService
{
    /**
     * Obtém estatísticas de gerenciamento de grupo para um bot
     *
     * @param Bot $bot
     * @param int|null $days Número de dias para análise (padrão: 30)
     * @return array
     */
    public function getGroupStatistics(Bot $bot, ?int $days = 30): array
    {
        $startDate = now()->subDays($days);

        // Estatísticas de ações de grupo
        $groupActions = Log::where('bot_id', $bot->id)
            ->where('created_at', '>=', $startDate)
            ->where(function ($query) {
                $query->where('message', 'like', '%adicionado%grupo%')
                    ->orWhere('message', 'like', '%removido%grupo%');
            })
            ->get();

        $additions = $groupActions->filter(function ($log) {
            return str_contains(strtolower($log->message), 'adicionado');
        })->count();

        $removals = $groupActions->filter(function ($log) {
            return str_contains(strtolower($log->message), 'removido');
        })->count();

        // Estatísticas por tipo de ação
        $manualActions = $groupActions->filter(function ($log) {
            $context = $log->context ?? [];
            return ($context['action_type'] ?? null) === 'manual';
        })->count();

        $automaticActions = $groupActions->count() - $manualActions;

        // Estatísticas por motivo
        $reasons = [];
        foreach ($groupActions as $log) {
            $context = $log->context ?? [];
            $reason = $context['reason'] ?? 'sem motivo';
            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
        }

        // Estatísticas de transações relacionadas
        $transactions = Transaction::where('bot_id', $bot->id)
            ->where('created_at', '>=', $startDate)
            ->get();

        $paidTransactions = $transactions->whereIn('status', ['approved', 'paid', 'completed'])->count();
        $expiredTransactions = $transactions->whereIn('status', ['expired', 'cancelled', 'refunded'])->count();

        // Estatísticas diárias (últimos 7 dias)
        $dailyStats = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayStart = now()->subDays($i)->startOfDay();
            $dayEnd = now()->subDays($i)->endOfDay();

            $dayAdditions = Log::where('bot_id', $bot->id)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('message', 'like', '%adicionado%grupo%')
                ->count();

            $dayRemovals = Log::where('bot_id', $bot->id)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('message', 'like', '%removido%grupo%')
                ->count();

            $dailyStats[] = [
                'date' => $date,
                'additions' => $dayAdditions,
                'removals' => $dayRemovals,
                'total' => $dayAdditions + $dayRemovals
            ];
        }

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => now()->format('Y-m-d'),
                'days' => $days
            ],
            'summary' => [
                'total_additions' => $additions,
                'total_removals' => $removals,
                'net_change' => $additions - $removals,
                'manual_actions' => $manualActions,
                'automatic_actions' => $automaticActions,
                'paid_transactions' => $paidTransactions,
                'expired_transactions' => $expiredTransactions
            ],
            'reasons' => $reasons,
            'daily_stats' => $dailyStats
        ];
    }

    /**
     * Obtém histórico de ações de gerenciamento para um contato
     *
     * @param Bot $bot
     * @param int $contactId
     * @return array
     */
    public function getContactHistory(Bot $bot, int $contactId): array
    {
        $contact = \App\Models\Contact::where('id', $contactId)
            ->where('bot_id', $bot->id)
            ->first();

        if (!$contact) {
            return [];
        }

        // Busca ações registradas do contato
        $contactActionService = new \App\Services\ContactActionService();
        $contactActions = $contactActionService->getContactHistory($bot, $contactId);

        // Busca logs antigos relacionados a grupos (para compatibilidade)
        $logs = Log::where('bot_id', $bot->id)
            ->where(function ($query) {
                $query->where('message', 'like', '%adicionado%grupo%')
                    ->orWhere('message', 'like', '%removido%grupo%');
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(function ($log) use ($contact) {
                $context = $log->context ?? [];
                $logContactId = $context['contact_id'] ?? null;
                $logTelegramId = $context['contact_telegram_id'] ?? null;
                
                return $logContactId == $contact->id || $logTelegramId == $contact->telegram_id;
            });

        $history = [];
        
        // Adiciona ações registradas
        foreach ($contactActions as $action) {
            $history[] = [
                'id' => $action['id'],
                'action' => $action['action'],
                'action_type' => $action['action_type'],
                'action_label' => $this->getActionLabel($action['action_type'], $action['action']),
                'description' => $action['description'],
                'status' => $action['status'],
                'metadata' => $action['metadata'],
                'transaction' => $action['transaction'],
                'performed_by' => 'Usuário',
                'created_at' => $action['created_at'],
                'created_at_human' => $action['created_at_human']
            ];
        }

        // Adiciona logs antigos de grupos (para compatibilidade)
        foreach ($logs as $log) {
            $context = $log->context ?? [];
            $isAdd = str_contains(strtolower($log->message), 'adicionado');
            
            $history[] = [
                'id' => 'log_' . $log->id,
                'action' => $isAdd ? 'add' : 'remove',
                'action_type' => 'group_management',
                'action_label' => $isAdd ? 'Adicionado ao Grupo' : 'Removido do Grupo',
                'description' => $log->message,
                'status' => 'completed',
                'metadata' => [
                    'reason' => $context['reason'] ?? null,
                    'action_type' => $context['action_type'] ?? 'automatic',
                    'transaction_id' => $context['transaction_id'] ?? null
                ],
                'transaction' => null,
                'performed_by' => $log->user_email ?? 'Sistema',
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $log->created_at->diffForHumans()
            ];
        }

        // Ordena por data (mais recente primeiro)
        usort($history, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return [
            'contact' => [
                'id' => $contact->id,
                'telegram_id' => $contact->telegram_id,
                'name' => $contact->first_name ?? $contact->username ?? 'Sem nome',
                'username' => $contact->username
            ],
            'history' => $history,
            'total_actions' => count($history)
        ];
    }

    /**
     * Retorna label legível para ação
     */
    protected function getActionLabel(string $actionType, string $action): string
    {
        $labels = [
            'command' => [
                'start' => 'Comando /start',
                'help' => 'Comando /help',
                'planos' => 'Comando /planos',
                'unknown' => 'Comando desconhecido'
            ],
            'payment' => [
                'plan_selected' => 'Plano Selecionado',
                'payment_initiated' => 'Pagamento Iniciado',
                'payment_pending' => 'Pagamento Pendente',
                'payment_completed' => 'Pagamento Concluído',
                'payment_failed' => 'Pagamento Falhou'
            ],
            'data_collection' => [
                'email_collected' => 'E-mail Coletado',
                'phone_collected' => 'Telefone Coletado',
                'language_collected' => 'Idioma Coletado'
            ],
            'moderation' => [
                'blocked' => 'Contato Bloqueado',
                'unblocked' => 'Contato Desbloqueado'
            ],
            'message' => [
                'message_sent' => 'Mensagem Enviada'
            ]
        ];

        return $labels[$actionType][$action] ?? ucfirst(str_replace('_', ' ', $action));
    }
}

