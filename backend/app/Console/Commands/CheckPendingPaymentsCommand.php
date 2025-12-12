<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\PaymentGatewayConfig;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

class CheckPendingPaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:check-pending 
                            {--bot-id= : ID do bot específico para verificar}
                            {--interval=30 : Intervalo em segundos desde a última verificação}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica pagamentos PIX pendentes e processa aprovações automaticamente';

    /**
     * Execute the console command.
     */
    public function handle(PaymentService $paymentService): int
    {
        $botId = $this->option('bot-id');
        $interval = (int) $this->option('interval');

        $this->info('Verificando pagamentos PIX pendentes...');

        // Busca transações PIX pendentes
        $query = Transaction::where('status', 'pending')
            ->where('payment_method', 'pix')
            ->where('gateway', 'mercadopago')
            ->with(['bot', 'contact', 'paymentPlan']);

        if ($botId) {
            $query->where('bot_id', $botId);
        }

        // Filtra apenas transações que não foram verificadas recentemente
        // Evita verificar a mesma transação múltiplas vezes em pouco tempo
        $query->where(function($q) use ($interval) {
            $q->whereRaw('JSON_EXTRACT(metadata, "$.last_status_check") IS NULL')
              ->orWhereRaw('TIMESTAMPDIFF(SECOND, JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.last_status_check")), NOW()) >= ?', [$interval]);
        });

        $transactions = $query->get();

        // Verifica se há pagamentos pendentes (mesmo que não sejam verificados agora devido ao intervalo)
        $hasPendingPayments = Transaction::where('status', 'pending')
            ->where('payment_method', 'pix')
            ->where('gateway', 'mercadopago')
            ->when($botId, function($q) use ($botId) {
                $q->where('bot_id', $botId);
            })
            ->exists();

        if ($transactions->isEmpty()) {
            if ($hasPendingPayments) {
                $this->info('Nenhuma transação PIX pendente encontrada para verificar agora (aguardando intervalo).');
                $this->info('Há pagamentos pendentes - chame o endpoint /api/payments/check-pending novamente em 15 segundos.');
            } else {
                $this->info('Nenhuma transação PIX pendente encontrada.');
            }
            return Command::SUCCESS;
        }

        $this->info("Encontradas {$transactions->count()} transação(ões) PIX pendente(s) para verificar.");

        $checkedCount = 0;
        $approvedCount = 0;
        $errorCount = 0;

        foreach ($transactions as $transaction) {
            $paymentId = null;
            try {
                $paymentId = $transaction->gateway_transaction_id 
                    ?? $transaction->metadata['mercadopago_payment_id'] 
                    ?? null;

                if (!$paymentId) {
                    $this->warn("  ⚠ Transação ID {$transaction->id} sem payment_id - pulando");
                    continue;
                }

                // Busca configuração do gateway
                $gatewayConfig = PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                    ->where('gateway', 'mercadopago')
                    ->where('active', true)
                    ->first();

                if (!$gatewayConfig || !$gatewayConfig->api_key) {
                    $this->warn("  ⚠ Transação ID {$transaction->id} sem configuração de gateway - pulando");
                    continue;
                }

                // Configura o SDK do Mercado Pago
                MercadoPagoConfig::setAccessToken($gatewayConfig->api_key);
                $client = new PaymentClient();
                
                // Busca o status atual do pagamento
                $payment = $client->get($paymentId);
                
                if ($payment) {
                    $status = $payment->status ?? 'pending';
                    
                    // Atualiza metadata com última verificação
                    $metadata = $transaction->metadata ?? [];
                    $metadata['last_status_check'] = now()->toIso8601String();
                    $transaction->update(['metadata' => $metadata]);
                    
                    $checkedCount++;
                    
                    // Se está aprovado, processa
                    if ($status === 'approved') {
                        $this->info("  ✓ Pagamento ID {$paymentId} aprovado - processando...");
                        
                        $paymentService->processPaymentApproval($transaction, $payment, $gatewayConfig);
                        $approvedCount++;
                        
                        Log::info('Pagamento aprovado via verificação automática', [
                            'transaction_id' => $transaction->id,
                            'payment_id' => $paymentId
                        ]);
                    } elseif ($status === 'cancelled' || $status === 'rejected') {
                        $this->info("  ⊘ Pagamento ID {$paymentId} cancelado/rejeitado");
                    } else {
                        $this->info("  ⏳ Pagamento ID {$paymentId} ainda pendente (status: {$status})");
                    }
                }
            } catch (\MercadoPago\Exceptions\MPApiException $e) {
                $errorCount++;
                $errorMessage = $e->getMessage();
                $apiResponse = $e->getApiResponse();
                $statusCode = $apiResponse ? $apiResponse->getStatusCode() : null;
                $responseContent = $apiResponse ? $apiResponse->getContent() : null;
                
                // Verifica se é o erro "Chave não localizada" (payment não encontrado)
                $isKeyNotFound = stripos($errorMessage, 'chave não localizada') !== false 
                    || stripos($errorMessage, 'key not found') !== false
                    || stripos($errorMessage, 'not found') !== false
                    || ($statusCode === 404)
                    || (isset($responseContent['message']) && (
                        stripos($responseContent['message'], 'chave não localizada') !== false ||
                        stripos($responseContent['message'], 'not found') !== false
                    ));
                
                if ($isKeyNotFound) {
                    // Usa paymentId do escopo do try, ou busca novamente se não estiver definido
                    $currentPaymentId = $paymentId ?? $transaction->gateway_transaction_id 
                        ?? $transaction->metadata['mercadopago_payment_id'] 
                        ?? 'N/A';
                    
                    $this->warn("  ⚠️  Transação ID {$transaction->id} - Pagamento ID {$currentPaymentId} não encontrado no Mercado Pago (Chave não localizada)");
                    
                    Log::warning('⚠️ Pagamento não encontrado no Mercado Pago (Chave não localizada)', [
                        'transaction_id' => $transaction->id,
                        'payment_id' => $currentPaymentId,
                        'status_code' => $statusCode,
                        'api_response' => $responseContent,
                        'note' => 'O payment_id pode estar incorreto ou o pagamento foi deletado no Mercado Pago'
                    ]);
                    
                    // Atualiza metadata para indicar que o pagamento não foi encontrado
                    $metadata = $transaction->metadata ?? [];
                    $metadata['payment_not_found'] = true;
                    $metadata['payment_not_found_at'] = now()->toIso8601String();
                    $metadata['payment_not_found_error'] = $errorMessage;
                    $notFoundCount = ($metadata['payment_not_found_count'] ?? 0) + 1;
                    $metadata['payment_not_found_count'] = $notFoundCount;
                    
                    // Se não foi encontrado 3 vezes ou mais, marca como falhado
                    if ($notFoundCount >= 3) {
                        $transaction->update([
                            'status' => 'failed',
                            'metadata' => $metadata
                        ]);
                        
                        $this->error("  ❌ Transação ID {$transaction->id} marcada como falhada após {$notFoundCount} tentativas de encontrar pagamento");
                        
                        Log::warning('🔄 Transação marcada como falhada após múltiplas tentativas de encontrar pagamento', [
                            'transaction_id' => $transaction->id,
                            'payment_id' => $currentPaymentId,
                            'not_found_count' => $notFoundCount
                        ]);
                    } else {
                        $transaction->update(['metadata' => $metadata]);
                    }
                } else {
                    $this->error("  ✗ Erro ao verificar transação ID {$transaction->id}: " . $errorMessage);
                    Log::error('Erro ao verificar pagamento pendente', [
                        'transaction_id' => $transaction->id,
                        'payment_id' => $paymentId ?? null,
                        'error' => $errorMessage,
                        'status_code' => $statusCode,
                        'api_response' => $responseContent
                    ]);
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("  ✗ Erro ao processar transação ID {$transaction->id}: " . $e->getMessage());
                Log::error('Erro ao verificar pagamento pendente', [
                    'transaction_id' => $transaction->id,
                    'payment_id' => $paymentId ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->newLine();
        $this->info("Resumo:");
        $this->info("  - Transações verificadas: {$checkedCount}");
        $this->info("  - Pagamentos aprovados: {$approvedCount}");
        if ($errorCount > 0) {
            $this->warn("  - Erros: {$errorCount}");
        }

        // Verifica se ainda há pagamentos pendentes após processar
        $stillHasPending = Transaction::where('status', 'pending')
            ->where('payment_method', 'pix')
            ->where('gateway', 'mercadopago')
            ->when($botId, function($q) use ($botId) {
                $q->where('bot_id', $botId);
            })
            ->exists();

        // Informa quando deve ser verificado novamente
        if ($stillHasPending) {
            $this->info('');
            $this->info('Ainda há pagamentos pendentes - chame o endpoint /api/payments/check-pending novamente em 15 segundos.');
            $this->info('Ou configure um serviço de cron externo para chamar automaticamente.');
        } else {
            $this->info('');
            $this->info('Todos os pagamentos foram processados - chame o endpoint /api/payments/check-pending novamente em 1 minuto.');
            $this->info('Ou configure um serviço de cron externo para chamar automaticamente.');
        }

        return Command::SUCCESS;
    }
}
