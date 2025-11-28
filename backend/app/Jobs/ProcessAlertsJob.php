<?php

namespace App\Jobs;

use App\Jobs\SendAlert;
use App\Models\Alert;
use App\Models\Bot;
use App\Models\Contact;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAlertsJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected $botId;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $botId = null)
    {
        $this->botId = $botId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Busca alertas ativos que estão prontos para serem enviados
            $query = Alert::where('status', 'active')
                ->where(function ($q) {
                    // Alertas comuns (sem agendamento)
                    $q->where('alert_type', 'common')
                        // Ou alertas agendados que já passaram da data/hora
                        ->orWhere(function ($subQ) {
                            $subQ->where('alert_type', 'scheduled')
                                ->where('scheduled_date', '<=', now()->toDateString())
                                ->where(function ($timeQ) {
                                    $timeQ->whereNull('scheduled_time')
                                        ->orWhere('scheduled_time', '<=', now()->toTimeString());
                                });
                        });
                });

            // Filtra por bot se fornecido
            if ($this->botId) {
                $query->where('bot_id', $this->botId);
            }

            $alerts = $query->with(['bot'])->get();

            if ($alerts->isEmpty()) {
                Log::info('Nenhum alerta para processar');
                return;
            }

            Log::info("Processando {$alerts->count()} alerta(s)");

            foreach ($alerts as $alert) {
                try {
                    // Busca contatos que devem receber o alerta
                    $contacts = $this->getTargetContacts($alert);

                    if ($contacts->isEmpty()) {
                        Log::info("Nenhum contato encontrado para o alerta ID: {$alert->id}");
                        continue;
                    }

                    // Dispara jobs para enviar o alerta
                    foreach ($contacts as $contact) {
                        SendAlert::dispatch($alert, $contact);
                    }

                    // Se for alerta agendado, marca como enviado
                    if ($alert->alert_type === 'scheduled') {
                        $alert->update(['status' => 'sent']);
                    }

                    Log::info("Alerta ID: {$alert->id} processado com sucesso");
                } catch (\Exception $e) {
                    Log::error('Erro ao processar alerta', [
                        'alert_id' => $alert->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao processar alertas no job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
        }

        // Filtro por plano específico
        if ($alert->plan_id) {
            // Aqui você pode adicionar lógica para filtrar por plano
            // Por exemplo, verificar transações ou assinaturas ativas
        }

        return $query->get();
    }
}
