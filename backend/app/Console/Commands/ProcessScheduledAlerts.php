<?php

namespace App\Console\Commands;

use App\Jobs\SendAlert;
use App\Models\Alert;
use App\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processa e envia alertas agendados que estão prontos para serem enviados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processando alertas agendados...');

        // Busca alertas ativos que estão prontos para serem enviados
        $alerts = Alert::where('status', 'active')
            ->where(function ($query) {
                // Alertas comuns (sem agendamento)
                $query->where('alert_type', 'common')
                    // Ou alertas agendados que já passaram da data/hora
                    ->orWhere(function ($q) {
                        $q->where('alert_type', 'scheduled')
                            ->where('scheduled_date', '<=', now()->toDateString())
                            ->where(function ($subQ) {
                                $subQ->whereNull('scheduled_time')
                                    ->orWhere('scheduled_time', '<=', now()->toTimeString());
                            });
                    });
            })
            ->with(['bot'])
            ->get();

        if ($alerts->isEmpty()) {
            $this->info('Nenhum alerta para processar.');
            return 0;
        }

        $this->info("Encontrados {$alerts->count()} alerta(s) para processar.");

        $totalSent = 0;

        foreach ($alerts as $alert) {
            try {
                // Busca contatos que devem receber o alerta
                $contacts = $this->getTargetContacts($alert);

                if ($contacts->isEmpty()) {
                    $this->warn("Nenhum contato encontrado para o alerta ID: {$alert->id}");
                    continue;
                }

                $this->info("Enviando alerta ID: {$alert->id} para {$contacts->count()} contato(s)...");

                // Dispara jobs para enviar o alerta
                foreach ($contacts as $contact) {
                    SendAlert::dispatch($alert, $contact);
                    $totalSent++;
                }

                // Se for alerta agendado, marca como enviado
                if ($alert->alert_type === 'scheduled') {
                    $alert->update(['status' => 'sent']);
                }

                $this->info("Alerta ID: {$alert->id} processado com sucesso.");
            } catch (\Exception $e) {
                $this->error("Erro ao processar alerta ID: {$alert->id} - " . $e->getMessage());
                Log::error('Erro ao processar alerta', [
                    'alert_id' => $alert->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("Processamento concluído. {$totalSent} alerta(s) enviado(s).");
        return 0;
    }

    /**
     * Busca contatos que devem receber o alerta
     */
    protected function getTargetContacts(Alert $alert): \Illuminate\Database\Eloquent\Collection
    {
        $query = Contact::where('bot_id', $alert->bot_id)
            ->where('is_bot', false)
            ->where('is_blocked', false)
            ->where('telegram_status', 'active');

        // Filtro por idioma
        if ($alert->user_language) {
            $query->where('language', $alert->user_language);
        }

        // Filtro por categoria (premium/free)
        if ($alert->user_category !== 'all') {
            // Aqui você pode adicionar lógica para determinar se o usuário é premium ou free
            // Por exemplo, verificar se tem transações ativas, planos, etc.
            // Por enquanto, vamos apenas filtrar se houver um plano específico
            if ($alert->plan_id) {
                // Se o alerta está vinculado a um plano, envia apenas para usuários desse plano
                // Você pode implementar uma lógica mais complexa aqui
            }
        }

        // Filtro por plano específico
        if ($alert->plan_id) {
            // Aqui você pode adicionar lógica para filtrar por plano
            // Por exemplo, verificar transações ou assinaturas ativas
        }

        return $query->get();
    }
}
