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
        foreach ($logs as $log) {
            $context = $log->context ?? [];
            $isAdd = str_contains(strtolower($log->message), 'adicionado');
            
            $history[] = [
                'id' => $log->id,
                'action' => $isAdd ? 'add' : 'remove',
                'action_label' => $isAdd ? 'Adicionado' : 'Removido',
                'message' => $log->message,
                'reason' => $context['reason'] ?? null,
                'action_type' => $context['action_type'] ?? 'automatic',
                'transaction_id' => $context['transaction_id'] ?? null,
                'performed_by' => $log->user_email ?? 'Sistema',
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $log->created_at->diffForHumans()
            ];
        }

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
}

