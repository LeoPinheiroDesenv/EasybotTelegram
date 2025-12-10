# Scripts de Validação CRC para Códigos PIX

Este projeto contém scripts para calcular e validar o CRC-16/CCITT-FALSE em códigos PIX, seguindo o padrão EMV (Europay, Mastercard, Visa).

## 📋 O que é CRC?

CRC (Cyclic Redundancy Check) é um código de verificação usado para detectar erros em dados. No caso do PIX, o CRC-16/CCITT-FALSE é usado para validar a integridade do código de pagamento.

## 📁 Arquivos Criados

### Backend (PHP)
- `backend/app/Services/PixCrcService.php` - Serviço principal para cálculo e validação
- `backend/tests/PixCrcTest.php` - Script de teste
- `backend/examples/pix_crc_example.php` - Exemplos de uso

### Frontend (JavaScript)
- `frontend/src/utils/pixCrc.js` - Utilitário JavaScript para cálculo e validação
- `frontend/src/utils/pixCrc.test.js` - Testes JavaScript

## 🚀 Como Usar

### Backend (PHP)

#### 1. Validar um código PIX

```php
use App\Services\PixCrcService;

$pixCrcService = new PixCrcService();
$pixCode = "00020126580014br.gov.bcb.pix...6304A1B2";

$validation = $pixCrcService->validatePixCode($pixCode);
if ($validation['valid']) {
    echo "Código PIX válido!";
} else {
    foreach ($validation['errors'] as $error) {
        echo $error;
    }
}
```

#### 2. Adicionar CRC a um código PIX

```php
$pixCodeWithoutCrc = "00020126580014br.gov.bcb.pix...6304";
$pixCodeWithCrc = $pixCrcService->addCrc($pixCodeWithoutCrc);
```

#### 3. Validar apenas o CRC

```php
$isValid = $pixCrcService->validateCrc($pixCode);
```

#### 4. Calcular CRC manualmente

```php
$crc = $pixCrcService->calculateCrc16($data);
$crcFormatted = $pixCrcService->formatCrc($crc);
```

### Frontend (JavaScript)

#### 1. Importar o utilitário

```javascript
import { validatePixCode, addCrc, validateCrc } from './utils/pixCrc';
// ou
import pixCrc from './utils/pixCrc';
```

#### 2. Validar um código PIX

```javascript
const pixCode = "00020126580014br.gov.bcb.pix...6304A1B2";
const validation = validatePixCode(pixCode);

if (validation.valid) {
    console.log("Código PIX válido!");
} else {
    validation.errors.forEach(error => {
        console.error(error);
    });
}
```

#### 3. Adicionar CRC a um código PIX

```javascript
const pixCodeWithoutCrc = "00020126580014br.gov.bcb.pix...6304";
const pixCodeWithCrc = addCrc(pixCodeWithoutCrc);
```

#### 4. Validar apenas o CRC

```javascript
const isValid = validateCrc(pixCode);
```

## 🧪 Executar Testes

### Backend

```bash
cd backend
php tests/PixCrcTest.php
```

### Frontend

```bash
cd frontend
npm test -- pixCrc.test.js
```

Ou abra o arquivo `pixCrc.test.js` no navegador e veja o console.

## 📖 Exemplos de Uso

### Exemplo 1: Validar código PIX recebido

```php
// No PaymentService ou similar
$pixCode = $pixData->qr_code ?? null;
$validation = $pixCrcService->validatePixCode($pixCode);

if (!$validation['valid']) {
    // Corrige o CRC se necessário
    $pixCode = $pixCrcService->addCrc($pixCode);
}
```

### Exemplo 2: Validar antes de gerar QR Code

```javascript
// No frontend, antes de gerar QR Code
const validation = validatePixCode(pixCode);
if (!validation.valid) {
    // Corrige o CRC
    pixCode = addCrc(pixCode);
    // Agora pode gerar o QR Code com segurança
}
```

## 🔍 Detalhes Técnicos

### CRC-16/CCITT-FALSE

- **Polinômio**: x^16 + x^12 + x^5 + 1 (0x1021)
- **Valor inicial**: 0xFFFF
- **Formato**: 4 dígitos hexadecimal (uppercase)
- **Posição**: Últimos 4 caracteres do código PIX

### Formato do Código PIX

- Deve começar com `000201` (padrão EMV)
- Comprimento mínimo: 100 caracteres
- CRC: Últimos 4 caracteres (hexadecimal)

## ⚠️ Observações Importantes

1. **Normalização**: Os scripts removem automaticamente espaços e quebras de linha antes de validar
2. **Case-insensitive**: A comparação de CRC é case-insensitive
3. **Validação completa**: A função `validatePixCode()` valida tanto o formato EMV quanto o CRC
4. **Correção automática**: Use `addCrc()` para corrigir ou adicionar CRC a códigos PIX

## 🔗 Integração com PaymentService

Para integrar no `PaymentService`, você pode adicionar validação após receber o código PIX:

```php
// Em PaymentService.php, após receber o código PIX
$pixCrcService = new PixCrcService();
$validation = $pixCrcService->validatePixCode($pixCode);

if (!$validation['valid']) {
    Log::warning('Código PIX com CRC inválido, corrigindo...', [
        'errors' => $validation['errors'],
        'current_crc' => $validation['current_crc'],
        'calculated_crc' => $validation['calculated_crc']
    ]);
    
    // Corrige o CRC
    $pixCode = $pixCrcService->addCrc($pixCode);
}
```

## 📚 Referências

- [Especificação EMV QR Code](https://www.emvco.com/emv-technologies/qrcodes/)
- [Padrão PIX - Banco Central do Brasil](https://www.bcb.gov.br/estabilidadefinanceira/pix)

