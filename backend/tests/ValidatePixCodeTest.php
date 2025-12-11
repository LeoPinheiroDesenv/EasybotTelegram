<?php

namespace Tests;

use App\Services\PixCrcService;
use PHPUnit\Framework\TestCase;

/**
 * Teste para validar códigos PIX
 * Execute: php artisan test --filter ValidatePixCodeTest
 */
class ValidatePixCodeTest extends TestCase
{
    protected $pixCrcService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pixCrcService = new PixCrcService();
    }

    /**
     * Testa se o código PIX está no formato correto
     */
    public function testPixCodeFormat()
    {
        // Exemplo de código PIX válido (formato EMV)
        $validPixCode = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-426655440000520400005303986540510.005802BR5913TESTE MERCADO6009SAO PAULO62070503***6304';
        
        // Remove o CRC e recalcula
        $codeWithoutCrc = substr($validPixCode, 0, -4);
        $validPixCode = $this->pixCrcService->addCrc($codeWithoutCrc);
        
        $validation = $this->pixCrcService->validatePixCode($validPixCode);
        
        $this->assertTrue($validation['valid'], 'Código PIX deve ser válido');
        $this->assertTrue($validation['format_valid'], 'Formato deve ser válido');
        $this->assertTrue($validation['crc_valid'], 'CRC deve ser válido');
    }

    /**
     * Testa se o código PIX começa com 000201
     */
    public function testPixCodeStartsWith000201()
    {
        $pixCode = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-426655440000520400005303986540510.005802BR5913TESTE MERCADO6009SAO PAULO62070503***6304';
        $codeWithoutCrc = substr($pixCode, 0, -4);
        $pixCode = $this->pixCrcService->addCrc($codeWithoutCrc);
        
        $this->assertStringStartsWith('000201', $pixCode, 'Código PIX deve começar com 000201');
    }

    /**
     * Testa se o CRC está sendo calculado corretamente
     */
    public function testCrcCalculation()
    {
        $codeWithoutCrc = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-426655440000520400005303986540510.005802BR5913TESTE MERCADO6009SAO PAULO62070503***';
        $pixCode = $this->pixCrcService->addCrc($codeWithoutCrc);
        
        $validation = $this->pixCrcService->validatePixCode($pixCode);
        
        $this->assertTrue($validation['crc_valid'], 'CRC deve ser válido após cálculo');
        $this->assertEquals($validation['current_crc'], $validation['calculated_crc'], 'CRC atual deve ser igual ao calculado');
    }

    /**
     * Testa se espaços são removidos corretamente
     */
    public function testSpaceRemoval()
    {
        $codeWithSpaces = '000201 26580014 br.gov.bcb.pix 0136123e4567-e12b-12d1-a456-426655440000 52040000 5303986 540510.00 5802BR 5913TESTE MERCADO 6009SAO PAULO 62070503*** 6304';
        $codeWithoutCrc = substr($codeWithSpaces, 0, -4);
        $codeWithoutCrc = preg_replace('/\s+/', '', $codeWithoutCrc);
        $pixCode = $this->pixCrcService->addCrc($codeWithoutCrc);
        
        $this->assertStringNotContainsString(' ', $pixCode, 'Código PIX não deve conter espaços');
    }
}
