<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TelegramPollingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:polling 
                            {--bot-id= : ID do bot específico para fazer polling}
                            {--timeout=30 : Timeout em segundos para getUpdates}
                            {--limit=100 : Limite de atualizações por requisição}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Faz polling de atualizações do Telegram usando getUpdates (útil para desenvolvimento)';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $botId = $this->option('bot-id');
        $timeout = (int) $this->option('timeout');
        $limit = (int) $this->option('limit');

        // Se bot-id foi especificado, faz polling apenas desse bot
        if ($botId) {
            $bot = Bot::where('id', $botId)
                ->where('active', true)
                ->where('activated', true)
                ->first();

            if (!$bot) {
                $this->error("Bot ID {$botId} não encontrado ou não está ativo.");
                return Command::FAILURE;
            }

            return $this->pollBot($bot, $telegramService, $timeout, $limit);
        }

        // Caso contrário, faz polling de todos os bots ativos
        $bots = Bot::where('active', true)
            ->where('activated', true)
            ->get();

        if ($bots->isEmpty()) {
            $this->warn('Nenhum bot ativo encontrado.');
            return Command::SUCCESS;
        }

        $this->info("Iniciando polling para {$bots->count()} bot(s)...");

        foreach ($bots as $bot) {
            $this->info("Fazendo polling para bot: {$bot->name} (ID: {$bot->id})");
            $this->pollBot($bot, $telegramService, $timeout, $limit);
        }

        return Command::SUCCESS;
    }

    /**
     * Faz polling de um bot específico
     */
    protected function pollBot(Bot $bot, TelegramService $telegramService, int $timeout, int $limit): int
    {
        $this->info("Polling iniciado para bot: {$bot->name}");
        $lastUpdateId = 0;

        while (true) {
            try {
                // Busca atualizações do Telegram
                $response = \Illuminate\Support\Facades\Http::timeout($timeout + 5)
                    ->get("https://api.telegram.org/bot{$bot->token}/getUpdates", [
                        'offset' => $lastUpdateId + 1,
                        'timeout' => $timeout,
                        'limit' => $limit
                    ]);

                if (!$response->successful()) {
                    $this->error("Erro ao buscar atualizações: " . $response->body());
                    sleep(5);
                    continue;
                }

                $data = $response->json();

                if (!$data['ok']) {
                    $this->error("Erro na resposta do Telegram: " . ($data['description'] ?? 'Desconhecido'));
                    sleep(5);
                    continue;
                }

                $updates = $data['result'] ?? [];

                if (empty($updates)) {
                    // Sem atualizações, aguarda um pouco antes de tentar novamente
                    sleep(1);
                    continue;
                }

                foreach ($updates as $update) {
                    $updateId = $update['update_id'] ?? 0;
                    
                    if ($updateId > $lastUpdateId) {
                        $lastUpdateId = $updateId;
                    }

                    // Processa a atualização
                    $this->info("Processando atualização ID: {$updateId}");
                    $telegramService->processUpdate($bot, $update);
                }

                // Pequena pausa entre requisições
                usleep(100000); // 0.1 segundos

            } catch (\Exception $e) {
                $this->error("Erro durante polling: " . $e->getMessage());
                Log::error('Erro no polling do Telegram', [
                    'bot_id' => $bot->id,
                    'error' => $e->getMessage()
                ]);
                sleep(5);
            }
        }
    }
}

