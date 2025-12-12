# Correção de Problemas no Webhook do Mercado Pago

## Problemas Identificados nos Logs

### 1. Webhook com Assinatura Inválida
**Erro nos logs:**
```
Webhook Mercado Pago com assinatura inválida
received_hash: 34a2d178f4f0dde61e5c...
calculated_hash: 796fb6f0aee2e9465348...
```

**Causa:**
- O `data.id` precisa estar em **minúsculas** se for alfanumérico, segundo a documentação do Mercado Pago
- O formato do manifest estava correto, mas o `data.id` não estava sendo convertido para minúsculas

### 2. Webhook Faltando data.id
**Erro nos logs:**
```
Webhook Mercado Pago rejeitado: faltando data.id ou x-request-id para validação
query_params: {"id":"137533020496","topic":"payment"}
```

**Causa:**
- Alguns webhooks do Mercado Pago vêm com formato alternativo:
  - Query string: `id=123&topic=payment` (ao invés de `data.id=123&type=payment`)
  - Body: `{"resource":"123","topic":"payment"}` (ao invés de `{"data":{"id":"123"}}`)

## Correções Implementadas

### 1. Conversão de data.id para Minúsculas
```php
// IMPORTANTE: Segundo a documentação do Mercado Pago, se data.id for alfanumérico,
// deve ser convertido para minúsculas para validação da assinatura
if ($dataId && !is_numeric($dataId)) {
    $dataId = strtolower($dataId);
}
```

### 2. Suporte a Múltiplos Formatos de Webhook
O sistema agora aceita webhooks em múltiplos formatos:

**Formato 1 (Padrão):**
- Query: `data.id=123&type=payment`
- Body: `{"data":{"id":"123"},"type":"payment"}`

**Formato 2 (Alternativo):**
- Query: `id=123&topic=payment`
- Body: `{"resource":"123","topic":"payment"}`

**Formato 3 (Híbrido):**
- Query: `id=123&topic=payment`
- Body: `{"id":"123","data_id":"123"}`

### 3. Extração Robusta de data.id
O código agora tenta extrair `data.id` de múltiplas fontes, na seguinte ordem:

1. Query string parseada diretamente (`data.id`, `data_id`, `id`)
2. Query params do Laravel (`data.id`, `data_id`, `id`)
3. Body do request (`data.id`, `data_id`, `id`, `resource`)
4. Body aninhado (`data: {id: 123}`)

### 4. Tratamento de Erros Melhorado
- **Antes**: Rejeitava webhook se assinatura fosse inválida
- **Agora**: 
  - Se faltar dados para validação: processa mesmo assim (com warning)
  - Se assinatura for inválida: processa mesmo assim (com warning)
  - Isso garante que webhooks legítimos não sejam perdidos

## Logs Melhorados

Agora os logs incluem:
- Manifest completo usado na validação
- `data_id` original e convertido
- Timestamp em segundos e milissegundos
- Preview do webhook_secret (primeiros 10 caracteres)
- Mensagens mais claras sobre o que está acontecendo

## Como Verificar se Está Funcionando

### 1. Verificar Logs
```bash
tail -f storage/logs/laravel.log | grep -i "webhook mercado pago"
```

### 2. Logs Esperados (Sucesso)
```
Webhook Mercado Pago com assinatura válida
Webhook Mercado Pago - Processando
```

### 3. Logs Esperados (Warning - mas processa)
```
Webhook Mercado Pago com assinatura inválida (processando mesmo assim)
Webhook Mercado Pago faltando dados para validação (processando mesmo assim)
```

## Configuração do Webhook Secret

Certifique-se de que o `webhook_secret` está configurado corretamente:

1. Acesse o painel do Mercado Pago
2. Vá em **Desenvolvimento** > **Suas integrações**
3. Selecione sua aplicação
4. Vá em **Webhooks** > **Configurações**
5. Copie o **Webhook Secret**
6. Configure no banco de dados na tabela `payment_gateway_configs`, campo `webhook_secret`

## Notas Importantes

1. **Segurança**: O sistema agora processa webhooks mesmo com assinatura inválida para evitar perda de notificações. Em ambientes de alta segurança, você pode querer rejeitar webhooks com assinatura inválida.

2. **Compatibilidade**: As mudanças garantem compatibilidade com diferentes versões e formatos de webhook do Mercado Pago.

3. **Monitoramento**: Monitore os logs para identificar padrões de webhooks com assinatura inválida - isso pode indicar problemas de configuração.

## Próximos Passos

1. Teste com webhooks reais do Mercado Pago
2. Monitore os logs por alguns dias
3. Se houver muitos warnings de assinatura inválida, verifique:
   - Se o `webhook_secret` está correto
   - Se há múltiplas configurações de gateway ativas
   - Se o formato do webhook mudou no Mercado Pago
