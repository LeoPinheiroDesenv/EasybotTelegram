<?php

/**
 * Exemplo de uso do PixCrcService
 * 
 * Este exemplo demonstra como usar o serviço para validar e corrigir códigos PIX
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\PixCrcService;

$pixCrcService = new PixCrcService();

// Exemplo 1: Validar um código PIX existente
echo "=== Exemplo 1: Validar Código PIX ===\n";
$pixCode = "00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913FULANO DE TAL6008BRASILIA62070503***6304A1B2";

$validation = $pixCrcService->validatePixCode($pixCode);
if ($validation['valid']) {
    echo "✓ Código PIX válido!\n";
} else {
    echo "✗ Código PIX inválido:\n";
    foreach ($validation['errors'] as $error) {
        echo "  - {$error}\n";
    }
}
echo "\n";

// Exemplo 2: Adicionar CRC a um código PIX sem CRC
echo "=== Exemplo 2: Adicionar CRC ===\n";
$pixCodeWithoutCrc = "00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913FULANO DE TAL6008BRASILIA62070503***6304";

$pixCodeWithCrc = $pixCrcService->addCrc($pixCodeWithoutCrc);
echo "Código original: " . substr($pixCodeWithoutCrc, 0, 50) . "...\n";
echo "Código com CRC:  " . substr($pixCodeWithCrc, 0, 50) . "..." . substr($pixCodeWithCrc, -4) . "\n";
echo "CRC adicionado: " . substr($pixCodeWithCrc, -4) . "\n";
echo "\n";

// Exemplo 3: Corrigir CRC de um código PIX com CRC inválido
echo "=== Exemplo 3: Corrigir CRC Inválido ===\n";
$pixCodeWithInvalidCrc = "00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913FULANO DE TAL6008BRASILIA62070503***6304XXXX";

$isValid = $pixCrcService->validateCrc($pixCodeWithInvalidCrc);
echo "CRC válido? " . ($isValid ? "SIM" : "NÃO") . "\n";

if (!$isValid) {
    $correctedCode = $pixCrcService->addCrc($pixCodeWithInvalidCrc);
    echo "Código corrigido: " . substr($correctedCode, 0, 50) . "..." . substr($correctedCode, -4) . "\n";
    echo "Novo CRC: " . substr($correctedCode, -4) . "\n";
}
echo "\n";

// Exemplo 4: Calcular apenas o CRC
echo "=== Exemplo 4: Calcular CRC ===\n";
$data = "00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913FULANO DE TAL6008BRASILIA62070503***6304";
$crc = $pixCrcService->calculateCrc16($data);
$crcFormatted = $pixCrcService->formatCrc($crc);
echo "Dados: " . substr($data, 0, 50) . "...\n";
echo "CRC (decimal): {$crc}\n";
echo "CRC (hex): {$crcFormatted}\n";

