<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckPixExpirationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pix:check-expiration 
                            {--bot-id= : ID do bot específico para verificar}
                            {--dry-run : Apenas simula, não envia notificações}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica transações PIX pendentes que expiraram e notifica os usuários';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService): int
    {
        $botId = $this->option('bot-id');
        $dryRun = $this->option('dry-run');

        $this->info('Verificando expiração de PIX pendentes...');

        // Busca transações PIX pendentes
        $query = Transaction::where('status', 'pending')
            ->where('payment_method', 'pix')
            ->where('gateway', 'mercadopago')
            ->with(['bot', 'contact', 'paymentPlan']);

        if ($botId) {
            $query->where('bot_id', $botId);
        }

        $transactions = $query->get();

        if ($transactions->isEmpty()) {
            $this->info('Nenhuma transação PIX pendente encontrada.');
            return Command::SUCCESS;
        }

        $this->info("Encontradas {$transactions->count()} transação(ões) PIX pendente(s).");

        $expiredCount = 0;
        $notifiedCount = 0;
        $errorCount = 0;

        foreach ($transactions as $transaction) {
            try {
                $metadata = $transaction->metadata ?? [];
                $expirationDate = $metadata['expiration_date'] ?? null;

                if (!$expirationDate) {
                    // Se não tem data de expiração no metadata, usa 30 minutos após criação (padrão PIX)
                    $expirationDate = Carbon::parse($transaction->created_at)
                        ->addMinutes(30)
                        ->toIso8601String();
                }

                $expiresAt = Carbon::parse($expirationDate);
                $now = Carbon::now();

                // Verifica se expirou
                if ($now->greaterThan($expiresAt)) {
                    $expiredCount++;

                    // Verifica se já foi notificado
                    $alreadyNotified = $metadata['pix_expiration_notified'] ?? false;

                    if (!$alreadyNotified) {
                        $this->info("PIX expirado encontrado - Transação ID: {$transaction->id}");

                        if (!$dryRun) {
                            // Notifica o usuário
                            if ($transaction->contact && $transaction->bot) {
                                try {
                                    $paymentPlan = $transaction->paymentPlan;
                                    $amount = number_format($transaction->amount, 2, ',', '.');

                                    $message = "⏰ <b>PIX Expirado</b>\n\n";
                                    $message .= "Olá " . ($transaction->contact->first_name ?? 'Cliente') . ",\n\n";
                                    $message .= "O código PIX para o pagamento do plano <b>" . ($paymentPlan->title ?? 'N/A') . "</b> expirou.\n\n";
                                    $message .= "💰 <b>Valor:</b> R$ {$amount}\n\n";
                                    $message .= "Para realizar o pagamento novamente, use o comando /start e selecione um plano.";

                                    $telegramService->sendMessage(
                                        $transaction->bot,
                                        $transaction->contact->telegram_id,
                                        $message
                                    );

                                    // Marca como notificado no metadata
                                    $metadata['pix_expiration_notified'] = true;
                                    $metadata['pix_expiration_notified_at'] = now()->toIso8601String();
                                    $transaction->update(['metadata' => $metadata]);

                                    $notifiedCount++;

                                    $this->info("  ✓ Notificação enviada para contato ID: {$transaction->contact->id}");

                                    Log::info('Notificação de PIX expirado enviada', [
                                        'transaction_id' => $transaction->id,
                                        'contact_id' => $transaction->contact->id,
                                        'bot_id' => $transaction->bot->id
                                    ]);
                                } catch (\Exception $e) {
                                    $errorCount++;
                                    $this->error("  ✗ Erro ao enviar notificação: " . $e->getMessage());
                                    Log::error('Erro ao enviar notificação de PIX expirado', [
                                        'transaction_id' => $transaction->id,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            } else {
                                $this->warn("  ⚠ Transação sem contato ou bot associado - ID: {$transaction->id}");
                            }
                        } else {
                            $this->info("  [DRY RUN] Notificação seria enviada para transação ID: {$transaction->id}");
                            $notifiedCount++;
                        }
                    } else {
                        $this->info("  ⊘ PIX já notificado anteriormente - Transação ID: {$transaction->id}");
                    }
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("Erro ao processar transação ID {$transaction->id}: " . $e->getMessage());
                Log::error('Erro ao verificar expiração de PIX', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("Resumo:");
        $this->info("  - PIX expirados encontrados: {$expiredCount}");
        $this->info("  - Notificações enviadas: {$notifiedCount}");
        if ($errorCount > 0) {
            $this->warn("  - Erros: {$errorCount}");
        }

        return Command::SUCCESS;
    }
}
