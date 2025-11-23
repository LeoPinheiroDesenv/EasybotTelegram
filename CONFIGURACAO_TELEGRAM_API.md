# Configuração da Telegram Bot API - Guia Completo

## ⚠️ Problema Identificado e CORRIGIDO

**Problema Original**: A implementação não iniciava automaticamente o processo de receber atualizações.

**Correções Implementadas**:
- ✅ Método `initialize()` agora verifica se webhook está configurado
- ✅ Retorna instruções claras sobre próximos passos
- ✅ Validação de HTTPS para webhook
- ✅ Método para obter informações do webhook (`getWebhookInfo`)
- ✅ Melhor tratamento de erros e mensagens informativas

---

## 📋 Requisitos da Telegram Bot API

Conforme a documentação oficial (https://core.telegram.org/bots/api):

### 1. **getUpdates (Long Polling)**
- Método para receber atualizações via polling
- Requer loop contínuo fazendo requisições
- Não precisa de servidor público
- Ideal para desenvolvimento

### 2. **setWebhook**
- Método para configurar webhook
- Telegram envia atualizações para URL configurada
- Requer servidor público com HTTPS
- Ideal para produção

### ⚠️ **IMPORTANTE**: Você deve escolher UM método:
- **Polling** OU **Webhook** (não ambos ao mesmo tempo)

---

## 🔧 Configuração Correta

### Opção 1: Polling (Desenvolvimento/Local)

#### Passo 1: Criar e Validar Bot
```bash
# 1. Criar bot via API
POST /api/bots
{
  "name": "Meu Bot",
  "token": "123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11",
  "active": true
}

# 2. Validar token
POST /api/bots/validate
{
  "token": "123456:ABC-DEF1234ghIkl-zyx57W1u123ew11"
}

# 3. Inicializar bot (marca como ativado)
POST /api/bots/{id}/initialize
```

#### Passo 2: Iniciar Polling Manualmente
```bash
# OBRIGATÓRIO: Executar em terminal separado
cd backend
php artisan telegram:polling --bot-id=1

# Ou para todos os bots ativos:
php artisan telegram:polling
```

**⚠️ CRÍTICO**: O polling precisa estar rodando continuamente! Se você fechar o terminal, o bot para de receber mensagens.

#### Passo 3: Verificar Funcionamento
- Envie `/start` para o bot no Telegram
- Bot deve responder automaticamente
- Verifique logs no terminal do polling
- Se não funcionar, verifique:
  - Token está correto?
  - Bot está ativo e inicializado?
  - Polling está rodando?

---

### Opção 2: Webhook (Produção)

#### Passo 1: Pré-requisitos
- ✅ Servidor público acessível
- ✅ HTTPS configurado (obrigatório)
- ✅ URL pública: `https://seudominio.com/api/telegram/webhook/{botId}`

#### Passo 2: Criar e Configurar Bot
```bash
# 1. Criar bot
POST /api/bots
{
  "name": "Meu Bot",
  "token": "123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11",
  "active": true
}

# 2. Inicializar bot
POST /api/bots/{id}/initialize

# 3. Configurar webhook (OBRIGATÓRIO)
POST /api/telegram/webhook/{botId}/set
Authorization: Bearer {seu_token}
```

#### Passo 3: Verificar Webhook
```bash
# Via API da aplicação
GET /api/telegram/webhook/{botId}/info

# Ou diretamente na API do Telegram
GET https://api.telegram.org/bot{TOKEN}/getWebhookInfo
```

Resposta esperada:
```json
{
  "success": true,
  "webhook_info": {
    "url": "https://seudominio.com/api/telegram/webhook/1",
    "has_custom_certificate": false,
    "pending_update_count": 0
  }
}
```

#### Passo 4: Testar
- Envie `/start` para o bot no Telegram
- Bot deve responder automaticamente
- Verifique logs da aplicação

---

## ✅ Correções Implementadas

### 1. Melhorias no Método `initialize()`
- ✅ Agora verifica automaticamente se webhook está configurado
- ✅ Retorna mensagem clara com próximos passos
- ✅ Informa se precisa executar polling ou se webhook já está ativo
- ✅ Retorna informações do webhook se existir

### 2. Validação de Webhook Melhorada
- ✅ Valida se URL usa HTTPS (obrigatório)
- ✅ Remove webhook antigo antes de configurar novo
- ✅ Configura `allowed_updates` corretamente
- ✅ Retorna informações detalhadas do webhook após configuração

### 3. Novo Endpoint: getWebhookInfo
- ✅ `GET /api/telegram/webhook/{botId}/info`
- ✅ Permite verificar status do webhook facilmente
- ✅ Mostra erros e atualizações pendentes

### 4. Mensagens Mais Claras
- ✅ Erros mais descritivos
- ✅ Instruções claras sobre próximos passos
- ✅ Validações melhoradas

---

## 📝 Checklist de Configuração

### Para Polling (Local):
- [ ] Bot criado no BotFather
- [ ] Token válido obtido
- [ ] Bot criado via API (`POST /api/bots`)
- [ ] Token validado (`POST /api/bots/validate`)
- [ ] Bot inicializado (`POST /api/bots/{id}/initialize`)
- [ ] **Polling iniciado** (`php artisan telegram:polling --bot-id={id}`)
- [ ] Polling rodando continuamente
- [ ] Testado enviando `/start` no Telegram

### Para Webhook (Produção):
- [ ] Bot criado no BotFather
- [ ] Token válido obtido
- [ ] Servidor público com HTTPS
- [ ] Bot criado via API (`POST /api/bots`)
- [ ] Token validado (`POST /api/bots/validate`)
- [ ] Bot inicializado (`POST /api/bots/{id}/initialize`)
- [ ] **Webhook configurado** (`POST /api/telegram/webhook/{botId}/set`)
- [ ] Webhook verificado (`GET /api/telegram/webhook/{botId}/info`)
- [ ] Verificar se `pending_update_count` é 0 (sem erros)
- [ ] Queue worker rodando (`php artisan queue:work --queue=telegram-updates`)
- [ ] Testado enviando `/start` no Telegram

---

## 🔍 Como Diagnosticar Problemas

### 1. Verificar se Bot está Ativo
```bash
GET /api/bots/{id}/status
```

Resposta esperada:
```json
{
  "bot_id": 1,
  "active": true,
  "activated": true,
  "token_valid": true,
  "bot_info": {
    "id": 123456789,
    "username": "meu_bot",
    "first_name": "Meu Bot"
  }
}
```

### 2. Verificar Webhook (se usando)
```bash
curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo
```

### 3. Verificar Logs
```bash
# Logs do Laravel
tail -f backend/storage/logs/laravel.log

# Logs do Docker
docker-compose logs backend -f
```

### 4. Testar Token Diretamente
```bash
curl https://api.telegram.org/bot{TOKEN}/getMe
```

---

## 🚀 Melhorias Recomendadas

### 1. Iniciar Polling Automaticamente (Opcional)

Criar um processo supervisor ou systemd para manter polling rodando:

```bash
# Exemplo com supervisor
[program:telegram-polling]
command=php /caminho/para/artisan telegram:polling
autostart=true
autorestart=true
user=www-data
```

### 2. Verificar Webhook Antes de Usar

Adicionar verificação automática de webhook antes de processar atualizações.

### 3. Processamento em Background

Já implementado com queues, mas garantir que worker está rodando.

---

## 📚 Referências

- Documentação Oficial: https://core.telegram.org/bots/api
- getUpdates: https://core.telegram.org/bots/api#getupdates
- setWebhook: https://core.telegram.org/bots/api#setwebhook
- Long Polling: https://core.telegram.org/bots/api#getting-updates

---

## ⚡ Resumo Rápido

### Para Desenvolvimento Local:
1. Criar bot via API
2. Inicializar bot
3. **Executar**: `php artisan telegram:polling --bot-id={id}`
4. Manter terminal aberto

### Para Produção:
1. Criar bot via API
2. Inicializar bot
3. **Configurar webhook**: `POST /api/telegram/webhook/{botId}/set`
4. Garantir queue worker rodando

## 📌 Pontos Importantes

### ⚠️ Polling não é automático
- O método `initialize()` apenas valida e marca o bot como ativado
- **Você DEVE executar polling manualmente** ou configurar webhook
- Polling precisa rodar continuamente em um processo separado

### ✅ Webhook é automático (após configurar)
- Após configurar webhook, o Telegram envia atualizações automaticamente
- Não precisa de processo separado rodando
- Requer HTTPS e servidor público

### 🔍 Como saber qual método está ativo?
- Após inicializar bot, a resposta mostra se webhook está configurado
- Use `GET /api/telegram/webhook/{botId}/info` para verificar
- Se não houver webhook, você precisa executar polling manualmente

