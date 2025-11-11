# Guia de Integração - Gateways de Pagamento

Este guia explica como configurar e usar os gateways de pagamento integrados: **Mercado Pago (PIX)** e **Stripe (Cartão de Crédito)**.

## 🔧 Configuração Inicial

### Variáveis de Ambiente

Adicione as seguintes variáveis no arquivo `.env` do backend:

```env
# Mercado Pago
MERCADOPAGO_ACCESS_TOKEN=seu_access_token_aqui
MERCADOPAGO_WEBHOOK_URL=https://seu-dominio.com/api/payments/webhook/mercadopago

# Stripe
STRIPE_SECRET_KEY=sk_test_seu_secret_key_aqui
STRIPE_WEBHOOK_SECRET=whsec_seu_webhook_secret_aqui
STRIPE_RETURN_URL=https://seu-dominio.com/payment/success

# URLs da aplicação
API_URL=https://seu-dominio.com
FRONTEND_URL=https://seu-dominio.com
```

### Obter Credenciais

#### Mercado Pago

1. Acesse [https://www.mercadopago.com.br/developers](https://www.mercadopago.com.br/developers)
2. Crie uma conta ou faça login
3. Vá em "Suas integrações" > "Criar aplicação"
4. Copie o **Access Token** (teste ou produção)
5. Configure a URL do webhook nas configurações da aplicação

#### Stripe

1. Acesse [https://dashboard.stripe.com](https://dashboard.stripe.com)
2. Crie uma conta ou faça login
3. Vá em "Developers" > "API keys"
4. Copie a **Secret key** (teste ou produção)
5. Vá em "Developers" > "Webhooks" e crie um webhook
6. Configure a URL: `https://seu-dominio.com/api/payments/webhook/stripe`
7. Copie o **Signing secret** do webhook

## 💳 Processar Pagamento PIX (Mercado Pago)

### Endpoint

```bash
POST /api/payments/pix
Authorization: Bearer {token}
```

### Request Body

```json
{
  "payment_plan_id": 1,
  "bot_id": 1,
  "contact_id": 123,
  "payer": {
    "email": "cliente@example.com",
    "first_name": "João",
    "last_name": "Silva",
    "identification": {
      "type": "CPF",
      "number": "12345678900"
    }
  }
}
```

### Response

```json
{
  "transaction": {
    "id": 1,
    "amount": "29.90",
    "status": "pending",
    "gateway": "mercadopago",
    "payment_method": "pix",
    "pix_qr_code": "00020126580014BR.GOV.BCB.PIX...",
    "pix_qr_code_base64": "data:image/png;base64,iVBORw0KGgo...",
    "pix_ticket_url": "https://www.mercadopago.com.br/payments/123456/ticket",
    "pix_expiration_date": "2024-11-11T12:00:00.000Z"
  },
  "pix_data": {
    "qr_code": "00020126580014BR.GOV.BCB.PIX...",
    "qr_code_base64": "data:image/png;base64,iVBORw0KGgo...",
    "ticket_url": "https://www.mercadopago.com.br/payments/123456/ticket",
    "expiration_date": "2024-11-11T12:00:00.000Z"
  }
}
```

### Como Usar

1. Faça a requisição para criar o pagamento PIX
2. Exiba o QR code (`pix_qr_code_base64`) para o usuário escanear
3. Ou forneça o código PIX (`pix_qr_code`) para pagamento manual
4. O status será atualizado automaticamente via webhook quando o pagamento for confirmado

## 💳 Processar Pagamento com Cartão (Stripe)

### Endpoint

```bash
POST /api/payments/credit-card
Authorization: Bearer {token}
```

### Opção 1: Com Payment Method ID (Recomendado)

Se você já tem um `payment_method_id` do Stripe (criado no frontend):

```json
{
  "payment_plan_id": 1,
  "bot_id": 1,
  "contact_id": 123,
  "payment_method_id": "pm_1234567890abcdef"
}
```

### Opção 2: Com Dados do Cartão

```json
{
  "payment_plan_id": 1,
  "bot_id": 1,
  "contact_id": 123,
  "card_data": {
    "number": "4242424242424242",
    "exp_month": 12,
    "exp_year": 2025,
    "cvc": "123",
    "billing_details": {
      "name": "João Silva",
      "email": "cliente@example.com"
    }
  }
}
```

### Response

```json
{
  "transaction": {
    "id": 2,
    "amount": "29.90",
    "status": "approved",
    "gateway": "stripe",
    "payment_method": "credit_card",
    "gateway_transaction_id": "pi_1234567890abcdef"
  },
  "client_secret": "pi_1234567890abcdef_secret_xyz"
}
```

### Como Usar no Frontend

1. Use o Stripe.js para coletar os dados do cartão de forma segura
2. Crie um Payment Method no frontend
3. Envie o `payment_method_id` para o backend
4. O backend processa o pagamento e retorna o status

## 📊 Consultar Transações

### Listar Transações

```bash
GET /api/payments/transactions?bot_id=1&status=approved
Authorization: Bearer {token}
```

**Query Parameters:**
- `bot_id` - Filtrar por bot
- `contact_id` - Filtrar por contato
- `payment_plan_id` - Filtrar por plano
- `status` - Filtrar por status (pending, processing, approved, rejected, cancelled, refunded)
- `payment_method` - Filtrar por método (pix, credit_card)
- `limit` - Limite de resultados (1-100)
- `offset` - Offset para paginação

### Buscar Transação por ID

```bash
GET /api/payments/transactions/:id
Authorization: Bearer {token}
```

### Estatísticas de Pagamentos

```bash
GET /api/payments/stats?botId=1
Authorization: Bearer {token}
```

**Response:**
```json
{
  "stats": {
    "total_transactions": 100,
    "approved_transactions": 85,
    "total_revenue": "2547.50",
    "pix_transactions": 60,
    "credit_card_transactions": 40
  }
}
```

## 🔔 Webhooks

### Configuração

Os webhooks são configurados automaticamente e atualizam o status das transações quando há mudanças nos gateways.

#### Mercado Pago Webhook

**URL:** `POST /api/payments/webhook/mercadopago`

O Mercado Pago envia notificações quando:
- Pagamento aprovado
- Pagamento rejeitado
- Pagamento cancelado
- Status alterado

#### Stripe Webhook

**URL:** `POST /api/payments/webhook/stripe`

O Stripe envia eventos quando:
- `payment_intent.succeeded` - Pagamento aprovado
- `payment_intent.payment_failed` - Pagamento falhou

### Testar Webhooks Localmente

Use o Stripe CLI para testar webhooks localmente:

```bash
stripe listen --forward-to localhost:5000/api/payments/webhook/stripe
```

## 📝 Status das Transações

| Status | Descrição |
|--------|-----------|
| `pending` | Aguardando pagamento |
| `processing` | Processando pagamento |
| `approved` | Pagamento aprovado |
| `rejected` | Pagamento rejeitado |
| `cancelled` | Pagamento cancelado |
| `refunded` | Pagamento estornado |

## 🔒 Segurança

### Boas Práticas

1. **Nunca armazene dados de cartão no servidor**
   - Use Stripe.js no frontend para coletar dados do cartão
   - Envie apenas o `payment_method_id` para o backend

2. **Valide webhooks**
   - Stripe: Validação automática via assinatura
   - Mercado Pago: Valide a origem das requisições

3. **Use HTTPS em produção**
   - Webhooks devem ser recebidos via HTTPS
   - Dados sensíveis devem ser transmitidos criptografados

4. **Mantenha credenciais seguras**
   - Use variáveis de ambiente
   - Não commite credenciais no código
   - Use diferentes credenciais para teste e produção

## 🧪 Teste

### Cartões de Teste (Stripe)

- **Sucesso:** `4242 4242 4242 4242`
- **Falha:** `4000 0000 0000 0002`
- **3D Secure:** `4000 0025 0000 3155`
- **CVC:** Qualquer 3 dígitos
- **Data:** Qualquer data futura

### PIX de Teste (Mercado Pago)

Use as credenciais de teste do Mercado Pago. Os pagamentos PIX em modo teste são simulados.

## 📚 Referências

- [Mercado Pago API](https://www.mercadopago.com.br/developers/pt/docs)
- [Stripe API](https://stripe.com/docs/api)
- [Stripe.js](https://stripe.com/docs/js)

