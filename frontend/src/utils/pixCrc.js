/**
 * Utilitário para cálculo e validação de CRC-16/CCITT-FALSE em códigos PIX
 * 
 * O código PIX segue o padrão EMV (Europay, Mastercard, Visa) e utiliza
 * CRC-16/CCITT-FALSE para validação de integridade.
 */

/**
 * Polinômio CRC-16/CCITT-FALSE
 * Polinômio: x^16 + x^12 + x^5 + 1 (0x1021)
 */
const CRC16_POLYNOMIAL = 0x1021;

/**
 * Valor inicial do CRC (0xFFFF para CCITT-FALSE)
 */
const CRC16_INITIAL = 0xFFFF;

/**
 * Calcula o CRC-16/CCITT-FALSE de uma string
 * 
 * @param {string} data - Dados para calcular o CRC
 * @returns {number} CRC-16 calculado (valor de 0 a 65535)
 */
export function calculateCrc16(data) {
  let crc = CRC16_INITIAL;
  const dataLength = data.length;

  for (let i = 0; i < dataLength; i++) {
    crc ^= (data.charCodeAt(i) << 8);

    for (let j = 0; j < 8; j++) {
      if (crc & 0x8000) {
        crc = ((crc << 1) ^ CRC16_POLYNOMIAL) & 0xFFFF;
      } else {
        crc = (crc << 1) & 0xFFFF;
      }
    }
  }

  return crc;
}

/**
 * Formata o CRC como string hexadecimal com 4 dígitos (uppercase)
 * 
 * @param {number} crc - Valor do CRC
 * @returns {string} CRC formatado (ex: "A1B2")
 */
export function formatCrc(crc) {
  return crc.toString(16).toUpperCase().padStart(4, '0');
}

/**
 * Extrai o código PIX sem o CRC (remove os últimos 4 caracteres)
 * 
 * @param {string} pixCode - Código PIX completo
 * @returns {string} Código PIX sem CRC
 */
export function extractCodeWithoutCrc(pixCode) {
  return pixCode.substring(0, pixCode.length - 4);
}

/**
 * Extrai o CRC do código PIX (últimos 4 caracteres)
 * 
 * @param {string} pixCode - Código PIX completo
 * @returns {string} CRC extraído
 */
export function extractCrc(pixCode) {
  return pixCode.substring(pixCode.length - 4);
}

/**
 * Valida o CRC de um código PIX
 * 
 * @param {string} pixCode - Código PIX completo (com CRC)
 * @returns {boolean} True se o CRC for válido, False caso contrário
 */
export function validateCrc(pixCode) {
  // Verifica se o código tem pelo menos 4 caracteres (CRC)
  if (pixCode.length < 4) {
    return false;
  }

  // Extrai o código sem CRC e o CRC atual
  const codeWithoutCrc = extractCodeWithoutCrc(pixCode);
  const currentCrc = extractCrc(pixCode);

  // Calcula o CRC esperado
  const calculatedCrc = calculateCrc16(codeWithoutCrc);
  const expectedCrc = formatCrc(calculatedCrc);

  // Compara os CRCs (case-insensitive)
  return currentCrc.toUpperCase() === expectedCrc.toUpperCase();
}

/**
 * Adiciona ou atualiza o CRC em um código PIX
 * 
 * @param {string} pixCode - Código PIX (com ou sem CRC)
 * @returns {string} Código PIX com CRC válido
 */
export function addCrc(pixCode) {
  // Remove CRC existente se houver
  let codeWithoutCrc;
  
  // Se o código original tinha menos de 4 caracteres, não tinha CRC
  if (pixCode.length >= 4) {
    codeWithoutCrc = pixCode.substring(0, pixCode.length - 4);
  } else {
    codeWithoutCrc = pixCode;
  }

  // Calcula e adiciona o CRC
  const crc = calculateCrc16(codeWithoutCrc);
  const crcFormatted = formatCrc(crc);

  return codeWithoutCrc + crcFormatted;
}

/**
 * Valida um código PIX completo (formato EMV e CRC)
 * 
 * @param {string} pixCode - Código PIX completo
 * @returns {Object} Resultado da validação com detalhes
 */
export function validatePixCode(pixCode) {
  const result = {
    valid: false,
    errors: [],
    crcValid: false,
    formatValid: false,
    calculatedCrc: null,
    currentCrc: null
  };

  // Remove espaços e quebras de linha
  const normalizedCode = pixCode.trim().replace(/\s+/g, '');

  // Valida formato básico (deve começar com 000201)
  if (!normalizedCode.startsWith('000201')) {
    result.errors.push('Código PIX não começa com 000201 (formato EMV inválido)');
    return result;
  }
  result.formatValid = true;

  // Valida comprimento mínimo
  if (normalizedCode.length < 100) {
    result.errors.push('Código PIX muito curto (mínimo 100 caracteres)');
    return result;
  }

  // Valida CRC
  if (normalizedCode.length < 4) {
    result.errors.push('Código PIX não contém CRC');
    return result;
  }

  const codeWithoutCrc = extractCodeWithoutCrc(normalizedCode);
  const currentCrc = extractCrc(normalizedCode);
  const calculatedCrc = formatCrc(calculateCrc16(codeWithoutCrc));

  result.currentCrc = currentCrc.toUpperCase();
  result.calculatedCrc = calculatedCrc;

  if (currentCrc.toUpperCase() === calculatedCrc) {
    result.crcValid = true;
    result.valid = true;
  } else {
    result.errors.push(`CRC inválido. Esperado: ${calculatedCrc}, Encontrado: ${currentCrc}`);
  }

  return result;
}

// Exporta tudo como objeto também para uso conveniente
export default {
  calculateCrc16,
  formatCrc,
  extractCodeWithoutCrc,
  extractCrc,
  validateCrc,
  addCrc,
  validatePixCode
};

