<?php

namespace App\Jobs;

use App\Models\Downsell;
use App\Models\Contact;
use App\Models\Transaction;
use App\Services\DownsellService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDownsell implements ShouldQueue
{
    use Queueable, SerializesModels, InteractsWithQueue;

    /**
     * Número de tentativas em caso de falha
     */
    public $tries = 3;

    /**
     * Timeout em segundos
     */
    public $timeout = 60;

    protected $downsell;
    protected $contact;
    protected $transaction;

    /**
     * Create a new job instance.
     */
    public function __construct(Downsell $downsell, Contact $contact, Transaction $transaction)
    {
        $this->downsell = $downsell;
        $this->contact = $contact;
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     */
    public function handle(DownsellService $downsellService): void
    {
        try {
            // Recarrega os modelos para garantir que temos os dados mais recentes
            $this->downsell->refresh();
            $this->contact->refresh();
            $this->transaction->refresh();

            // Verifica se ainda pode ser usado
            if (!$this->downsell->canBeUsed()) {
                Log::info('Downsell não pode mais ser usado, pulando envio', [
                    'downsell_id' => $this->downsell->id
                ]);
                return;
            }

            // Envia o downsell
            $downsellService->sendDownsell($this->downsell, $this->contact, $this->transaction);
        } catch (\Exception $e) {
            Log::error('Erro ao executar job SendDownsell', [
                'downsell_id' => $this->downsell->id ?? null,
                'contact_id' => $this->contact->id ?? null,
                'transaction_id' => $this->transaction->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
