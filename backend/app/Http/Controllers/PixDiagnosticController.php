<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\PixCrcService;

class PixDiagnosticController extends Controller
{
    /**
     * Valida um código PIX e retorna diagnóstico completo
     */
    public function validatePixCode(Request $request)
    {
        $request->validate([
            'pix_code' => 'required|string|min:100',
        ]);
        
        $pixCode = $request->input('pix_code');
        $pixCrcService = new PixCrcService();
        
        $validation = $pixCrcService->validatePixCode($pixCode);
        
        $diagnostic = [
            'pix_code' => [
                'length' => strlen($pixCode),
                'start' => substr($pixCode, 0, 30),
                'end' => substr($pixCode, -10),
                'crc' => substr($pixCode, -4),
            ],
            'validation' => $validation,
            'recommendations' => [],
        ];
        
        // Adiciona recomendações baseadas na validação
        if (!$validation['crc_valid']) {
            $diagnostic['recommendations'][] = [
                'type' => 'error',
                'message' => 'CRC inválido - o banco não reconhecerá este código',
                'action' => 'Corrigir o CRC antes de usar o código',
                'corrected_code' => $pixCrcService->addCrc($pixCode),
            ];
        }
        
        if (!$validation['format_valid']) {
            $diagnostic['recommendations'][] = [
                'type' => 'error',
                'message' => 'Formato inválido - código não começa com 000201',
                'action' => 'Verificar se o código está completo',
            ];
        }
        
        if ($validation['valid']) {
            $diagnostic['recommendations'][] = [
                'type' => 'success',
                'message' => 'Código PIX válido e pronto para uso',
            ];
        }
        
        return response()->json($diagnostic);
    }
    
    /**
     * Retorna estatísticas de correção de CRC
     */
    public function getCrcStatistics(Request $request)
    {
        $days = (int) $request->input('days', 7);
        $startDate = Carbon::now()->subDays($days);
        
        $transactions = Transaction::where('payment_method', 'pix')
            ->where('gateway', 'mercadopago')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('metadata')
            ->get();
        
        $totalTransactions = $transactions->count();
        $crcCorrections = 0;
        $crcValid = 0;
        $byEnvironment = [];
        
        foreach ($transactions as $transaction) {
            $metadata = $transaction->metadata ?? [];
            
            if (isset($metadata['crc_correction'])) {
                $crcCorrections++;
                $env = $metadata['crc_correction']['mercado_pago_environment'] ?? 'unknown';
                if (!isset($byEnvironment[$env])) {
                    $byEnvironment[$env] = 0;
                }
                $byEnvironment[$env]++;
            } else {
                $crcValid++;
            }
        }
        
        return response()->json([
            'period' => [
                'start' => $startDate->toIso8601String(),
                'end' => Carbon::now()->toIso8601String(),
                'days' => $days,
            ],
            'statistics' => [
                'total_transactions' => $totalTransactions,
                'crc_corrections' => $crcCorrections,
                'crc_valid' => $crcValid,
                'correction_rate' => $totalTransactions > 0 
                    ? round(($crcCorrections / $totalTransactions) * 100, 2) 
                    : 0,
                'by_environment' => $byEnvironment,
            ],
        ]);
    }
    
    /**
     * Gera relatório para reportar ao Mercado Pago
     */
    public function generateMercadoPagoReport(Request $request)
    {
        $days = (int) $request->input('days', 30);
        $startDate = Carbon::now()->subDays($days);
        
        $transactions = Transaction::where('payment_method', 'pix')
            ->where('gateway', 'mercadopago')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('metadata')
            ->get();
        
        $crcCorrections = [];
        
        foreach ($transactions as $transaction) {
            $metadata = $transaction->metadata ?? [];
            
            if (isset($metadata['crc_correction'])) {
                $correction = $metadata['crc_correction'];
                $crcCorrections[] = [
                    'payment_id' => $correction['payment_id'] ?? null,
                    'timestamp' => $correction['timestamp'] ?? null,
                    'environment' => $correction['mercado_pago_environment'] ?? null,
                    'crc_received' => $correction['crc_before'] ?? null,
                    'crc_calculated' => $correction['crc_calculated'] ?? null,
                    'crc_corrected' => $correction['crc_after'] ?? null,
                    'pix_code_length' => $correction['pix_code_length'] ?? null,
                ];
            }
        }
        
        $report = [
            'report_type' => 'pix_crc_validation_issue',
            'generated_at' => Carbon::now()->toIso8601String(),
            'period' => [
                'start' => $startDate->toIso8601String(),
                'end' => Carbon::now()->toIso8601String(),
                'days' => $days,
            ],
            'summary' => [
                'total_incorrect_crc' => count($crcCorrections),
                'total_transactions' => $transactions->count(),
                'error_rate' => $transactions->count() > 0 
                    ? round((count($crcCorrections) / $transactions->count()) * 100, 2) 
                    : 0,
            ],
            'issue_description' => 'Códigos PIX retornados pela API do Mercado Pago estão sendo recebidos com CRC inválido, fazendo com que os bancos não reconheçam os QR Codes. A aplicação está corrigindo automaticamente o CRC para garantir funcionamento.',
            'examples' => array_slice($crcCorrections, 0, 10), // Primeiros 10 exemplos
            'technical_details' => [
                'endpoint_used' => '/v1/payments',
                'field_used' => 'point_of_interaction.transaction_data.qr_code',
                'crc_algorithm' => 'CRC-16/CCITT-FALSE',
                'validation_method' => 'Local validation and correction',
            ],
        ];
        
        return response()->json($report);
    }
}
