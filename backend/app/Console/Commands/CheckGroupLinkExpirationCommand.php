<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TelegramService;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckGroupLinkExpirationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:group-link-expiration {--dry-run : Executa sem enviar notificações}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica links de grupo expirados e notifica usuários';

    protected $telegramService;
    protected $paymentService;

    public function __construct(TelegramService $telegramService, PaymentService $paymentService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->paymentService = $paymentService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 Modo DRY-RUN: Nenhuma notificação será enviada');
        }

        $this->info('🔍 Verificando links de grupo expirados...');

        // Busca transações com status aprovado que têm link de grupo
        $transactions = Transaction::whereIn('status', ['approved', 'paid', 'completed'])
            ->whereNotNull('metadata')
            ->with(['bot', 'contact', 'paymentPlan', 'paymentCycle'])
            ->get();

        $expiredCount = 0;
        $notifiedCount = 0;

        foreach ($transactions as $transaction) {
            $metadata = $transaction->metadata ?? [];
            
            // Verifica se tem link de grupo no metadata
            if (empty($metadata['group_invite_link']) || empty($metadata['group_invite_link_expires_at'])) {
                continue;
            }

            $expiresAt = $metadata['group_invite_link_expires_at'];
            
            try {
                $expireDate = Carbon::parse($expiresAt);
            } catch (\Exception $e) {
                Log::warning('Erro ao parsear data de expiração do link', [
                    'transaction_id' => $transaction->id,
                    'expires_at' => $expiresAt,
                    'error' => $e->getMessage()
                ]);
                continue;
            }

            // Verifica se o link expirou ou está próximo de expirar (dentro de 24 horas)
            $isExpired = now()->greaterThan($expireDate);
            $isExpiringSoon = now()->addHours(24)->greaterThan($expireDate) && !now()->greaterThan($expireDate);
            
            if ($isExpired || $isExpiringSoon) {
                // Verifica se o usuário tem um pagamento válido mais recente
                $hasValidPayment = Transaction::where('contact_id', $transaction->contact_id)
                    ->where('bot_id', $transaction->bot_id)
                    ->whereIn('status', ['approved', 'paid', 'completed'])
                    ->where('id', '>', $transaction->id) // Transação mais recente
                    ->whereNotNull('metadata')
                    ->exists();
                
                if ($hasValidPayment) {
                    // Usuário tem pagamento válido mais recente - não precisa notificar sobre esta transação antiga
                    $this->line("  ⏭️  Usuário tem pagamento válido mais recente - Transação ID: {$transaction->id}");
                    continue;
                }
                
                if ($isExpired) {
                    $expiredCount++;
                    
                    // CRÍTICO: Verifica se a transação ainda está válida (status aprovado)
                    // Se estiver válida, tenta renovar o link
                    // Se não estiver válida, apenas notifica sobre expiração
                    $transactionStillValid = in_array($transaction->status, ['approved', 'paid', 'completed']);
                    
                    // CRÍTICO: Tenta renovar o link apenas se a transação ainda estiver válida
                    $linkRenewed = false;
                    $newLink = null;
                    
                    if ($transactionStillValid && !$dryRun && $transaction->contact && $transaction->bot && !empty($transaction->contact->telegram_id)) {
                        try {
                            // Recarrega a transação para garantir que tem os relacionamentos
                            $transaction->refresh();
                            $transaction->load(['bot', 'contact', 'paymentPlan', 'paymentCycle']);
                            
                            // Tenta renovar o link usando o PaymentService
                            $newLink = $this->paymentService->findGroupInviteLink($transaction, $this->telegramService);
                            
                            if ($newLink) {
                                // Atualiza o metadata com o novo link
                                $metadata = $transaction->metadata ?? [];
                                $metadata['group_invite_link'] = $newLink;
                                
                                // Calcula nova data de expiração baseada no ciclo
                                if ($transaction->paymentCycle) {
                                    $days = $transaction->paymentCycle->days ?? 30;
                                    $newExpireDate = Carbon::now()->addDays($days);
                                    $metadata['group_invite_link_expires_at'] = $newExpireDate->toIso8601String();
                                    $metadata['group_invite_link_created_at'] = now()->toIso8601String();
                                    $metadata['group_invite_link_renewed_at'] = now()->toIso8601String();
                                    $metadata['group_link_expiration_notified'] = false; // Reseta para permitir notificações futuras
                                }
                                
                                $transaction->update(['metadata' => $metadata]);
                                $linkRenewed = true;
                                
                                Log::info('Link de grupo renovado automaticamente', [
                                    'transaction_id' => $transaction->id,
                                    'contact_id' => $transaction->contact->id,
                                    'old_expires_at' => $expireDate->toDateTimeString(),
                                    'new_expires_at' => isset($newExpireDate) ? $newExpireDate->toDateTimeString() : null,
                                    'transaction_status' => $transaction->status
                                ]);
                            } else {
                                Log::warning('Não foi possível renovar link de grupo - link não encontrado', [
                                    'transaction_id' => $transaction->id,
                                    'contact_id' => $transaction->contact->id,
                                    'transaction_status' => $transaction->status
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Erro ao tentar renovar link de grupo', [
                                'transaction_id' => $transaction->id,
                                'contact_id' => $transaction->contact->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    } elseif (!$transactionStillValid) {
                        Log::info('Transação não está mais válida - não será renovado link automaticamente', [
                            'transaction_id' => $transaction->id,
                            'transaction_status' => $transaction->status,
                            'contact_id' => $transaction->contact_id
                        ]);
                    }
                    
                    // Verifica se já foi notificado
                    $alreadyNotified = $metadata['group_link_expiration_notified'] ?? false;
                    
                    if (!$alreadyNotified || $linkRenewed) {
                        $this->info("Link expirado encontrado - Transação ID: {$transaction->id}");
                        $this->info("  Data de expiração: {$expireDate->toDateTimeString()}");
                        $this->info("  Contato: {$transaction->contact->first_name} (ID: {$transaction->contact->id})");
                        if ($linkRenewed) {
                            $this->info("  ✅ Link renovado automaticamente");
                        }

                        if (!$dryRun) {
                            // Notifica o usuário
                            if ($transaction->contact && $transaction->bot && !empty($transaction->contact->telegram_id)) {
                                try {
                                    $paymentPlan = $transaction->paymentPlan;
                                    $paymentCycle = $transaction->paymentCycle;
                                    $days = $paymentCycle->days ?? 30;
                                    
                                    if ($linkRenewed && $newLink) {
                                        // Link foi renovado - envia mensagem com novo link
                                        $message = "🔄 <b>Link do Grupo Renovado</b>\n\n";
                                        $message .= "Olá " . ($transaction->contact->first_name ?? 'Cliente') . ",\n\n";
                                        $message .= "O link do seu grupo foi renovado automaticamente!\n\n";
                                        $message .= "📦 <b>Plano:</b> " . ($paymentPlan->title ?? 'N/A') . "\n";
                                        $message .= "📅 <b>Duração:</b> {$days} dia(s)\n\n";
                                        $message .= "🔗 <b>Novo link do grupo:</b>\n";
                                        $message .= "{$newLink}\n\n";
                                        $message .= "Obrigado por continuar conosco! 🎉";
                                        
                                        // Reseta flag de notificação para permitir notificações futuras
                                        $metadata['group_link_expiration_notified'] = false;
                                        $metadata['group_link_renewed'] = true;
                                        $metadata['group_link_renewed_at'] = now()->toIso8601String();
                                    } else {
                                        // Link não foi renovado - notifica sobre expiração
                                        $message = "⚠️ <b>Plano Expirado</b>\n\n";
                                        $message .= "Olá " . ($transaction->contact->first_name ?? 'Cliente') . ",\n\n";
                                        $message .= "Seu plano <b>" . ($paymentPlan->title ?? 'N/A') . "</b> expirou.\n\n";
                                        $message .= "📅 <b>Duração do plano:</b> {$days} dia(s)\n";
                                        $message .= "⏰ <b>Data de expiração:</b> " . $expireDate->format('d/m/Y H:i') . "\n\n";
                                        $message .= "Para continuar tendo acesso ao grupo, por favor, efetue um novo pagamento.\n\n";
                                        $message .= "Use o comando /start para ver os planos disponíveis.";
                                        
                                        // Marca como notificado apenas se não conseguiu renovar
                                        $metadata['group_link_expiration_notified'] = true;
                                        $metadata['group_link_expiration_notified_at'] = now()->toIso8601String();
                                    }

                                    $this->telegramService->sendMessage(
                                        $transaction->bot,
                                        $transaction->contact->telegram_id,
                                        $message
                                    );

                                    $transaction->update(['metadata' => $metadata]);

                                    $notifiedCount++;

                                    $this->info("  ✓ Notificação enviada para contato ID: {$transaction->contact->id}");
                                    if ($linkRenewed) {
                                        $this->info("  ✓ Link renovado e enviado ao usuário");
                                    }

                                    Log::info('Notificação de link de grupo processada', [
                                        'transaction_id' => $transaction->id,
                                        'contact_id' => $transaction->contact->id,
                                        'expires_at' => $expireDate->toDateTimeString(),
                                        'link_renewed' => $linkRenewed,
                                        'new_link_sent' => $linkRenewed && !empty($newLink)
                                    ]);
                                } catch (\Exception $e) {
                                    $this->error("  ✗ Erro ao enviar notificação: " . $e->getMessage());
                                    
                                    Log::error('Erro ao enviar notificação de link expirado', [
                                        'transaction_id' => $transaction->id,
                                        'contact_id' => $transaction->contact->id,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                        } else {
                            $this->info("  [DRY-RUN] Notificação seria enviada para contato ID: {$transaction->contact->id}");
                            if ($linkRenewed) {
                                $this->info("  [DRY-RUN] Link seria renovado e enviado");
                            }
                        }
                    } else {
                        $this->line("  ⏭️  Já notificado anteriormente - Transação ID: {$transaction->id}");
                    }
                } elseif ($isExpiringSoon) {
                    // Link está próximo de expirar - pode adicionar lógica de aviso prévio aqui se necessário
                    $this->line("  ⚠️  Link próximo de expirar (24h) - Transação ID: {$transaction->id}");
                }
            }
        }

        $this->info("\n✅ Verificação concluída!");
        $this->info("   Links expirados encontrados: {$expiredCount}");
        $this->info("   Notificações enviadas: {$notifiedCount}");

        return 0;
    }
}
