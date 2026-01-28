<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bot;
use App\Models\Contact;
use App\Services\TelegramService;
use Carbon\Carbon;

class CheckExpirationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expiration:check {--bot-id=} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica planos expirando e envia lembretes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $botId = $this->option('bot-id');
        $force = $this->option('force');

        $query = Bot::where('active', true);
        if ($botId) {
            $query->where('id', $botId);
        }

        $bots = $query->get();
        $telegramService = new TelegramService();

        foreach ($bots as $bot) {
            $this->info("Verificando bot: {$bot->name}");

            // Busca contatos com transações aprovadas
            $contacts = Contact::where('bot_id', $bot->id)
                ->whereHas('transactions', function($q) {
                    $q->whereIn('status', ['approved', 'paid', 'completed']);
                })
                ->with(['transactions' => function($q) {
                    $q->whereIn('status', ['approved', 'paid', 'completed'])
                      ->orderBy('created_at', 'desc')
                      ->with(['paymentPlan', 'paymentCycle']);
                }])
                ->get();

            foreach ($contacts as $contact) {
                $lastTransaction = $contact->transactions->first();

                if (!$lastTransaction || !$lastTransaction->paymentCycle) {
                    continue;
                }

                $expiresAt = Carbon::parse($lastTransaction->created_at)
                    ->addDays($lastTransaction->paymentCycle->days);

                $daysRemaining = (int) ceil(now()->diffInDays($expiresAt, false));

                // Se já expirou há muito tempo (ex: > 30 dias), ignora
                if ($daysRemaining < -30) {
                    continue;
                }

                $shouldSend = false;

                // Lógica de envio automático (3 dias, 2 dias, hoje)
                // Verifica se já enviou hoje para não duplicar (idealmente teria log de envio, mas simplificando)
                if (!$force) {
                    if ($daysRemaining == 3 || $daysRemaining == 2 || $daysRemaining == 0) {
                        $shouldSend = true;
                    }
                }

                // Se for forçado, envia para todos que têm plano ativo ou recém expirado
                if ($force && $daysRemaining >= -5) {
                    $shouldSend = true;
                }

                if ($shouldSend) {
                    $planName = $lastTransaction->paymentPlan->title ?? $lastTransaction->paymentPlan->name;

                    $message = "Olá {$contact->first_name}!\n\n";
                    $message .= "Seu plano *{$planName}* ";

                    if ($daysRemaining > 0) {
                        $message .= "expira em *{$daysRemaining} dias* ({$expiresAt->format('d/m/Y')}).\n";
                        $message .= "Renove agora para continuar aproveitando os benefícios!";
                    } elseif ($daysRemaining == 0) {
                        $message .= "expira *hoje*!\n";
                        $message .= "Renove agora para não perder o acesso.";
                    } else {
                        $message .= "expirou em *{$expiresAt->format('d/m/Y')}*.\n";
                        $message .= "Renove agora para recuperar o acesso.";
                    }

                    try {
                        $telegramService->sendMessage($bot, $contact->telegram_id, $message);
                        $this->info("Lembrete enviado para {$contact->first_name} ({$daysRemaining} dias)");
                    } catch (\Exception $e) {
                        $this->error("Erro ao enviar para {$contact->first_name}: " . $e->getMessage());
                    }
                }
            }
        }
    }
}
