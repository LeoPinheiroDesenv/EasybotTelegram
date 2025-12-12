# Solução para Erro "Chave não localizada" do Mercado Pago

## Problema Identificado

O sistema estava retornando o erro "Chave não localizada" quando tentava verificar ou processar pagamentos PIX no Mercado Pago.

## Causa do Problema

O erro "Chave não localizada" ocorre quando:
1. O `payment_id` armazenado na transação está incorreto ou inválido
2. O pagamento foi deletado no Mercado Pago
3. O pagamento não existe mais na conta do Mercado Pago
4. Há inconsistência entre o `payment_id` salvo e o pagamento real

## Solução Implementada

### 1. Detecção Específica do Erro

Implementada detecção específica para o erro "Chave não localizada" em todos os pontos onde o sistema busca pagamentos:

- Verifica múltiplas variações da mensagem de erro:
  - "chave não localizada"
  - "key not found"
  - "not found"
  - Status HTTP 404

### 2. Tratamento em Múltiplos Pontos

O tratamento foi implementado em:

#### a) `PaymentService::checkPaymentStatusImmediately()`
- Detecta quando o pagamento não é encontrado
- Registra no metadata da transação
- Não interrompe o fluxo principal

#### b) Webhook do Mercado Pago (`PaymentController::mercadoPagoWebhook()`)
- Detecta quando o webhook tenta buscar um pagamento inexistente
- Marca no metadata da transação
- Retorna sucesso para evitar retry desnecessário do webhook

#### c) Endpoint de Verificação Automática (`/api/payments/check-pending`)
- Detecta pagamentos não encontrados durante verificação periódica
- Conta quantas vezes o pagamento não foi encontrado
- Após 3 tentativas, marca a transação como `failed`

#### d) Comando Artisan (`CheckPendingPaymentsCommand`)
- Detecta e trata o erro durante execução do comando
- Exibe mensagens claras no console
- Marca transações como falhadas após múltiplas tentativas

### 3. Sistema de Contagem e Marcação

- **Contador de tentativas**: Registra quantas vezes o pagamento não foi encontrado
- **Metadata detalhado**: Salva informações sobre quando e por que não foi encontrado
- **Marcação automática**: Após 3 tentativas sem sucesso, marca a transação como `failed`

### 4. Logs Detalhados

Todos os pontos de tratamento registram logs detalhados incluindo:
- `transaction_id`
- `payment_id` que foi buscado
- Status HTTP da resposta
- Conteúdo completo da resposta da API
- Contador de tentativas

## Estrutura do Metadata

Quando um pagamento não é encontrado, o metadata da transação é atualizado com:

```json
{
  "payment_not_found": true,
  "payment_not_found_at": "2025-12-12T10:30:00Z",
  "payment_not_found_error": "Chave não localizada",
  "payment_not_found_count": 1,
  "payment_not_found_via": "webhook" // ou "check-pending" ou "immediate"
}
```

## Comportamento do Sistema

### Primeira Tentativa
- Detecta que o pagamento não foi encontrado
- Registra no metadata
- Continua processamento normalmente
- Log de warning é gerado

### Segunda Tentativa
- Detecta novamente que não foi encontrado
- Incrementa contador
- Continua processamento

### Terceira Tentativa
- Detecta que não foi encontrado pela terceira vez
- **Marca a transação como `failed`**
- Log de warning é gerado
- Transação não será mais verificada automaticamente

## Benefícios

1. **Não interrompe o fluxo**: O erro não quebra o processamento de outros pagamentos
2. **Diagnóstico claro**: Logs detalhados facilitam identificar o problema
3. **Limpeza automática**: Transações inválidas são marcadas como falhadas automaticamente
4. **Rastreabilidade**: Metadata completo permite entender o histórico do problema

## Troubleshooting

### Verificar Transações com Pagamento Não Encontrado

```sql
SELECT id, gateway_transaction_id, status, metadata 
FROM transactions 
WHERE JSON_EXTRACT(metadata, '$.payment_not_found') = true;
```

### Verificar Contador de Tentativas

```sql
SELECT id, gateway_transaction_id, 
       JSON_EXTRACT(metadata, '$.payment_not_found_count') as tentativas
FROM transactions 
WHERE JSON_EXTRACT(metadata, '$.payment_not_found_count') >= 1;
```

### Limpar Transações com Pagamento Não Encontrado

Se necessário, você pode marcar manualmente como falhadas:

```sql
UPDATE transactions 
SET status = 'failed' 
WHERE JSON_EXTRACT(metadata, '$.payment_not_found') = true
  AND JSON_EXTRACT(metadata, '$.payment_not_found_count') >= 3;
```

## Prevenção

Para evitar esse erro no futuro:

1. **Validação de payment_id**: Sempre validar se o `payment_id` existe antes de salvar
2. **Verificação imediata**: Após criar pagamento, verificar se foi criado corretamente
3. **Logs de criação**: Registrar o `payment_id` completo quando o pagamento é criado
4. **Monitoramento**: Acompanhar logs para identificar padrões de erro

## Logs Importantes

Procure por estas mensagens nos logs:

- `⚠️ Pagamento não encontrado no Mercado Pago (Chave não localizada)`
- `🔄 Transação marcada como falhada após múltiplas tentativas`

## Conclusão

O sistema agora trata adequadamente o erro "Chave não localizada", registrando informações detalhadas e marcando transações inválidas automaticamente após múltiplas tentativas, sem interromper o processamento de outros pagamentos válidos.
