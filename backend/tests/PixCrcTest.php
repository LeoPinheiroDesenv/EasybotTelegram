<?php

/**
 * Script de teste para validação do cálculo de CRC em códigos PIX
 * 
 * Execute: php backend/tests/PixCrcTest.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\PixCrcService;

$pixCrcService = new PixCrcService();

echo "=== Teste de Cálculo e Validação de CRC para Códigos PIX ===\n\n";

// Exemplo de código PIX (sem CRC ou com CRC inválido para teste)
$testCodes = [
    // Código PIX de exemplo (formato EMV)
    '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913FULANO DE TAL6008BRASILIA62070503***6304',
    // Código PIX completo com CRC válido (exemplo)
    '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913FULANO DE TAL6008BRASILIA62070503***6304A1B2',
];

foreach ($testCodes as $index => $code) {
    echo "--- Teste " . ($index + 1) . " ---\n";
    echo "Código PIX: " . substr($code, 0, 50) . "...\n";
    echo "Comprimento: " . strlen($code) . " caracteres\n\n";

    // Calcula CRC
    $codeWithoutCrc = strlen($code) >= 4 ? substr($code, 0, -4) : $code;
    $crc = $pixCrcService->calculateCrc16($codeWithoutCrc);
    $crcFormatted = $pixCrcService->formatCrc($crc);
    
    echo "CRC Calculado: {$crcFormatted} (decimal: {$crc})\n";
    
    // Adiciona CRC ao código
    $codeWithCrc = $pixCrcService->addCrc($code);
    echo "Código com CRC: " . substr($codeWithCrc, 0, 50) . "...{$crcFormatted}\n";
    
    // Valida CRC
    $isValid = $pixCrcService->validateCrc($codeWithCrc);
    echo "CRC Válido: " . ($isValid ? "SIM ✓" : "NÃO ✗") . "\n";
    
    // Validação completa
    $validation = $pixCrcService->validatePixCode($codeWithCrc);
    echo "\nValidação Completa:\n";
    echo "  Válido: " . ($validation['valid'] ? "SIM ✓" : "NÃO ✗") . "\n";
    echo "  Formato Válido: " . ($validation['format_valid'] ? "SIM ✓" : "NÃO ✗") . "\n";
    echo "  CRC Válido: " . ($validation['crc_valid'] ? "SIM ✓" : "NÃO ✗") . "\n";
    echo "  CRC Atual: " . ($validation['current_crc'] ?? 'N/A') . "\n";
    echo "  CRC Calculado: " . ($validation['calculated_crc'] ?? 'N/A') . "\n";
    
    if (!empty($validation['errors'])) {
        echo "  Erros:\n";
        foreach ($validation['errors'] as $error) {
            echo "    - {$error}\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}

echo "Testes concluídos!\n";

