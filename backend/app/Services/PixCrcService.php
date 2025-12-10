<?php

namespace App\Services;

/**
 * Serviço para cálculo e validação de CRC-16/CCITT-FALSE em códigos PIX
 * 
 * O código PIX segue o padrão EMV (Europay, Mastercard, Visa) e utiliza
 * CRC-16/CCITT-FALSE para validação de integridade.
 */
class PixCrcService
{
    /**
     * Polinômio CRC-16/CCITT-FALSE
     * Polinômio: x^16 + x^12 + x^5 + 1 (0x1021)
     */
    private const CRC16_POLYNOMIAL = 0x1021;
    
    /**
     * Valor inicial do CRC (0xFFFF para CCITT-FALSE)
     */
    private const CRC16_INITIAL = 0xFFFF;

    /**
     * Calcula o CRC-16/CCITT-FALSE de uma string
     * 
     * @param string $data Dados para calcular o CRC
     * @return int CRC-16 calculado (valor de 0 a 65535)
     */
    public function calculateCrc16(string $data): int
    {
        $crc = self::CRC16_INITIAL;
        $dataLength = strlen($data);

        for ($i = 0; $i < $dataLength; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ self::CRC16_POLYNOMIAL) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return $crc;
    }

    /**
     * Formata o CRC como string hexadecimal com 4 dígitos (uppercase)
     * 
     * @param int $crc Valor do CRC
     * @return string CRC formatado (ex: "A1B2")
     */
    public function formatCrc(int $crc): string
    {
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Extrai o código PIX sem o CRC (remove os últimos 4 caracteres)
     * 
     * @param string $pixCode Código PIX completo
     * @return string Código PIX sem CRC
     */
    public function extractCodeWithoutCrc(string $pixCode): string
    {
        // Remove os últimos 4 caracteres (CRC)
        return substr($pixCode, 0, -4);
    }

    /**
     * Extrai o CRC do código PIX (últimos 4 caracteres)
     * 
     * @param string $pixCode Código PIX completo
     * @return string CRC extraído
     */
    public function extractCrc(string $pixCode): string
    {
        return substr($pixCode, -4);
    }

    /**
     * Valida o CRC de um código PIX
     * 
     * @param string $pixCode Código PIX completo (com CRC)
     * @return bool True se o CRC for válido, False caso contrário
     */
    public function validateCrc(string $pixCode): bool
    {
        // Verifica se o código tem pelo menos 4 caracteres (CRC)
        if (strlen($pixCode) < 4) {
            return false;
        }

        // Extrai o código sem CRC e o CRC atual
        $codeWithoutCrc = $this->extractCodeWithoutCrc($pixCode);
        $currentCrc = $this->extractCrc($pixCode);

        // Calcula o CRC esperado
        $calculatedCrc = $this->calculateCrc16($codeWithoutCrc);
        $expectedCrc = $this->formatCrc($calculatedCrc);

        // Compara os CRCs (case-insensitive)
        return strtoupper($currentCrc) === strtoupper($expectedCrc);
    }

    /**
     * Adiciona ou atualiza o CRC em um código PIX
     * 
     * @param string $pixCode Código PIX (com ou sem CRC)
     * @return string Código PIX com CRC válido
     */
    public function addCrc(string $pixCode): string
    {
        // Remove CRC existente se houver (últimos 4 caracteres)
        // Se o código original tinha menos de 4 caracteres, não tinha CRC
        if (strlen($pixCode) >= 4) {
            $codeWithoutCrc = substr($pixCode, 0, -4);
        } else {
            $codeWithoutCrc = $pixCode;
        }

        // Calcula e adiciona o CRC
        $crc = $this->calculateCrc16($codeWithoutCrc);
        $crcFormatted = $this->formatCrc($crc);

        return $codeWithoutCrc . $crcFormatted;
    }

    /**
     * Valida um código PIX completo (formato EMV e CRC)
     * 
     * @param string $pixCode Código PIX completo
     * @return array Resultado da validação com detalhes
     */
    public function validatePixCode(string $pixCode): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'crc_valid' => false,
            'format_valid' => false,
            'calculated_crc' => null,
            'current_crc' => null
        ];

        // Remove espaços e quebras de linha
        $pixCode = trim($pixCode);
        $pixCode = preg_replace('/\s+/', '', $pixCode);

        // Valida formato básico (deve começar com 000201)
        if (!str_starts_with($pixCode, '000201')) {
            $result['errors'][] = 'Código PIX não começa com 000201 (formato EMV inválido)';
            return $result;
        }
        $result['format_valid'] = true;

        // Valida comprimento mínimo
        if (strlen($pixCode) < 100) {
            $result['errors'][] = 'Código PIX muito curto (mínimo 100 caracteres)';
            return $result;
        }

        // Valida CRC
        if (strlen($pixCode) < 4) {
            $result['errors'][] = 'Código PIX não contém CRC';
            return $result;
        }

        $codeWithoutCrc = $this->extractCodeWithoutCrc($pixCode);
        $currentCrc = $this->extractCrc($pixCode);
        $calculatedCrc = $this->formatCrc($this->calculateCrc16($codeWithoutCrc));

        $result['current_crc'] = strtoupper($currentCrc);
        $result['calculated_crc'] = $calculatedCrc;

        if (strtoupper($currentCrc) === $calculatedCrc) {
            $result['crc_valid'] = true;
            $result['valid'] = true;
        } else {
            $result['errors'][] = "CRC inválido. Esperado: {$calculatedCrc}, Encontrado: {$currentCrc}";
        }

        return $result;
    }
}

