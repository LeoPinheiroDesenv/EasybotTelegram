<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Bot;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BillingService
{
    /**
     * Obtém o faturamento mensal atual do usuário
     *
     * @param int $userId
     * @return array
     */
    public function getMonthlyBilling(int $userId): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Usa JOIN direto ao invés de whereHas para melhor performance
        $total = Transaction::join('bots', 'transactions.bot_id', '=', 'bots.id')
            ->where('bots.user_id', $userId)
            ->whereIn('transactions.status', ['approved', 'paid', 'completed'])
            ->whereBetween('transactions.created_at', [$startOfMonth, $endOfMonth])
            ->sum('transactions.amount');

        $count = Transaction::join('bots', 'transactions.bot_id', '=', 'bots.id')
            ->where('bots.user_id', $userId)
            ->whereIn('transactions.status', ['approved', 'paid', 'completed'])
            ->whereBetween('transactions.created_at', [$startOfMonth, $endOfMonth])
            ->count();

        // Formata mês em português
        $months = [
            'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
            'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
            'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
            'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
        ];
        $monthName = $months[Carbon::now()->format('F')] ?? Carbon::now()->format('F');

        return [
            'total' => (float) $total,
            'currency' => 'BRL',
            'period' => [
                'start' => $startOfMonth->format('Y-m-d'),
                'end' => $endOfMonth->format('Y-m-d'),
                'month' => $monthName . ' ' . Carbon::now()->format('Y')
            ],
            'transaction_count' => $count
        ];
    }

    /**
     * Obtém faturamento com filtros
     *
     * @param int $userId
     * @param array $filters
     * @return array
     */
    public function getBillingWithFilters(int $userId, array $filters = []): array
    {
        // Usa JOIN direto para melhor performance
        $query = Transaction::join('bots', 'transactions.bot_id', '=', 'bots.id')
            ->where('bots.user_id', $userId)
            ->whereIn('transactions.status', ['approved', 'paid', 'completed'])
            ->select('transactions.*')
            ->with(['contact', 'bot', 'paymentPlan']);

        // Filtro por período
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('transactions.created_at', [
                Carbon::parse($filters['start_date'])->startOfDay(),
                Carbon::parse($filters['end_date'])->endOfDay()
            ]);
        } elseif (isset($filters['month'])) {
            $month = Carbon::parse($filters['month']);
            $query->whereBetween('transactions.created_at', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth()
            ]);
        }

        // Filtro por bot
        if (isset($filters['bot_id'])) {
            $query->where('transactions.bot_id', $filters['bot_id']);
        }

        // Filtro por método de pagamento
        if (isset($filters['payment_method'])) {
            $query->where('transactions.payment_method', $filters['payment_method']);
        }

        // Filtro por gateway
        if (isset($filters['gateway'])) {
            $query->where('transactions.gateway', $filters['gateway']);
        }

        $transactions = $query->orderBy('transactions.created_at', 'desc')->get();

        $total = $transactions->sum('amount');

        return [
            'transactions' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'contact' => [
                        'id' => $transaction->contact_id,
                        'name' => $transaction->contact->first_name ?? $transaction->contact->username ?? 'Sem nome',
                        'username' => $transaction->contact->username,
                        'telegram_id' => $transaction->contact->telegram_id
                    ],
                    'bot' => [
                        'id' => $transaction->bot_id,
                        'name' => $transaction->bot->name ?? 'Bot desconhecido'
                    ],
                    'payment_method' => $transaction->payment_method ?? 'N/A',
                    'gateway' => $transaction->gateway,
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->currency ?? 'BRL',
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
                    'created_at_formatted' => $transaction->created_at->format('d/m/Y H:i')
                ];
            }),
            'summary' => [
                'total' => (float) $total,
                'currency' => 'BRL',
                'transaction_count' => $transactions->count(),
                'by_payment_method' => $transactions->groupBy('payment_method')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total' => (float) $group->sum('amount')
                    ];
                }),
                'by_gateway' => $transactions->groupBy('gateway')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total' => (float) $group->sum('amount')
                    ];
                })
            ]
        ];
    }

    /**
     * Obtém dados para gráfico de faturamento mensal
     *
     * @param int $userId
     * @param int $months Número de meses para retornar
     * @return array
     */
    public function getMonthlyChartData(int $userId, int $months = 12): array
    {
        $data = [];
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();

        // Busca todos os bot_ids do usuário uma única vez
        $botIds = Bot::where('user_id', $userId)->pluck('id')->toArray();
        
        if (empty($botIds)) {
            // Se não há bots, retorna array vazio com meses
            for ($i = 0; $i < $months; $i++) {
                $month = $startDate->copy()->addMonths($i);
                $monthsAbbr = [
                    'Jan' => 'Jan', 'Feb' => 'Fev', 'Mar' => 'Mar',
                    'Apr' => 'Abr', 'May' => 'Mai', 'Jun' => 'Jun',
                    'Jul' => 'Jul', 'Aug' => 'Ago', 'Sep' => 'Set',
                    'Oct' => 'Out', 'Nov' => 'Nov', 'Dec' => 'Dez'
                ];
                $monthAbbr = $monthsAbbr[$month->format('M')] ?? $month->format('M');
                $data[] = [
                    'month' => $month->format('Y-m'),
                    'month_label' => $monthAbbr . '/' . $month->format('y'),
                    'total' => 0.0,
                    'transaction_count' => 0
                ];
            }
            return $data;
        }

        for ($i = 0; $i < $months; $i++) {
            $month = $startDate->copy()->addMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            // Usa whereIn com bot_ids ao invés de whereHas
            $total = Transaction::whereIn('bot_id', $botIds)
                ->whereIn('status', ['approved', 'paid', 'completed'])
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');
                
            $count = Transaction::whereIn('bot_id', $botIds)
                ->whereIn('status', ['approved', 'paid', 'completed'])
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            // Formata mês em português
            $monthsAbbr = [
                'Jan' => 'Jan', 'Feb' => 'Fev', 'Mar' => 'Mar',
                'Apr' => 'Abr', 'May' => 'Mai', 'Jun' => 'Jun',
                'Jul' => 'Jul', 'Aug' => 'Ago', 'Sep' => 'Set',
                'Oct' => 'Out', 'Nov' => 'Nov', 'Dec' => 'Dez'
            ];
            $monthAbbr = $monthsAbbr[$month->format('M')] ?? $month->format('M');

            $data[] = [
                'month' => $month->format('Y-m'),
                'month_label' => $monthAbbr . '/' . $month->format('y'),
                'total' => (float) $total,
                'transaction_count' => $count
            ];
        }

        return $data;
    }

    /**
     * Obtém faturamento total de todos os tempos
     *
     * @param int $userId
     * @return float
     */
    public function getTotalBilling(int $userId): float
    {
        // Usa JOIN direto para melhor performance
        $total = Transaction::join('bots', 'transactions.bot_id', '=', 'bots.id')
            ->where('bots.user_id', $userId)
            ->whereIn('transactions.status', ['approved', 'paid', 'completed'])
            ->sum('transactions.amount');

        return (float) $total;
    }
}

