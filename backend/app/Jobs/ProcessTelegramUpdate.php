<?php

namespace App\Jobs;

use App\Models\Bot;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTelegramUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Bot $bot,
        public array $update
    ) {
        // Define a queue específica para processar atualizações do Telegram
        $this->onQueue('telegram-updates');
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramService $telegramService): void
    {
        try {
            $telegramService->processUpdate($this->bot, $this->update);
        } catch (\Exception $e) {
            Log::error('Erro ao processar atualização do Telegram na queue', [
                'bot_id' => $this->bot->id,
                'error' => $e->getMessage(),
                'update' => $this->update
            ]);
            
            // Re-throw para que o Laravel possa registrar como falha se necessário
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de processamento de atualização do Telegram falhou', [
            'bot_id' => $this->bot->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}

