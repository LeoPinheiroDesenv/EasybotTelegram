<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Bot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BillingService
{
    /**
     * Obtém o faturamento mensal atual do usuário
     *
     * @param User $user
     * @return array
     */
    public function getMonthlyBilling(User $user): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Monta query base
        $query = Transaction::join('bots', 'transactions.bot_id', '=', 'bots.id')
            ->whereIn('transactions.status', ['approved', 'paid', 'completed'])
            ->whereBetween('transactions.created_at', [$startOfMonth, $endOfMonth]);

        // Super admin vê todos os recebimentos, outros apenas os seus
        if (!$user->isSuperAdmin()) {
            $query->where('bots.user_id', $user->id);
        }

        $total = (clone $query)->sum('transactions.amount');
        $count = (clone $query)->count();

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
     * @param User $user
     * @param array $filters
     * @return array
     */
    public function getBillingWithFilters(User $user, array $filters = []): array
    {
        // Usa JOIN direto para melhor performance
        $query = Transaction::join('bots', 'transactions.bot_id', '=', 'bots.id')
            ->whereIn('transactions.status', ['approved', 'paid', 'completed'])
            ->select('transactions.*')
            ->with(['contact', 'bot', 'paymentPlan', 'paymentCycle']);

        // Super admin vê todos os recebimentos, outros apenas os seus
        if (!$user->isSuperAdmin()) {
            $query->where('bots.user_id', $user->id);
        }

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

        // Agrupa por plano para estatísticas
        $byPlan = $transactions->groupBy('payment_plan_id')
            ->filter(function ($group) {
                return $group->isNotEmpty();
            })
            ->map(function ($group) {
                $plan = $group->first()->paymentPlan;
                return [
                    'plan_id' => $plan->id ?? null,
                    'plan_title' => $plan->title ?? 'Plano não encontrado',
                    'plan_price' => $plan->price ?? 0,
                    'subscription_count' => $group->count(),
                    'total_revenue' => (float) $group->sum('amount')
                ];
            })
            ->values();

        // Agrupa por assinatura (contato + plano)
        $bySubscription = $transactions->groupBy(function ($transaction) {
                return ($transaction->contact_id ?? 'unknown') . '_' . ($transaction->payment_plan_id ?? 'unknown');
            })
            ->filter(function ($group) {
                return $group->isNotEmpty();
            })
            ->map(function ($group) {
                $first = $group->first();
                $maxDate = $group->max('created_at');
                $minDate = $group->min('created_at');
                
                return [
                    'contact_id' => $first->contact_id ?? null,
                    'contact_name' => $first->contact->first_name ?? $first->contact->username ?? 'Sem nome',
                    'contact_username' => $first->contact->username ?? null,
                    'plan_id' => $first->payment_plan_id ?? null,
                    'plan_title' => $first->paymentPlan->title ?? 'Plano não encontrado',
                    'plan_price' => $first->paymentPlan->price ?? 0,
                    'cycle_name' => $first->paymentCycle->name ?? 'N/A',
                    'cycle_days' => $first->paymentCycle->days ?? 0,
                    'transaction_count' => $group->count(),
                    'total_revenue' => (float) $group->sum('amount'),
                    'last_payment' => $maxDate ? $maxDate->format('d/m/Y H:i') : 'N/A',
                    'first_payment' => $minDate ? $minDate->format('d/m/Y H:i') : 'N/A'
                ];
            })
            ->values();

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
                    'payment_plan' => [
                        'id' => $transaction->paymentPlan->id ?? null,
                        'title' => $transaction->paymentPlan->title ?? 'Plano não encontrado',
                        'price' => $transaction->paymentPlan->price ?? 0
                    ],
                    'payment_cycle' => [
                        'id' => $transaction->paymentCycle->id ?? null,
                        'name' => $transaction->paymentCycle->name ?? 'N/A',
                        'days' => $transaction->paymentCycle->days ?? 0
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
            'subscriptions' => $bySubscription,
            'plans' => $byPlan,
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
     * @param User $user
     * @param int $months Número de meses para retornar
     * @return array
     */
    public function getMonthlyChartData(User $user, int $months = 12): array
    {
        $data = [];
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();

        // Busca todos os bot_ids do usuário (ou todos se for super admin)
        $botQuery = Bot::query();
        if (!$user->isSuperAdmin()) {
            $botQuery->where('user_id', $user->id);
        }
        $botIds = $botQuery->pluck('id')->toArray();
        
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
     * @param User $user
     * @return float
     */
    public function getTotalBilling(User $user): float
    {
        // Usa JOIN direto para melhor performance
        $query = Transaction::join('bots', 'transactions.bot_id', '=', 'bots.id')
            ->whereIn('transactions.status', ['approved', 'paid', 'completed']);

        // Super admin vê todos os recebimentos, outros apenas os seus
        if (!$user->isSuperAdmin()) {
            $query->where('bots.user_id', $user->id);
        }

        $total = $query->sum('transactions.amount');

        return (float) $total;
    }

    /**
     * Obtém estatísticas completas do dashboard
     *
     * @param User $user
     * @return array
     */
    public function getDashboardStatistics(User $user): array
    {
        // Busca todos os bot_ids do usuário (ou todos se for super admin)
        $botQuery = Bot::query();
        if (!$user->isSuperAdmin()) {
            $botQuery->where('user_id', $user->id);
        }
        $botIds = $botQuery->pluck('id')->toArray();

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // Query base para transações aprovadas
        // Se não houver bots e não for super admin, força whereIn vazio para retornar 0 resultados
        $baseQuery = Transaction::whereIn('status', ['approved', 'paid', 'completed']);
        if (!empty($botIds)) {
            $baseQuery->whereIn('bot_id', $botIds);
        } elseif (!$user->isSuperAdmin()) {
            // Se não for super admin e não tiver bots, força whereIn vazio para não retornar nenhuma transação
            $baseQuery->whereIn('bot_id', []);
        }

        // Recebimentos do mês atual
        $currentMonthTotal = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Recebimentos do mês anterior
        $lastMonthTotal = (clone $baseQuery)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        // Calcula percentual de crescimento
        $growthPercentage = $lastMonthTotal > 0 
            ? round((($currentMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100, 1)
            : ($currentMonthTotal > 0 ? 100 : 0);

        // Total de transações do mês
        $currentMonthTransactions = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        // Total de transações do mês anterior
        $lastMonthTransactions = (clone $baseQuery)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        // Total geral (todos os tempos)
        $totalRevenue = (clone $baseQuery)->sum('amount');

        // Total de transações
        $totalTransactions = (clone $baseQuery)->count();

        // Assinaturas ativas (contatos com pagamento aprovado nos últimos 30 dias)
        $activeSubscriptions = (clone $baseQuery)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->distinct('contact_id')
            ->count('contact_id');

        // Recebimentos por método de pagamento (mês atual)
        $byPaymentMethod = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'method' => $item->payment_method ?? 'N/A',
                    'count' => (int) $item->count,
                    'total' => (float) $item->total
                ];
            });

        // Recebimentos por gateway (mês atual)
        $byGateway = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('gateway, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('gateway')
            ->get()
            ->map(function ($item) {
                return [
                    'gateway' => $item->gateway ?? 'N/A',
                    'count' => (int) $item->count,
                    'total' => (float) $item->total
                ];
            });

        // Recebimentos por bot (mês atual)
        $byBot = [];
        if (!empty($botIds)) {
            $byBot = (clone $baseQuery)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->with('bot')
                ->selectRaw('bot_id, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('bot_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'bot_id' => $item->bot_id,
                        'bot_name' => $item->bot->name ?? 'Bot desconhecido',
                        'count' => (int) $item->count,
                        'total' => (float) $item->total
                    ];
                });
        }

        // Últimas transações (10 mais recentes)
        $recentTransactions = (clone $baseQuery)
            ->with(['contact', 'bot', 'paymentPlan', 'paymentCycle'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'contact' => [
                        'id' => $transaction->contact_id,
                        'name' => $transaction->contact->first_name ?? $transaction->contact->username ?? 'Sem nome',
                        'username' => $transaction->contact->username,
                        'email' => $transaction->contact->email
                    ],
                    'bot' => [
                        'id' => $transaction->bot_id,
                        'name' => $transaction->bot->name ?? 'Bot desconhecido'
                    ],
                    'payment_plan' => [
                        'id' => $transaction->paymentPlan->id ?? null,
                        'title' => $transaction->paymentPlan->title ?? 'N/A'
                    ],
                    'payment_method' => $transaction->payment_method ?? 'N/A',
                    'gateway' => $transaction->gateway ?? 'N/A',
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->currency ?? 'BRL',
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
                    'created_at_formatted' => $transaction->created_at->format('d/m/Y H:i')
                ];
            });

        // Estatísticas diárias dos últimos 7 dias
        $dailyStats = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $dayTotal = (clone $baseQuery)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->sum('amount');

            $dayCount = (clone $baseQuery)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count();

            $dailyStats[] = [
                'date' => $date->format('Y-m-d'),
                'date_label' => $date->format('d/m'),
                'day_name' => $date->format('D'),
                'total' => (float) $dayTotal,
                'count' => (int) $dayCount
            ];
        }

        return [
            'metrics' => [
                'total_revenue' => [
                    'current' => (float) $currentMonthTotal,
                    'last_month' => (float) $lastMonthTotal,
                    'growth_percentage' => $growthPercentage,
                    'label' => 'Recebimentos do Mês',
                    'currency' => 'BRL'
                ],
                'total_transactions' => [
                    'current' => $currentMonthTransactions,
                    'last_month' => $lastMonthTransactions,
                    'growth_percentage' => $lastMonthTransactions > 0 
                        ? round((($currentMonthTransactions - $lastMonthTransactions) / $lastMonthTransactions) * 100, 1)
                        : ($currentMonthTransactions > 0 ? 100 : 0),
                    'label' => 'Transações'
                ],
                'active_subscriptions' => [
                    'current' => $activeSubscriptions,
                    'label' => 'Assinaturas Ativas'
                ],
                'total_all_time' => [
                    'current' => (float) $totalRevenue,
                    'transactions' => $totalTransactions,
                    'label' => 'Total Geral'
                ]
            ],
            'breakdown' => [
                'by_payment_method' => $byPaymentMethod,
                'by_gateway' => $byGateway,
                'by_bot' => $byBot
            ],
            'recent_transactions' => $recentTransactions,
            'daily_stats' => $dailyStats,
            'period' => [
                'current_month' => $now->format('F Y'),
                'current_month_start' => $startOfMonth->format('Y-m-d'),
                'current_month_end' => $endOfMonth->format('Y-m-d')
            ]
        ];
    }
}

