/**
 * Testes para validação do cálculo de CRC em códigos PIX
 * 
 * Execute: npm test -- pixCrc.test.js
 * ou abra no navegador e veja o console
 */

import { 
  calculateCrc16, 
  formatCrc, 
  validateCrc, 
  addCrc, 
  validatePixCode 
} from './pixCrc';

// Exemplo de código PIX (sem CRC ou com CRC inválido para teste)
const testCodes = [
  // Código PIX de exemplo (formato EMV)
  '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913FULANO DE TAL6008BRASILIA62070503***6304',
  // Código PIX completo com CRC válido (exemplo)
  '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913FULANO DE TAL6008BRASILIA62070503***6304A1B2',
];

console.log('=== Teste de Cálculo e Validação de CRC para Códigos PIX ===\n');

testCodes.forEach((code, index) => {
  console.log(`--- Teste ${index + 1} ---`);
  console.log(`Código PIX: ${code.substring(0, 50)}...`);
  console.log(`Comprimento: ${code.length} caracteres\n`);

  // Calcula CRC
  const codeWithoutCrc = code.length >= 4 ? code.substring(0, code.length - 4) : code;
  const crc = calculateCrc16(codeWithoutCrc);
  const crcFormatted = formatCrc(crc);
  
  console.log(`CRC Calculado: ${crcFormatted} (decimal: ${crc})`);
  
  // Adiciona CRC ao código
  const codeWithCrc = addCrc(code);
  console.log(`Código com CRC: ${codeWithCrc.substring(0, 50)}...${crcFormatted}`);
  
  // Valida CRC
  const isValid = validateCrc(codeWithCrc);
  console.log(`CRC Válido: ${isValid ? 'SIM ✓' : 'NÃO ✗'}`);
  
  // Validação completa
  const validation = validatePixCode(codeWithCrc);
  console.log('\nValidação Completa:');
  console.log(`  Válido: ${validation.valid ? 'SIM ✓' : 'NÃO ✗'}`);
  console.log(`  Formato Válido: ${validation.formatValid ? 'SIM ✓' : 'NÃO ✗'}`);
  console.log(`  CRC Válido: ${validation.crcValid ? 'SIM ✓' : 'NÃO ✗'}`);
  console.log(`  CRC Atual: ${validation.currentCrc || 'N/A'}`);
  console.log(`  CRC Calculado: ${validation.calculatedCrc || 'N/A'}`);
  
  if (validation.errors.length > 0) {
    console.log('  Erros:');
    validation.errors.forEach(error => {
      console.log(`    - ${error}`);
    });
  }
  
  console.log('\n' + '='.repeat(60) + '\n');
});

console.log('Testes concluídos!');

// Exporta para uso em testes automatizados
export { testCodes };

