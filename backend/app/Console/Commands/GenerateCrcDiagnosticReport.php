<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateCrcDiagnosticReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pix:crc-diagnostic-report 
                            {--days=7 : Número de dias para analisar}
                            {--output=console : Formato de saída (console, json, file)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera relatório de diagnóstico sobre correções de CRC em códigos PIX';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $outputFormat = $this->option('output');
        
        $this->info("Gerando relatório de diagnóstico de CRC para os últimos {$days} dias...");
        
        $startDate = Carbon::now()->subDays($days);
        
        // Busca transações com correção de CRC
        $transactions = Transaction::where('payment_method', 'pix')
            ->where('gateway', 'mercadopago')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('metadata')
            ->get();
        
        $totalTransactions = $transactions->count();
        $crcCorrections = [];
        $crcValid = [];
        $errors = [];
        
        foreach ($transactions as $transaction) {
            $metadata = $transaction->metadata ?? [];
            
            if (isset($metadata['crc_correction'])) {
                $crcCorrections[] = [
                    'transaction_id' => $transaction->id,
                    'payment_id' => $metadata['crc_correction']['payment_id'] ?? null,
                    'bot_id' => $metadata['crc_correction']['bot_id'] ?? null,
                    'plan_id' => $metadata['crc_correction']['plan_id'] ?? null,
                    'timestamp' => $metadata['crc_correction']['timestamp'] ?? null,
                    'environment' => $metadata['crc_correction']['mercado_pago_environment'] ?? null,
                    'crc_before' => $metadata['crc_correction']['crc_before'] ?? null,
                    'crc_after' => $metadata['crc_correction']['crc_after'] ?? null,
                    'correction_successful' => $metadata['crc_correction']['correction_successful'] ?? false,
                    'created_at' => $transaction->created_at->toIso8601String(),
                ];
            } else {
                // Verifica se tem código PIX (pode ter sido criado antes da correção)
                if (isset($metadata['pix_code'])) {
                    $crcValid[] = [
                        'transaction_id' => $transaction->id,
                        'created_at' => $transaction->created_at->toIso8601String(),
                    ];
                }
            }
        }
        
        $report = [
            'period' => [
                'start' => $startDate->toIso8601String(),
                'end' => Carbon::now()->toIso8601String(),
                'days' => $days,
            ],
            'summary' => [
                'total_transactions' => $totalTransactions,
                'transactions_with_crc_correction' => count($crcCorrections),
                'transactions_with_valid_crc' => count($crcValid),
                'crc_correction_rate' => $totalTransactions > 0 
                    ? round((count($crcCorrections) / $totalTransactions) * 100, 2) 
                    : 0,
            ],
            'crc_corrections' => $crcCorrections,
            'statistics' => $this->calculateStatistics($crcCorrections),
        ];
        
        // Gera saída no formato solicitado
        switch ($outputFormat) {
            case 'json':
                $this->outputJson($report);
                break;
            case 'file':
                $this->outputFile($report);
                break;
            default:
                $this->outputConsole($report);
        }
        
        return 0;
    }
    
    /**
     * Calcula estatísticas das correções de CRC
     */
    private function calculateStatistics(array $corrections): array
    {
        if (empty($corrections)) {
            return [
                'total_corrections' => 0,
                'successful_corrections' => 0,
                'failed_corrections' => 0,
                'by_environment' => [],
            ];
        }
        
        $successful = 0;
        $failed = 0;
        $byEnvironment = [];
        
        foreach ($corrections as $correction) {
            if ($correction['correction_successful'] ?? false) {
                $successful++;
            } else {
                $failed++;
            }
            
            $env = $correction['environment'] ?? 'unknown';
            if (!isset($byEnvironment[$env])) {
                $byEnvironment[$env] = 0;
            }
            $byEnvironment[$env]++;
        }
        
        return [
            'total_corrections' => count($corrections),
            'successful_corrections' => $successful,
            'failed_corrections' => $failed,
            'success_rate' => count($corrections) > 0 
                ? round(($successful / count($corrections)) * 100, 2) 
                : 0,
            'by_environment' => $byEnvironment,
        ];
    }
    
    /**
     * Exibe relatório no console
     */
    private function outputConsole(array $report): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  RELATÓRIO DE DIAGNÓSTICO DE CRC - CÓDIGOS PIX');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();
        
        $this->info("Período: {$report['period']['start']} até {$report['period']['end']}");
        $this->info("Dias analisados: {$report['period']['days']}");
        $this->newLine();
        
        $this->info('📊 RESUMO:');
        $this->line("  Total de transações PIX: {$report['summary']['total_transactions']}");
        $this->line("  Transações com CRC corrigido: {$report['summary']['transactions_with_crc_correction']}");
        $this->line("  Transações com CRC válido: {$report['summary']['transactions_with_valid_crc']}");
        $this->line("  Taxa de correção de CRC: {$report['summary']['crc_correction_rate']}%");
        $this->newLine();
        
        if (!empty($report['statistics']['total_corrections'])) {
            $this->info('📈 ESTATÍSTICAS DE CORREÇÃO:');
            $this->line("  Total de correções: {$report['statistics']['total_corrections']}");
            $this->line("  Correções bem-sucedidas: {$report['statistics']['successful_corrections']}");
            $this->line("  Correções falhadas: {$report['statistics']['failed_corrections']}");
            $this->line("  Taxa de sucesso: {$report['statistics']['success_rate']}%");
            $this->newLine();
            
            if (!empty($report['statistics']['by_environment'])) {
                $this->info('🌍 POR AMBIENTE:');
                foreach ($report['statistics']['by_environment'] as $env => $count) {
                    $this->line("  {$env}: {$count}");
                }
                $this->newLine();
            }
        }
        
        if (!empty($report['crc_corrections'])) {
            $this->info('🔍 DETALHES DAS CORREÇÕES (últimas 10):');
            $this->table(
                ['ID', 'Payment ID', 'CRC Antes', 'CRC Depois', 'Ambiente', 'Data'],
                array_slice(array_map(function($correction) {
                    return [
                        $correction['transaction_id'],
                        $correction['payment_id'] ?? 'N/A',
                        $correction['crc_before'] ?? 'N/A',
                        $correction['crc_after'] ?? 'N/A',
                        $correction['environment'] ?? 'N/A',
                        Carbon::parse($correction['created_at'])->format('d/m/Y H:i:s'),
                    ];
                }, $report['crc_corrections']), 0, 10)
            );
        }
        
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
    }
    
    /**
     * Exibe relatório em JSON
     */
    private function outputJson(array $report): void
    {
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Salva relatório em arquivo
     */
    private function outputFile(array $report): void
    {
        $filename = 'crc-diagnostic-report-' . date('Y-m-d-His') . '.json';
        $path = storage_path('logs/' . $filename);
        
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->info("Relatório salvo em: {$path}");
    }
}
