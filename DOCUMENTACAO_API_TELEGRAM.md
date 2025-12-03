# Documentação Técnica - Integração com API do Telegram

## Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura da Integração](#arquitetura-da-integração)
3. [Endpoints da API do Telegram Utilizados](#endpoints-da-api-do-telegram-utilizados)
4. [Fluxo de Comunicação](#fluxo-de-comunicação)
5. [Componentes Principais](#componentes-principais)
6. [Métodos de Recebimento de Atualizações](#métodos-de-recebimento-de-atualizações)
7. [Processamento de Mensagens](#processamento-de-mensagens)
8. [Gerenciamento de Comandos](#gerenciamento-de-comandos)
9. [Gerenciamento de Grupos e Canais](#gerenciamento-de-grupos-e-canais)
10. [Webhooks](#webhooks)
11. [Tratamento de Erros e Retry](#tratamento-de-erros-e-retry)
12. [Segurança](#segurança)
13. [Exemplos de Uso](#exemplos-de-uso)

---

## Visão Geral

Esta aplicação é uma plataforma de gerenciamento de bots do Telegram que permite criar, configurar e gerenciar múltiplos bots através de uma interface web. A integração com a API do Telegram é realizada através de requisições HTTP para os endpoints oficiais da API do Telegram Bot.

### Tecnologias Utilizadas

- **Backend**: Laravel (PHP)
- **Frontend**: React.js
- **API do Telegram**: REST API oficial (`https://api.telegram.org/bot{token}/`)

---

## Arquitetura da Integração

A aplicação segue uma arquitetura em camadas para interagir com a API do Telegram:

```
┌─────────────────┐
│   Frontend       │
│   (React.js)    │
└────────┬─────────┘
         │
         ▼
┌─────────────────┐
│   Backend API   │
│   (Laravel)     │
└────────┬─────────┘
         │
         ▼
┌─────────────────┐         ┌──────────────────┐
│ TelegramService │ ◄─────► │  API Telegram   │
│   (Camada de    │         │  (REST API)     │
│   Abstração)    │         └──────────────────┘
└────────┬─────────┘
         │
         ▼
┌─────────────────┐
│   Banco de      │
│   Dados (MySQL) │
└─────────────────┘
```

### Princípios SOLID Aplicados

- **Single Responsibility**: O `TelegramService` é responsável exclusivamente pela comunicação com a API do Telegram
- **Dependency Inversion**: Os controllers dependem de abstrações (TelegramService) e não de implementações concretas
- **Open/Closed**: A estrutura permite extensão através de novos métodos no TelegramService sem modificar código existente

---

## Endpoints da API do Telegram Utilizados

A aplicação utiliza os seguintes endpoints da API oficial do Telegram:

### Autenticação e Validação

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/getMe` | Valida token e obtém informações do bot |
| `GET` | `/getWebhookInfo` | Obtém informações sobre webhook configurado |

### Webhooks

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/setWebhook` | Configura webhook para receber atualizações |
| `POST` | `/deleteWebhook` | Remove webhook configurado |

### Recebimento de Atualizações

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/getUpdates` | Obtém atualizações via polling (desenvolvimento) |

### Envio de Mensagens

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/sendMessage` | Envia mensagem de texto |
| `POST` | `/sendDocument` | Envia documento/arquivo |
| `POST` | `/sendPhoto` | Envia foto |
| `POST` | `/sendVideo` | Envia vídeo |

### Gerenciamento de Comandos

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/setMyCommands` | Registra comandos do bot |
| `GET` | `/getMyCommands` | Lista comandos registrados |
| `POST` | `/deleteMyCommands` | Remove todos os comandos |

### Gerenciamento de Grupos

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/getChat` | Obtém informações de um chat |
| `GET` | `/getChatMember` | Obtém informações de um membro |
| `GET` | `/getChatAdministrators` | Lista administradores do chat |
| `GET` | `/getChatMemberCount` | Obtém número de membros |
| `POST` | `/createChatInviteLink` | Cria link de convite |
| `POST` | `/exportChatInviteLink` | Exporta link de convite existente |
| `POST` | `/getChatMember` | Verifica status de membro |

### Gerenciamento de Membros

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/banChatMember` | Remove membro do grupo |
| `POST` | `/unbanChatMember` | Remove banimento de membro |

### Callback Queries

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/answerCallbackQuery` | Responde callback query de botões inline |

---

## Fluxo de Comunicação

### 1. Criação e Validação de Bot

```
Usuário → Frontend → Backend API → TelegramService → API Telegram (/getMe)
                                                              ↓
                                                         Valida Token
                                                              ↓
                                                         Retorna Info do Bot
                                                              ↓
Backend ← TelegramService ← Resposta JSON ←──────────────────┘
   ↓
Salva Bot no BD
   ↓
Frontend ← Resposta JSON ← Backend
```

### 2. Recebimento de Atualizações (Webhook)

```
Telegram → Webhook Endpoint (/api/telegram/webhook/{botId})
                ↓
        TelegramWebhookController
                ↓
        Valida Origem e Bot
                ↓
        ProcessTelegramUpdate Job (Queue)
                ↓
        TelegramService.processUpdate()
                ↓
        Processa Mensagem/Comando/Callback
                ↓
        Salva no Banco de Dados
                ↓
        Envia Resposta (se necessário)
```

### 3. Envio de Mensagem

```
Usuário → Frontend → Backend API → TelegramService
                                        ↓
                            sendMessage() / sendDocument()
                                        ↓
                            POST /sendMessage ou /sendDocument
                                        ↓
                            API Telegram
                                        ↓
                            Resposta (ok: true/false)
                                        ↓
                            TelegramService ← Resposta
                                        ↓
                            Backend ← Resultado
                                        ↓
                            Frontend ← JSON Response
```

---

## Componentes Principais

### 1. TelegramService (`app/Services/TelegramService.php`)

Classe central responsável por toda comunicação com a API do Telegram.

#### Métodos Principais

**Validação e Inicialização:**
- `validateToken(string $token): array` - Valida token do bot
- `initializeBot(Bot $bot): array` - Inicializa bot e registra comandos
- `stopBot(Bot $bot): array` - Para bot e remove webhook
- `getBotStatus(Bot $bot): array` - Obtém status atual do bot

**Processamento de Atualizações:**
- `processUpdate(Bot $bot, array $update): void` - Processa atualização recebida
- `processMessage(Bot $bot, array $message, bool $isEdited): void` - Processa mensagem
- `processCommand(Bot $bot, int $chatId, array $from, string $command): void` - Processa comando
- `processCallbackQuery(Bot $bot, array $callbackQuery): void` - Processa callback query

**Envio de Mensagens:**
- `sendMessage(Bot $bot, int $chatId, string $text, ?array $keyboard): void` - Envia mensagem de texto
- `sendDocument(Bot $bot, int $chatId, string $documentUrl, ?string $caption): void` - Envia documento
- `sendMessageWithKeyboard(Bot $bot, int $chatId, string $text, array $keyboard): void` - Envia mensagem com teclado

**Gerenciamento de Comandos:**
- `registerBotCommands(Bot $bot): bool` - Registra comandos no Telegram
- `getMyCommands(Bot $bot): array` - Lista comandos registrados
- `deleteBotCommands(Bot $bot): bool` - Remove todos os comandos
- `deleteBotCommand(Bot $bot, string $commandName): bool` - Remove comando específico

**Gerenciamento de Grupos:**
- `validateGroup(string $token, string $groupId, ?array $botInfo): array` - Valida grupo/canal
- `getChatMember(string $token, string $groupId, int $userId): array` - Obtém informações de membro
- `getChatInviteLink(string $token, string $chatId, ?int $botId): array` - Obtém link de convite
- `addUserToGroup(string $token, string $groupId, int $userId): array` - Adiciona usuário ao grupo
- `removeUserFromGroup(string $token, string $groupId, int $userId): array` - Remove usuário do grupo
- `getChatAdministrators(string $token, string $chatId): array` - Lista administradores
- `getChatMemberCount(string $token, string $chatId): array` - Obtém contagem de membros
- `syncGroupMembers(Bot $bot): array` - Sincroniza membros do grupo

#### Configuração HTTP

O serviço utiliza configurações de timeout e retry:

```php
protected function getTimeout(): int
{
    return (int) env('TELEGRAM_API_TIMEOUT', 30);
}

protected function http(): \Illuminate\Http\Client\PendingRequest
{
    return Http::timeout($this->getTimeout())
        ->retry(2, 1000); // 2 tentativas com 1 segundo de delay
}
```

### 2. TelegramWebhookController (`app/Http/Controllers/TelegramWebhookController.php`)

Controller responsável por receber e processar webhooks do Telegram.

#### Métodos

- `webhook(Request $request, string $botId): JsonResponse` - Recebe atualização via webhook
- `setWebhook(Request $request, string $botId): JsonResponse` - Configura webhook
- `deleteWebhook(string $botId): JsonResponse` - Remove webhook
- `getWebhookInfo(string $botId): JsonResponse` - Obtém informações do webhook

#### Validação de Origem

O controller valida a origem do webhook através de:
1. **Secret Token** (se configurado): Verifica header `X-Telegram-Bot-Api-Secret-Token`
2. **Estrutura da Atualização**: Valida campos esperados na atualização

### 3. BotController (`app/Http/Controllers/BotController.php`)

Controller para gerenciamento de bots.

#### Endpoints Principais

- `POST /api/bots` - Cria novo bot (valida token automaticamente)
- `GET /api/bots/{id}` - Obtém informações do bot
- `PUT /api/bots/{id}` - Atualiza bot
- `DELETE /api/bots/{id}` - Remove bot
- `POST /api/bots/{id}/initialize` - Inicializa bot
- `POST /api/bots/{id}/stop` - Para bot
- `GET /api/bots/{id}/status` - Obtém status do bot
- `POST /api/bots/validate` - Valida token sem criar bot

### 4. BotCommandController (`app/Http/Controllers/BotCommandController.php`)

Controller para gerenciamento de comandos do bot.

#### Endpoints

- `GET /api/bots/{botId}/commands` - Lista comandos
- `POST /api/bots/{botId}/commands` - Cria comando
- `PUT /api/bots/{botId}/commands/{commandId}` - Atualiza comando
- `DELETE /api/bots/{botId}/commands/{commandId}` - Remove comando
- `POST /api/bots/{botId}/commands/register` - Registra comandos no Telegram
- `GET /api/bots/{botId}/commands/telegram` - Lista comandos registrados no Telegram
- `DELETE /api/bots/{botId}/commands/telegram` - Remove todos os comandos do Telegram
- `DELETE /api/bots/{botId}/commands/telegram/command` - Remove comando específico do Telegram

### 5. TelegramGroupController (`app/Http/Controllers/TelegramGroupController.php`)

Controller para gerenciamento de grupos e canais do Telegram.

#### Endpoints

- `GET /api/telegram-groups?bot_id={id}` - Lista grupos/canais
- `POST /api/telegram-groups` - Adiciona grupo/canal
- `GET /api/telegram-groups/{id}` - Obtém informações do grupo
- `PUT /api/telegram-groups/{id}` - Atualiza grupo
- `DELETE /api/telegram-groups/{id}` - Remove grupo

### 6. ProcessTelegramUpdate (`app/Jobs/ProcessTelegramUpdate.php`)

Job assíncrono para processar atualizações do Telegram em background.

**Queue**: `telegram-updates`

---

## Métodos de Recebimento de Atualizações

A aplicação suporta dois métodos para receber atualizações do Telegram:

### 1. Webhook (Produção - Recomendado)

O webhook é o método recomendado para produção. O Telegram envia atualizações diretamente para um endpoint da aplicação.

#### Configuração

```http
POST /api/telegram/webhook/{botId}/set
Content-Type: application/json

{
  "url": "https://seudominio.com/api/telegram/webhook/{botId}",
  "secret_token": "seu-token-secreto-opcional",
  "allowed_updates": ["message", "edited_message", "callback_query"],
  "drop_pending_updates": false
}
```

#### Requisitos

- URL deve usar HTTPS
- Portas permitidas: 443, 80, 88, 8443
- Certificado SSL válido
- Endpoint deve responder em até 60 segundos

#### Rotas

- **Webhook público**: `POST /api/telegram/webhook/{botId}` (sem autenticação)
- **Configurar webhook**: `POST /api/telegram/webhook/{botId}/set` (requer autenticação)
- **Remover webhook**: `POST /api/telegram/webhook/{botId}/delete` (requer autenticação)
- **Informações do webhook**: `GET /api/telegram/webhook/{botId}/info` (requer autenticação)

### 2. Polling (Desenvolvimento)

O polling utiliza o método `getUpdates` para buscar atualizações periodicamente.

#### Comando Artisan

```bash
php artisan telegram:polling --bot-id={id} --timeout=30 --limit=100
```

#### Parâmetros

- `--bot-id`: ID específico do bot (opcional, se não informado, faz polling de todos os bots ativos)
- `--timeout`: Timeout em segundos para getUpdates (padrão: 30)
- `--limit`: Limite de atualizações por requisição (padrão: 100)

#### Uso

Ideal para desenvolvimento e testes locais onde não é possível configurar webhook HTTPS.

---

## Processamento de Mensagens

### Tipos de Atualizações Suportadas

A aplicação processa os seguintes tipos de atualizações:

1. **Mensagens** (`message`)
   - Mensagens de texto
   - Mensagens com mídia (foto, vídeo, documento)
   - Contatos compartilhados
   - Mensagens editadas (`edited_message`)

2. **Comandos** (`message` com `entities`)
   - Comandos iniciados com `/`
   - Comandos padrão: `/start`, `/help`, `/planos`
   - Comandos personalizados configurados pelo usuário

3. **Callback Queries** (`callback_query`)
   - Respostas de botões inline
   - Seleção de planos de pagamento
   - Seleção de método de pagamento

4. **Mensagens de Grupo** (`message` em grupos)
   - Mensagens em grupos/canais
   - Verificação de membros
   - Sincronização de membros

5. **Channel Posts** (`channel_post`)
   - Posts em canais

### Fluxo de Processamento

```
processUpdate()
    ↓
Identifica tipo de atualização
    ↓
┌─────────────────────────────────────┐
│                                     │
├─ message → processMessage()         │
│   ├─ É comando? → processCommand()  │
│   ├─ É grupo? → processGroupMessage()│
│   └─ É texto? → processTextMessage()│
│                                     │
├─ callback_query → processCallbackQuery()│
│                                     │
├─ channel_post → processChannelPost()│
│                                     │
└─ edited_message → processMessage(edited=true)│
```

### Comandos Padrão

#### `/start`

Inicia conversa com o bot:
1. Salva/atualiza contato no banco de dados
2. Envia mensagem inicial configurada (`initial_message`)
3. Envia mídias configuradas (`media_1_url`, `media_2_url`, `media_3_url`)
4. Exibe botões de ação (`activate_cta`)

#### `/help`

Exibe ajuda e lista comandos disponíveis.

#### `/planos`

Lista planos de pagamento disponíveis e permite seleção.

### Processamento de Texto

Quando o bot recebe uma mensagem de texto que não é comando:

1. Verifica se é resposta a uma pergunta pendente (email, telefone, idioma)
2. Salva informações do contato
3. Envia resposta apropriada ou continua fluxo de conversa

### Processamento de Callback Query

Callbacks são usados para:
- Seleção de planos de pagamento
- Seleção de método de pagamento (cartão/PIX)
- Navegação em menus

Fluxo:
```
Callback Query recebido
    ↓
Identifica tipo (plan_selection, payment_method, etc.)
    ↓
Processa ação correspondente
    ↓
Responde callback (answerCallbackQuery)
    ↓
Envia próxima mensagem ou atualiza interface
```

---

## Gerenciamento de Comandos

### Estrutura de Comando

```php
BotCommand {
    id: int
    bot_id: int
    command: string        // Nome do comando (sem /)
    response: string       // Resposta do comando
    description: string    // Descrição (aparece na lista de comandos)
    active: boolean        // Se está ativo
}
```

### Registro de Comandos no Telegram

Os comandos são registrados no Telegram usando o formato:

```json
[
  {
    "command": "start",
    "description": "Iniciar conversa com o bot"
  },
  {
    "command": "help",
    "description": "Ver ajuda e comandos disponíveis"
  }
]
```

### Endpoint da API

```http
POST https://api.telegram.org/bot{token}/setMyCommands
Content-Type: application/json

{
  "commands": [
    {"command": "start", "description": "Iniciar"},
    {"command": "help", "description": "Ajuda"}
  ]
}
```

### Sincronização Automática

Os comandos são sincronizados automaticamente quando:
- Bot é inicializado
- Comando é criado/atualizado/removido
- Usuário solicita registro manual via API

---

## Gerenciamento de Grupos e Canais

### Funcionalidades

1. **Cadastro de Grupos/Canais**
   - Associação com bot
   - Vinculação com plano de pagamento
   - Obtenção automática de link de convite

2. **Gerenciamento de Membros**
   - Adicionar membro ao grupo
   - Remover membro do grupo
   - Verificar status de membro
   - Sincronizar lista de membros

3. **Informações do Grupo**
   - Listar administradores
   - Obter contagem de membros
   - Obter informações do chat

### Normalização de Chat ID

O Telegram utiliza diferentes formatos de ID:
- Grupos: `-1001234567890` (negativo)
- Canais: `-1001234567890` (negativo)
- Usuários: `123456789` (positivo)

A aplicação normaliza esses IDs para garantir consistência:

```php
protected function normalizeChatId(string $chatId): string
{
    // Remove @ se presente
    $chatId = str_replace('@', '', $chatId);
    
    // Se não começa com -, adiciona
    if (!str_starts_with($chatId, '-')) {
        $chatId = '-' . $chatId;
    }
    
    return $chatId;
}
```

### Adicionar Membro ao Grupo

```php
$result = $telegramService->addUserToGroup(
    $bot->token,
    $groupId,
    $userId
);
```

**Endpoint utilizado**: `POST /banChatMember` (com `until_date: 0` para remover banimento) ou adição direta via link de convite.

### Remover Membro do Grupo

```php
$result = $telegramService->removeUserFromGroup(
    $bot->token,
    $groupId,
    $userId
);
```

**Endpoint utilizado**: `POST /banChatMember` com `until_date` definido.

---

## Webhooks

### Configuração de Webhook

#### Via API

```http
POST /api/telegram/webhook/{botId}/set
Authorization: Bearer {token}
Content-Type: application/json

{
  "url": "https://seudominio.com/api/telegram/webhook/{botId}",
  "secret_token": "token-secreto-opcional",
  "allowed_updates": [
    "message",
    "edited_message",
    "channel_post",
    "edited_channel_post",
    "callback_query"
  ],
  "drop_pending_updates": false
}
```

#### Validações

1. **URL HTTPS**: Obrigatório
2. **Porta**: Apenas 443, 80, 88, 8443
3. **Certificado SSL**: Válido e confiável
4. **Timeout**: Resposta em até 60 segundos

### Estrutura de Webhook Recebido

```json
{
  "update_id": 123456789,
  "message": {
    "message_id": 1,
    "from": {
      "id": 123456789,
      "is_bot": false,
      "first_name": "João",
      "username": "joao_silva"
    },
    "chat": {
      "id": 123456789,
      "type": "private"
    },
    "date": 1234567890,
    "text": "/start"
  }
}
```

### Processamento Assíncrono

Por padrão, o webhook processa atualizações de forma síncrona para garantir resposta rápida ao Telegram. Em caso de erro, tenta processar de forma assíncrona:

```php
try {
    $telegramService->processUpdate($bot, $update);
} catch (\Exception $e) {
    ProcessTelegramUpdate::dispatch($bot, $update);
}
```

### Secret Token

Para maior segurança, pode-se configurar um secret token:

1. Configure no `.env`:
```env
TELEGRAM_WEBHOOK_SECRET_TOKEN=seu-token-secreto-aqui
```

2. Configure no webhook:
```http
POST /api/telegram/webhook/{botId}/set
{
  "secret_token": "seu-token-secreto-aqui"
}
```

3. O Telegram enviará o token no header:
```
X-Telegram-Bot-Api-Secret-Token: seu-token-secreto-aqui
```

---

## Tratamento de Erros e Retry

### Estratégia de Retry

A aplicação implementa retry automático para requisições à API do Telegram:

```php
protected function http(): \Illuminate\Http\Client\PendingRequest
{
    return Http::timeout($this->getTimeout())
        ->retry(2, 1000); // 2 tentativas com 1 segundo de delay
}
```

### Validação de Token com Retry

A validação de token possui retry específico:

```php
$maxRetries = 3;
$retryDelay = 2; // segundos

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        $response = $this->http()->get("https://api.telegram.org/bot{$token}/getMe");
        // Processa resposta
    } catch (ConnectionException $e) {
        if ($attempt < $maxRetries) {
            sleep($retryDelay);
            continue;
        }
        // Retorna erro após todas as tentativas
    }
}
```

### Logging de Erros

Todos os erros são registrados no sistema de logs do Laravel:

```php
Log::error('Erro ao processar atualização do Telegram', [
    'bot_id' => $bot->id,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString()
]);
```

### Timeout Configurável

O timeout pode ser configurado via variável de ambiente:

```env
TELEGRAM_API_TIMEOUT=30
```

Padrão: 30 segundos

---

## Segurança

### Validação de Token

Todos os tokens são validados antes de serem salvos:

```php
$validation = $telegramService->validateToken($token);
if (!$validation['valid']) {
    return response()->json(['error' => 'Token inválido'], 400);
}
```

### Validação de Webhook

O webhook valida:
1. Existência do bot
2. Bot está ativo e ativado
3. Origem da requisição (secret token ou estrutura válida)

### Permissões

A aplicação utiliza sistema de permissões baseado em:
- **Super Admin**: Acesso total
- **Admin**: Acesso a bots específicos
- **Usuário**: Acesso limitado conforme grupos de usuário

### Proteção de Rotas

Rotas protegidas utilizam middleware:
- `auth:api` - Autenticação via token JWT
- `super_admin` - Apenas super administradores
- `CheckPermission` - Verifica permissões específicas

### Armazenamento de Tokens

Os tokens são armazenados criptografados no banco de dados (recomendado usar `encrypted` cast no Laravel).

---

## Exemplos de Uso

### 1. Criar e Ativar Bot

```http
POST /api/bots
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Meu Bot",
  "token": "123456789:ABCdefGHIjklMNOpqrsTUVwxyz",
  "active": true,
  "initial_message": "Olá! Bem-vindo ao bot.",
  "request_email": true,
  "request_phone": true
}
```

Resposta:
```json
{
  "bot": {
    "id": 1,
    "name": "Meu Bot",
    "token": "***",
    "active": true,
    "activated": false
  },
  "bot_info": {
    "id": 123456789,
    "is_bot": true,
    "first_name": "Meu Bot",
    "username": "meu_bot"
  },
  "token_valid": true
}
```

Ativar bot:
```http
POST /api/bots/1/validate-and-activate
Authorization: Bearer {token}
```

### 2. Configurar Webhook

```http
POST /api/telegram/webhook/1/set
Authorization: Bearer {token}
Content-Type: application/json

{
  "url": "https://seudominio.com/api/telegram/webhook/1",
  "secret_token": "meu-token-secreto",
  "allowed_updates": ["message", "callback_query"]
}
```

Resposta:
```json
{
  "success": true,
  "message": "Webhook configurado com sucesso",
  "webhook_url": "https://seudominio.com/api/telegram/webhook/1",
  "webhook_info": {
    "url": "https://seudominio.com/api/telegram/webhook/1",
    "pending_update_count": 0,
    "last_error_date": null,
    "last_error_message": null
  }
}
```

### 3. Criar Comando

```http
POST /api/bots/1/commands
Authorization: Bearer {token}
Content-Type: application/json

{
  "command": "info",
  "response": "Informações sobre o bot...",
  "description": "Obter informações do bot",
  "active": true
}
```

Registrar no Telegram:
```http
POST /api/bots/1/commands/register
Authorization: Bearer {token}
```

### 4. Adicionar Grupo

```http
POST /api/telegram-groups
Authorization: Bearer {token}
Content-Type: application/json

{
  "bot_id": 1,
  "title": "Grupo VIP",
  "telegram_group_id": "-1001234567890",
  "type": "group",
  "payment_plan_id": 1,
  "active": true
}
```

### 5. Adicionar Membro ao Grupo

```http
POST /api/bots/1/group/add-member
Authorization: Bearer {token}
Content-Type: application/json

{
  "group_id": "-1001234567890",
  "user_id": 987654321
}
```

### 6. Obter Status do Bot

```http
GET /api/bots/1/status
Authorization: Bearer {token}
```

Resposta:
```json
{
  "bot": {
    "id": 1,
    "name": "Meu Bot",
    "active": true,
    "activated": true
  },
  "webhook": {
    "url": "https://seudominio.com/api/telegram/webhook/1",
    "pending_updates": 0
  },
  "bot_info": {
    "id": 123456789,
    "username": "meu_bot"
  }
}
```

---

## Limitações e Considerações

### Rate Limits da API do Telegram

A API do Telegram possui limites de taxa:
- **Mensagens**: Máximo 30 mensagens por segundo para diferentes chats
- **Grupos**: Máximo 20 mensagens por minuto no mesmo grupo
- **getUpdates**: Máximo 1 requisição por segundo

A aplicação implementa retry e timeout, mas não controla rate limiting automaticamente. Em produção, considere implementar throttling.

### Tamanho de Mensagens

- **Texto**: Máximo 4096 caracteres
- **Legenda de mídia**: Máximo 1024 caracteres
- **Arquivos**: Máximo 50MB (20MB via URL)

### Timeout

- **Webhook**: Deve responder em até 60 segundos
- **getUpdates**: Timeout configurável (padrão: 30 segundos)

### Certificado SSL

Webhooks requerem certificado SSL válido. Em desenvolvimento, use ferramentas como ngrok ou configure certificado local válido.

---

## Variáveis de Ambiente

### Configurações Relacionadas ao Telegram

```env
# Timeout para requisições à API do Telegram (segundos)
TELEGRAM_API_TIMEOUT=30

# URL base para webhooks (opcional, usa APP_URL se não definido)
TELEGRAM_WEBHOOK_URL=https://seudominio.com

# Token secreto para validação de webhooks (opcional)
TELEGRAM_WEBHOOK_SECRET_TOKEN=seu-token-secreto-aqui
```

---

## Troubleshooting

### Bot não recebe mensagens

1. Verifique se o bot está ativo e ativado
2. Verifique configuração do webhook: `GET /api/telegram/webhook/{botId}/info`
3. Verifique logs do Laravel para erros
4. Teste token diretamente: `GET https://api.telegram.org/bot{token}/getMe`

### Erro ao configurar webhook

1. Verifique se URL usa HTTPS
2. Verifique se porta é permitida (443, 80, 88, 8443)
3. Verifique se certificado SSL é válido
4. Verifique se endpoint está acessível publicamente

### Comandos não aparecem no Telegram

1. Verifique se comandos foram registrados: `GET /api/bots/{botId}/commands/telegram`
2. Registre comandos manualmente: `POST /api/bots/{botId}/commands/register`
3. Verifique se comandos estão ativos no banco de dados

### Erro de timeout

1. Aumente `TELEGRAM_API_TIMEOUT` no `.env`
2. Verifique conexão com internet
3. Verifique se API do Telegram está acessível

---

## Referências

- [Documentação Oficial da API do Telegram Bot](https://core.telegram.org/bots/api)
- [Laravel HTTP Client](https://laravel.com/docs/http-client)
- [Telegram Bot API Updates](https://core.telegram.org/bots/api#getting-updates)

---

## Changelog

### Versão 1.0
- Implementação inicial da integração com API do Telegram
- Suporte a webhooks e polling
- Gerenciamento de comandos
- Gerenciamento de grupos e canais
- Processamento de mensagens e callbacks

---

**Última atualização**: 2024
