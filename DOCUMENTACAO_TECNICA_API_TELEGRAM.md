# Documentação Técnica - Integração com API do Telegram

## Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura da Aplicação](#arquitetura-da-aplicação)
3. [Configuração Inicial](#configuração-inicial)
4. [Modelos de Dados](#modelos-de-dados)
5. [API do Telegram - Métodos Utilizados](#api-do-telegram---métodos-utilizados)
6. [Endpoints da Aplicação](#endpoints-da-aplicação)
7. [Webhooks e Polling](#webhooks-e-polling)
8. [Processamento de Mensagens](#processamento-de-mensagens)
9. [Gerenciamento de Grupos](#gerenciamento-de-grupos)
10. [Sistema de Comandos](#sistema-de-comandos)
11. [Sistema de Pagamentos](#sistema-de-pagamentos)
12. [Sistema de Alertas](#sistema-de-alertas)
13. [Logs e Monitoramento](#logs-e-monitoramento)
14. [Segurança e Permissões](#segurança-e-permissões)
15. [Boas Práticas](#boas-práticas)

---

## Visão Geral

Esta aplicação é um **sistema de gerenciamento de bots do Telegram** desenvolvido em Laravel (backend) e React (frontend), que permite criar e gerenciar múltiplos bots simultaneamente com funcionalidades avançadas como:

- **Gerenciamento de Bots**: Criação, configuração e monitoramento de múltiplos bots
- **Gerenciamento de Grupos**: Adicionar/remover membros, controlar acesso e estatísticas
- **Sistema de Comandos**: Comandos personalizados e respostas automatizadas
- **Sistema de Pagamentos**: Integração com MercadoPago e Stripe
- **Alertas Automatizados**: Envio programado de mensagens para usuários
- **Controle de Acesso**: Sistema de permissões granular por bot e usuário
- **Logs Detalhados**: Registro completo de todas as ações e interações

### Tecnologias Utilizadas

**Backend:**
- PHP 8.x com Laravel 11.x
- MySQL 8.0
- Queue System (Jobs assíncronos)
- Laravel HTTP Client (Guzzle)

**Frontend:**
- React 18.x
- Axios para requisições HTTP
- Context API para gerenciamento de estado

**Infraestrutura:**
- Docker e Docker Compose
- Nginx/Apache para servir aplicação

---

## Arquitetura da Aplicação

### Estrutura de Diretórios

```
/workspace/
├── backend/
│   ├── app/
│   │   ├── Console/Commands/          # Comandos Artisan
│   │   │   ├── TelegramPollingCommand.php
│   │   │   ├── ProcessScheduledAlerts.php
│   │   │   └── UpdateContactsTelegramStatus.php
│   │   ├── Http/Controllers/          # Controladores da API
│   │   │   ├── BotController.php
│   │   │   ├── TelegramWebhookController.php
│   │   │   ├── ContactController.php
│   │   │   ├── GroupManagementController.php
│   │   │   └── ...
│   │   ├── Models/                    # Modelos Eloquent
│   │   │   ├── Bot.php
│   │   │   ├── Contact.php
│   │   │   ├── BotCommand.php
│   │   │   └── ...
│   │   ├── Services/                  # Lógica de negócio
│   │   │   ├── TelegramService.php    # ⭐ Serviço principal
│   │   │   ├── PaymentService.php
│   │   │   ├── NotificationService.php
│   │   │   └── ...
│   │   └── Jobs/                      # Jobs assíncronos
│   │       ├── ProcessTelegramUpdate.php
│   │       └── SendAlert.php
│   └── routes/
│       └── api.php                    # Rotas da API
└── frontend/
    └── src/
        ├── pages/                     # Páginas React
        ├── components/                # Componentes reutilizáveis
        └── services/                  # Serviços API
```

### Fluxo de Dados

```
Telegram API → Webhook → TelegramWebhookController
                   ↓
            ProcessTelegramUpdate (Job)
                   ↓
            TelegramService.processUpdate()
                   ↓
    ┌──────────────┴──────────────┐
    ↓              ↓               ↓
Mensagens      Comandos        Callbacks
    ↓              ↓               ↓
Processamento → Resposta → API Telegram
```

---

## Configuração Inicial

### Variáveis de Ambiente

Adicione ao arquivo `.env` do backend:

```env
# Configurações da API do Telegram
TELEGRAM_API_TIMEOUT=30
TELEGRAM_WEBHOOK_URL=https://seu-dominio.com
TELEGRAM_WEBHOOK_SECRET_TOKEN=seu_token_secreto_aqui

# Configurações de Alertas
ALERTS_PROCESS_SECRET_TOKEN=seu_token_para_processar_alertas

# Banco de Dados
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=bottelegram_db
DB_USERNAME=bottelegram_user
DB_PASSWORD=bottelegram123

# JWT para autenticação
JWT_SECRET=sua_chave_secreta_jwt

# Configurações de Pagamento (opcional)
MERCADOPAGO_ACCESS_TOKEN=
STRIPE_SECRET_KEY=
STRIPE_PUBLIC_KEY=
```

### Instalação e Deploy

```bash
# 1. Clone o repositório
git clone <repository-url>
cd workspace

# 2. Construa e inicie os containers
docker-compose up -d

# 3. Instale dependências do backend
docker exec -it bottelegram_backend composer install

# 4. Execute as migrações
docker exec -it bottelegram_backend php artisan migrate

# 5. Crie um usuário admin
docker exec -it bottelegram_backend php artisan db:seed --class=CreateAdminUserSeeder

# 6. Crie link simbólico do storage (para uploads)
docker exec -it bottelegram_backend php artisan storage:link
```

---

## Modelos de Dados

### Bot

Representa um bot do Telegram configurado na aplicação.

```php
// app/Models/Bot.php
class Bot extends Model
{
    protected $fillable = [
        'user_id',              // ID do usuário proprietário
        'name',                 // Nome do bot
        'token',                // Token da API do Telegram
        'telegram_group_id',    // ID do grupo vinculado
        'active',               // Bot está ativo?
        'activated',            // Bot foi inicializado?
        'initial_message',      // Mensagem inicial
        'top_message',          // Mensagem topo
        'button_message',       // Texto do botão
        'activate_cta',         // Ativar CTA?
        'media_1_url',          // URL da mídia 1
        'media_2_url',          // URL da mídia 2
        'media_3_url',          // URL da mídia 3
        'request_email',        // Solicitar email?
        'request_phone',        // Solicitar telefone?
        'request_language',     // Solicitar idioma?
        'payment_method',       // Método de pagamento (credit_card, pix)
    ];
}
```

### Contact

Representa um usuário que interagiu com o bot.

```php
// app/Models/Contact.php
class Contact extends Model
{
    protected $fillable = [
        'bot_id',               // Bot que gerencia este contato
        'telegram_id',          // ID do usuário no Telegram
        'username',             // Username do Telegram
        'first_name',           // Primeiro nome
        'last_name',            // Sobrenome
        'email',                // Email (coletado opcionalmente)
        'phone',                // Telefone (coletado opcionalmente)
        'language',             // Idioma
        'is_bot',               // É um bot?
        'is_blocked',           // Está bloqueado?
        'telegram_status',      // Status: active, inactive, deleted, banned
        'expires_at',           // Data de expiração de acesso
        'last_interaction_at',  // Última interação
    ];
}
```

### BotCommand

Representa comandos personalizados do bot.

```php
// app/Models/BotCommand.php
class BotCommand extends Model
{
    protected $fillable = [
        'bot_id',       // ID do bot
        'command',      // Comando (ex: /help)
        'response',     // Resposta do comando
        'description',  // Descrição do comando
        'active',       // Comando está ativo?
        'usage_count',  // Contador de uso
    ];
}
```

### Outros Modelos Importantes

- **`TelegramGroup`**: Grupos do Telegram gerenciados
- **`RedirectButton`**: Botões de redirecionamento personalizados
- **`BotAdministrator`**: Administradores de bots
- **`Alert`**: Alertas programados
- **`Transaction`**: Transações de pagamento
- **`PaymentPlan`**: Planos de pagamento
- **`Log`**: Logs do sistema

---

## API do Telegram - Métodos Utilizados

A aplicação utiliza os seguintes métodos da API oficial do Telegram:

### Métodos de Autenticação e Informação

#### `getMe`
Obtém informações sobre o bot.

```php
GET https://api.telegram.org/bot{token}/getMe

// Retorna:
{
  "ok": true,
  "result": {
    "id": 123456789,
    "is_bot": true,
    "first_name": "Meu Bot",
    "username": "meubot",
    "can_join_groups": true,
    "can_read_all_group_messages": false
  }
}
```

**Uso na aplicação:**
- Validação de tokens
- Verificação de permissões do bot
- Obtenção de informações básicas

### Métodos de Webhook

#### `setWebhook`
Configura um webhook para receber atualizações.

```php
POST https://api.telegram.org/bot{token}/setWebhook

// Parâmetros:
{
  "url": "https://seu-dominio.com/api/telegram/webhook/{botId}",
  "allowed_updates": ["message", "callback_query", "inline_query", ...],
  "secret_token": "token_secreto",
  "drop_pending_updates": false
}
```

**Requisitos:**
- URL deve ser HTTPS
- Porta deve ser: 443, 80, 88 ou 8443
- Certificado SSL válido

**Uso na aplicação:**
```php
// POST /api/telegram/webhook/{botId}/set
TelegramWebhookController::setWebhook()
```

#### `getWebhookInfo`
Obtém informações sobre o webhook configurado.

```php
GET https://api.telegram.org/bot{token}/getWebhookInfo

// Retorna:
{
  "ok": true,
  "result": {
    "url": "https://seu-dominio.com/api/telegram/webhook/1",
    "has_custom_certificate": false,
    "pending_update_count": 0,
    "last_error_date": null,
    "last_error_message": null,
    "max_connections": 40,
    "allowed_updates": ["message", "callback_query"]
  }
}
```

#### `deleteWebhook`
Remove o webhook configurado.

```php
POST https://api.telegram.org/bot{token}/deleteWebhook
```

### Métodos de Mensagens

#### `sendMessage`
Envia uma mensagem de texto.

```php
POST https://api.telegram.org/bot{token}/sendMessage

// Parâmetros:
{
  "chat_id": 123456789,
  "text": "Olá! Como posso ajudar?",
  "parse_mode": "HTML",  // ou "Markdown"
  "reply_markup": {      // Opcional: teclado
    "inline_keyboard": [[
      {"text": "Botão 1", "callback_data": "btn1"},
      {"text": "Botão 2", "url": "https://example.com"}
    ]]
  }
}
```

**Uso na aplicação:**
```php
// TelegramService::sendMessage()
public function sendMessage(Bot $bot, int $chatId, string $text, ?array $keyboard = null)
```

#### `sendPhoto`
Envia uma foto.

```php
POST https://api.telegram.org/bot{token}/sendPhoto

// Parâmetros:
{
  "chat_id": 123456789,
  "photo": "https://example.com/image.jpg",  // URL ou file_id
  "caption": "Legenda da foto",
  "parse_mode": "HTML"
}
```

#### `sendVideo`
Envia um vídeo.

```php
POST https://api.telegram.org/bot{token}/sendVideo

// Parâmetros:
{
  "chat_id": 123456789,
  "video": "https://example.com/video.mp4",
  "caption": "Legenda do vídeo"
}
```

#### `sendDocument`
Envia um documento.

```php
POST https://api.telegram.org/bot{token}/sendDocument

// Parâmetros:
{
  "chat_id": 123456789,
  "document": "https://example.com/file.pdf",
  "caption": "Legenda do documento"
}
```

### Métodos de Grupo

#### `getChat`
Obtém informações sobre um chat/grupo.

```php
GET https://api.telegram.org/bot{token}/getChat?chat_id=-1001234567890

// Retorna:
{
  "ok": true,
  "result": {
    "id": -1001234567890,
    "type": "supergroup",
    "title": "Meu Grupo",
    "username": "meugrupo",
    "description": "Descrição do grupo",
    "members_count": 150
  }
}
```

#### `getChatMember`
Obtém informações sobre um membro do chat.

```php
GET https://api.telegram.org/bot{token}/getChatMember
  ?chat_id=-1001234567890
  &user_id=123456789

// Retorna:
{
  "ok": true,
  "result": {
    "user": {...},
    "status": "member",  // creator, administrator, member, left, kicked
    "can_invite_users": true,
    "can_restrict_members": false
  }
}
```

**Uso na aplicação:**
```php
// TelegramService::getChatMember()
public function getChatMember(string $token, string $groupId, int $userId): array
```

#### `getChatMemberCount`
Obtém o número de membros do chat.

```php
GET https://api.telegram.org/bot{token}/getChatMemberCount?chat_id=-1001234567890
```

#### `banChatMember`
Remove e bane um membro do grupo.

```php
POST https://api.telegram.org/bot{token}/banChatMember

// Parâmetros:
{
  "chat_id": -1001234567890,
  "user_id": 123456789,
  "revoke_messages": false,  // Revogar mensagens?
  "until_date": 0            // 0 = permanente, timestamp = temporário
}
```

**Uso na aplicação:**
```php
// TelegramService::removeUserFromGroup()
public function removeUserFromGroup(string $token, string $groupId, int $userId): array
```

#### `unbanChatMember`
Desbane um usuário do grupo.

```php
POST https://api.telegram.org/bot{token}/unbanChatMember

// Parâmetros:
{
  "chat_id": -1001234567890,
  "user_id": 123456789,
  "only_if_banned": true  // Só executa se usuário estiver banido
}
```

#### `inviteChatMember`
Convida um usuário para o grupo (apenas grupos pequenos).

```php
POST https://api.telegram.org/bot{token}/inviteChatMember

// Parâmetros:
{
  "chat_id": -1001234567890,
  "user_id": 123456789
}
```

**Nota:** Para supergrupos, use `createChatInviteLink` e envie o link para o usuário.

### Métodos de Comandos

#### `setMyCommands`
Define os comandos do bot (aparecem no menu do Telegram).

```php
POST https://api.telegram.org/bot{token}/setMyCommands

// Parâmetros:
{
  "commands": [
    {"command": "start", "description": "Iniciar conversa"},
    {"command": "help", "description": "Ver ajuda"},
    {"command": "planos", "description": "Ver planos"}
  ]
}
```

**Uso na aplicação:**
```php
// TelegramService::registerBotCommands()
public function registerBotCommands(Bot $bot): bool
```

#### `getMyCommands`
Obtém a lista de comandos configurados.

```php
GET https://api.telegram.org/bot{token}/getMyCommands
```

#### `deleteMyCommands`
Remove todos os comandos configurados.

```php
POST https://api.telegram.org/bot{token}/deleteMyCommands
```

### Métodos de Updates (Polling)

#### `getUpdates`
Obtém atualizações pendentes (usado em polling).

```php
GET https://api.telegram.org/bot{token}/getUpdates
  ?offset=1234567
  &limit=100
  &timeout=30
  &allowed_updates=["message","callback_query"]

// Retorna:
{
  "ok": true,
  "result": [
    {
      "update_id": 1234567,
      "message": {
        "message_id": 123,
        "from": {...},
        "chat": {...},
        "text": "/start"
      }
    }
  ]
}
```

**Uso na aplicação:**
```bash
# Comando Artisan para polling
php artisan telegram:polling --bot-id=1
```

---

## Endpoints da Aplicação

### Autenticação

#### `POST /api/auth/login`
Faz login na aplicação.

```json
// Request
{
  "email": "admin@example.com",
  "password": "senha123"
}

// Response 200
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "bearer",
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@example.com",
    "role": "super_admin"
  }
}
```

#### `GET /api/auth/me`
Obtém informações do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "id": 1,
  "name": "Admin",
  "email": "admin@example.com",
  "role": "super_admin"
}
```

### Gerenciamento de Bots

#### `GET /api/bots`
Lista todos os bots do usuário autenticado.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "bots": [
    {
      "id": 1,
      "name": "Meu Bot",
      "token": "123456789:ABCdef...",
      "telegram_group_id": "-1001234567890",
      "active": true,
      "activated": true,
      "request_email": true,
      "request_phone": false,
      "created_at": "2025-12-01T10:00:00.000000Z"
    }
  ]
}
```

#### `POST /api/bots`
Cria um novo bot.

**Headers:** `Authorization: Bearer {token}`

```json
// Request
{
  "name": "Novo Bot",
  "token": "123456789:ABCdef...",
  "telegram_group_id": "-1001234567890",
  "active": true,
  "initial_message": "Bem-vindo!",
  "request_email": true,
  "request_phone": false,
  "payment_method": "credit_card"
}

// Response 201
{
  "bot": {
    "id": 2,
    "name": "Novo Bot",
    "token": "123456789:ABCdef...",
    "active": true,
    "activated": false
  },
  "bot_info": {
    "id": 123456789,
    "first_name": "Novo Bot",
    "username": "novobot"
  },
  "token_valid": true
}
```

**Validações:**
- Token é validado automaticamente ao criar
- Se inválido, retorna erro 400

#### `GET /api/bots/{id}`
Obtém detalhes de um bot específico.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "bot": {
    "id": 1,
    "name": "Meu Bot",
    "token": "123456789:ABCdef...",
    "telegram_group_id": "-1001234567890",
    "active": true,
    "activated": true
  }
}
```

#### `PUT /api/bots/{id}`
Atualiza um bot.

**Headers:** `Authorization: Bearer {token}`

```json
// Request
{
  "name": "Bot Atualizado",
  "initial_message": "Nova mensagem de boas-vindas"
}

// Response 200
{
  "bot": {
    "id": 1,
    "name": "Bot Atualizado",
    "initial_message": "Nova mensagem de boas-vindas"
  }
}
```

#### `DELETE /api/bots/{id}`
Exclui um bot.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "message": "Bot deleted successfully"
}
```

#### `POST /api/bots/{id}/initialize`
Inicializa um bot (marca como ativado e registra comandos).

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "message": "Bot inicializado com sucesso. Configure webhook...",
  "bot": {...},
  "bot_info": {
    "id": 123456789,
    "first_name": "Meu Bot",
    "username": "meubot"
  },
  "has_webhook": false,
  "webhook_url": null
}
```

#### `POST /api/bots/{id}/stop`
Para um bot (marca como desativado).

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "message": "Bot parado com sucesso",
  "bot": {...}
}
```

#### `GET /api/bots/{id}/status`
Obtém status detalhado de um bot.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "bot_id": 1,
  "active": true,
  "activated": true,
  "token_valid": true,
  "bot_info": {
    "id": 123456789,
    "first_name": "Meu Bot",
    "username": "meubot",
    "can_join_groups": true,
    "can_read_all_group_messages": false
  },
  "permissions": {
    "can_read_all_group_messages": false,
    "can_join_groups": true
  },
  "warnings": [
    {
      "type": "critical",
      "permission": "can_read_all_group_messages",
      "message": "O bot não pode ler todas as mensagens do grupo...",
      "solution": "Configure no BotFather com /setprivacy"
    }
  ],
  "can_manage_groups": false
}
```

#### `POST /api/bots/validate`
Valida um token de bot.

**Headers:** `Authorization: Bearer {token}`

```json
// Request
{
  "token": "123456789:ABCdef..."
}

// Response 200
{
  "valid": true,
  "message": "Token válido",
  "bot": {
    "id": 123456789,
    "first_name": "Meu Bot",
    "username": "meubot"
  }
}

// Response 400 (token inválido)
{
  "valid": false,
  "error": "Token inválido: Unauthorized"
}
```

#### `POST /api/bots/{id}/validate-and-activate`
Valida e ativa o bot em uma única operação.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "success": true,
  "message": "Bot validado e ativado com sucesso!",
  "bot": {...},
  "bot_info": {...},
  "token_valid": true,
  "activated": true
}
```

#### `POST /api/bots/{id}/media/upload`
Faz upload de mídia para o bot.

**Headers:** 
- `Authorization: Bearer {token}`
- `Content-Type: multipart/form-data`

```json
// Form Data
{
  "file": <arquivo>,
  "media_number": 1  // 1, 2 ou 3
}

// Response 200
{
  "success": true,
  "message": "Mídia enviada com sucesso",
  "url": "http://localhost:8000/storage/bots/1/media/bot_1_media_1_1733241234.jpg",
  "file": {
    "name": "imagem.jpg",
    "path": "bots/1/media/bot_1_media_1_1733241234.jpg",
    "url": "http://localhost:8000/storage/...",
    "size": 150000,
    "size_formatted": "146.48 KB"
  }
}
```

**Formatos aceitos:**
- Imagens: jpg, jpeg, png, gif
- Vídeos: mp4, avi, mov, webm
- Documentos: pdf, doc, docx
- Tamanho máximo: 10MB

### Webhook do Telegram

#### `POST /api/telegram/webhook/{botId}`
Endpoint público que recebe atualizações do Telegram via webhook.

**Não requer autenticação** (Telegram precisa acessar)

```json
// Request (enviado pelo Telegram)
{
  "update_id": 1234567,
  "message": {
    "message_id": 123,
    "from": {
      "id": 987654321,
      "is_bot": false,
      "first_name": "João",
      "username": "joao123"
    },
    "chat": {
      "id": 987654321,
      "first_name": "João",
      "type": "private"
    },
    "date": 1733241234,
    "text": "/start"
  }
}

// Response 200
{
  "ok": true
}
```

**Validação de origem:**
- Header `X-Telegram-Bot-Api-Secret-Token` (se configurado)
- Estrutura da atualização

#### `POST /api/telegram/webhook/{botId}/set`
Configura webhook para um bot.

**Headers:** `Authorization: Bearer {token}`

```json
// Request
{
  "url": "https://seu-dominio.com/api/telegram/webhook/1",  // Opcional
  "secret_token": "token_secreto",  // Opcional
  "allowed_updates": ["message", "callback_query"],  // Opcional
  "drop_pending_updates": false  // Opcional
}

// Response 200
{
  "success": true,
  "message": "Webhook configurado com sucesso",
  "webhook_url": "https://seu-dominio.com/api/telegram/webhook/1",
  "webhook_info": {
    "url": "https://seu-dominio.com/api/telegram/webhook/1",
    "has_custom_certificate": false,
    "pending_update_count": 0,
    "last_error_date": null,
    "last_error_message": null,
    "max_connections": 40,
    "allowed_updates": ["message", "callback_query"]
  }
}
```

**Requisitos:**
- URL deve ser HTTPS
- Porta: 443, 80, 88 ou 8443
- `TELEGRAM_WEBHOOK_URL` deve estar configurado no `.env`

#### `POST /api/telegram/webhook/{botId}/delete`
Remove webhook configurado.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "success": true,
  "message": "Webhook removido com sucesso"
}
```

#### `GET /api/telegram/webhook/{botId}/info`
Obtém informações do webhook.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "success": true,
  "webhook_info": {
    "url": "https://seu-dominio.com/api/telegram/webhook/1",
    "has_custom_certificate": false,
    "pending_update_count": 0,
    "last_error_date": null,
    "last_error_message": null,
    "max_connections": 40,
    "allowed_updates": ["message", "callback_query"]
  }
}
```

### Comandos do Bot

#### `GET /api/bots/{botId}/commands`
Lista comandos personalizados do bot.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "commands": [
    {
      "id": 1,
      "bot_id": 1,
      "command": "ajuda",
      "response": "Como posso ajudar?",
      "description": "Solicitar ajuda",
      "active": true,
      "usage_count": 42
    }
  ]
}
```

#### `POST /api/bots/{botId}/commands`
Cria um novo comando personalizado.

**Headers:** `Authorization: Bearer {token}`

```json
// Request
{
  "command": "ajuda",
  "response": "Como posso ajudar você hoje?",
  "description": "Solicitar ajuda",
  "active": true
}

// Response 201
{
  "command": {
    "id": 2,
    "bot_id": 1,
    "command": "ajuda",
    "response": "Como posso ajudar você hoje?",
    "description": "Solicitar ajuda",
    "active": true,
    "usage_count": 0
  }
}
```

#### `PUT /api/bots/{botId}/commands/{commandId}`
Atualiza um comando.

**Headers:** `Authorization: Bearer {token}`

```json
// Request
{
  "response": "Nova resposta para o comando",
  "active": true
}

// Response 200
{
  "command": {...}
}
```

#### `DELETE /api/bots/{botId}/commands/{commandId}`
Exclui um comando.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "message": "Command deleted successfully"
}
```

#### `POST /api/bots/{botId}/commands/register`
Registra comandos no Telegram (aparece no menu do Telegram).

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "success": true,
  "message": "Comandos registrados no Telegram com sucesso",
  "commands_count": 5
}
```

#### `GET /api/bots/{botId}/commands/telegram`
Lista comandos registrados no Telegram.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "success": true,
  "commands": [
    {"command": "start", "description": "Iniciar conversa"},
    {"command": "help", "description": "Ver ajuda"}
  ]
}
```

### Gerenciamento de Contatos

#### `GET /api/contacts`
Lista contatos cadastrados.

**Headers:** `Authorization: Bearer {token}`

**Query Params:**
- `bot_id`: Filtrar por bot
- `is_blocked`: Filtrar bloqueados
- `telegram_status`: Filtrar por status (active, inactive, deleted, banned)

```json
// Response 200
{
  "contacts": [
    {
      "id": 1,
      "bot_id": 1,
      "telegram_id": 987654321,
      "username": "joao123",
      "first_name": "João",
      "last_name": "Silva",
      "email": "joao@example.com",
      "phone": "+5511999999999",
      "is_blocked": false,
      "telegram_status": "active",
      "last_interaction_at": "2025-12-03T10:00:00.000000Z"
    }
  ]
}
```

#### `GET /api/contacts/stats`
Obtém estatísticas de contatos.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "total": 150,
  "active": 120,
  "inactive": 20,
  "blocked": 10,
  "with_email": 80,
  "with_phone": 60
}
```

#### `POST /api/contacts/{id}/block`
Bloqueia/desbloqueia um contato.

**Headers:** `Authorization: Bearer {token}`

```json
// Request
{
  "is_blocked": true
}

// Response 200
{
  "contact": {...}
}
```

### Gerenciamento de Grupos

#### `POST /api/bots/{botId}/group/add-member`
Adiciona um membro ao grupo.

**Headers:** `Authorization: Bearer {token}`

```json
// Request
{
  "contact_id": 1
}

// Response 200
{
  "success": true,
  "message": "Usuário adicionado ao grupo com sucesso"
}
```

#### `POST /api/bots/{botId}/group/remove-member`
Remove um membro do grupo.

**Headers:** `Authorization: Bearer {token}`

```json
// Request
{
  "contact_id": 1
}

// Response 200
{
  "success": true,
  "message": "Usuário removido do grupo com sucesso"
}
```

#### `GET /api/bots/{botId}/group/member-status/{contactId}`
Verifica status de um membro no grupo.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "is_member": true,
  "status": "member",  // creator, administrator, member, left, kicked
  "can_invite_users": false,
  "can_restrict_members": false,
  "user": {
    "id": 987654321,
    "first_name": "João",
    "username": "joao123"
  }
}
```

#### `GET /api/bots/{botId}/group/info`
Obtém informações do grupo.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "id": -1001234567890,
  "title": "Meu Grupo VIP",
  "type": "supergroup",
  "username": "meugrupo",
  "description": "Grupo de membros VIP",
  "member_count": 150
}
```

#### `GET /api/bots/{botId}/group/statistics`
Obtém estatísticas do grupo.

**Headers:** `Authorization: Bearer {token}`

```json
// Response 200
{
  "total_members": 150,
  "active_members": 120,
  "administrators": 5,
  "recent_additions": 10,
  "recent_removals": 3
}
```

---

## Webhooks e Polling

### Modo Webhook (Recomendado para Produção)

O webhook é o método recomendado para receber atualizações em produção.

**Vantagens:**
- Recebe atualizações em tempo real
- Não consome recursos constantemente
- Mais eficiente para múltiplos bots

**Requisitos:**
1. Domínio com HTTPS válido
2. Porta: 443, 80, 88 ou 8443
3. Certificado SSL válido

**Configuração:**

```bash
# 1. Configure variável de ambiente
TELEGRAM_WEBHOOK_URL=https://seu-dominio.com

# 2. Configure webhook via API
POST /api/telegram/webhook/{botId}/set
```

**Fluxo:**

```
Telegram → HTTPS → Seu servidor → Webhook Controller
                                         ↓
                                   ProcessTelegramUpdate (Job)
                                         ↓
                                   TelegramService
```

**Código:**

```php
// TelegramWebhookController::webhook()
public function webhook(Request $request, string $botId): JsonResponse
{
    $bot = Bot::find($botId);
    
    if (!$bot || !$bot->active || !$bot->activated) {
        return response()->json(['error' => 'Bot not active'], 400);
    }

    $update = $request->all();
    
    // Processa atualização
    $telegramService = new TelegramService();
    $telegramService->processUpdate($bot, $update);

    return response()->json(['ok' => true]);
}
```

### Modo Polling (Desenvolvimento e Testes)

Polling consulta periodicamente a API do Telegram por novas atualizações.

**Vantagens:**
- Funciona sem HTTPS
- Útil para desenvolvimento local
- Não requer domínio público

**Desvantagens:**
- Consome mais recursos
- Delay nas respostas
- Não recomendado para produção

**Uso:**

```bash
# Inicia polling para um bot específico
php artisan telegram:polling --bot-id=1

# Com interval personalizado (padrão: 1 segundo)
php artisan telegram:polling --bot-id=1 --interval=2

# Verbose para debug
php artisan telegram:polling --bot-id=1 --verbose
```

**Código:**

```php
// app/Console/Commands/TelegramPollingCommand.php
public function handle()
{
    $botId = $this->option('bot-id');
    $bot = Bot::find($botId);
    
    $offset = 0;
    $telegramService = new TelegramService();
    
    while (true) {
        $response = Http::get("https://api.telegram.org/bot{$bot->token}/getUpdates", [
            'offset' => $offset,
            'timeout' => 30,
            'allowed_updates' => ['message', 'callback_query']
        ]);
        
        $updates = $response->json()['result'] ?? [];
        
        foreach ($updates as $update) {
            $telegramService->processUpdate($bot, $update);
            $offset = $update['update_id'] + 1;
        }
        
        sleep($this->option('interval') ?? 1);
    }
}
```

---

## Processamento de Mensagens

### Tipos de Update

A API do Telegram envia diferentes tipos de atualizações:

1. **`message`**: Mensagem nova
2. **`edited_message`**: Mensagem editada
3. **`channel_post`**: Post em canal
4. **`callback_query`**: Resposta de botão inline
5. **`inline_query`**: Query inline
6. **`my_chat_member`**: Mudança de status do bot em chat
7. **`chat_member`**: Mudança de membro em chat

### Processamento de Mensagens

```php
// TelegramService::processUpdate()
public function processUpdate(Bot $bot, array $update): void
{
    // Identifica tipo de atualização
    if (isset($update['message'])) {
        $this->processMessage($bot, $update['message']);
    }
    
    if (isset($update['edited_message'])) {
        $this->processMessage($bot, $update['edited_message'], true);
    }
    
    if (isset($update['callback_query'])) {
        $this->processCallbackQuery($bot, $update['callback_query']);
    }
    
    if (isset($update['inline_query'])) {
        $this->processInlineQuery($bot, $update['inline_query']);
    }
}
```

### Mensagens de Texto

```php
protected function processMessage(Bot $bot, array $message, bool $isEdited = false): void
{
    $from = $message['from'] ?? null;
    $chat = $message['chat'] ?? null;
    $text = $message['text'] ?? null;
    
    // Salva/atualiza contato
    if ($chat['type'] === 'private') {
        $contact = $this->saveOrUpdateContact($bot, $from);
    }
    
    // Verifica se é comando
    $isCommand = false;
    if (isset($message['entities'])) {
        foreach ($message['entities'] as $entity) {
            if ($entity['type'] === 'bot_command') {
                $isCommand = true;
                $command = substr($text, $entity['offset'], $entity['length']);
                break;
            }
        }
    }
    
    // Fallback: verifica se começa com /
    if (!$isCommand && $text && str_starts_with(trim($text), '/')) {
        $isCommand = true;
        $command = explode(' ', trim($text))[0];
    }
    
    if ($isCommand) {
        $this->processCommand($bot, $chat['id'], $from, $command);
    } else {
        $this->processTextMessage($bot, $chat['id'], $from, $text);
    }
}
```

### Processamento de Comandos

```php
protected function processCommand(Bot $bot, int $chatId, array $from, string $command): void
{
    // Remove @ do comando
    $command = preg_replace('/@\w+/', '', $command);
    $command = strtolower(trim($command, '/'));
    
    // Comandos do sistema
    switch ($command) {
        case 'start':
            $this->handleStartCommand($bot, $chatId, $from);
            break;
            
        case 'help':
            $this->handleHelpCommand($bot, $chatId);
            break;
            
        case 'planos':
            $this->handlePlanosCommand($bot, $chatId);
            break;
            
        default:
            // Busca comando personalizado
            $customCommand = BotCommand::where('bot_id', $bot->id)
                ->where('command', $command)
                ->where('active', true)
                ->first();
                
            if ($customCommand) {
                $customCommand->incrementUsage();
                $this->sendMessage($bot, $chatId, $customCommand->response);
            }
    }
}
```

### Comando /start

O comando `/start` é especial e inicia o fluxo de onboarding:

```php
protected function handleStartCommand(Bot $bot, int $chatId, array $from): void
{
    $contact = $this->saveOrUpdateContact($bot, $from);
    
    // Envia mensagem inicial
    if ($bot->initial_message) {
        $this->sendMessage($bot, $chatId, $bot->initial_message);
    }
    
    // Envia mídias configuradas
    $this->sendMedia($bot, $chatId);
    
    // Solicita dados adicionais
    if ($bot->request_email && !$contact->email) {
        $this->sendMessage($bot, $chatId, "Por favor, informe seu email:");
        // Aguarda resposta...
    }
    
    if ($bot->request_phone && !$contact->phone) {
        $keyboard = [
            'keyboard' => [[
                ['text' => 'Compartilhar telefone', 'request_contact' => true]
            ]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        $this->sendMessage($bot, $chatId, "Compartilhe seu telefone:", $keyboard);
    }
    
    // Envia botões de pagamento
    if ($bot->activate_cta) {
        $this->sendPaymentButtons($bot, $chatId, $contact);
    }
}
```

### Callback Query (Botões Inline)

```php
protected function processCallbackQuery(Bot $bot, array $callbackQuery): void
{
    $callbackId = $callbackQuery['id'];
    $data = $callbackQuery['data'] ?? null;
    $from = $callbackQuery['from'];
    $message = $callbackQuery['message'] ?? null;
    
    // Responde à callback (remove loading)
    Http::post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
        'callback_query_id' => $callbackId,
        'text' => 'Processando...'
    ]);
    
    // Processa ação baseada no data
    if (str_starts_with($data, 'payment_')) {
        $this->handlePaymentCallback($bot, $from, $data, $message);
    } elseif (str_starts_with($data, 'plan_')) {
        $this->handlePlanCallback($bot, $from, $data, $message);
    }
}
```

### Mensagens em Grupos

```php
protected function processGroupMessage(Bot $bot, array $message, array $chat): void
{
    $text = $message['text'] ?? null;
    $from = $message['from'] ?? null;
    $chatId = $chat['id'];
    
    // Salva contato
    $contact = $this->saveOrUpdateContact($bot, $from);
    
    // Verifica se usuário é membro do grupo configurado
    if ($bot->telegram_group_id) {
        $memberInfo = $this->getChatMember($bot->token, $bot->telegram_group_id, $from['id']);
        
        // Atualiza status do contato
        if ($memberInfo['is_member']) {
            $contact->update(['telegram_status' => 'active']);
        } else {
            $contact->update(['telegram_status' => 'inactive']);
        }
    }
    
    // Processa comandos apenas se mencionar o bot
    if ($text && str_contains($text, '@' . $bot->username)) {
        // Processa comando...
    }
}
```

---

## Gerenciamento de Grupos

### Adicionar Membro ao Grupo

```php
// GroupManagementController::addMember()
public function addMember(Request $request, string $botId): JsonResponse
{
    $bot = Bot::findOrFail($botId);
    $contact = Contact::findOrFail($request->input('contact_id'));
    
    if (!$bot->telegram_group_id) {
        return response()->json([
            'success' => false,
            'error' => 'Bot não tem grupo configurado'
        ], 400);
    }
    
    $telegramService = new TelegramService();
    $result = $telegramService->addUserToGroup(
        $bot->token,
        $bot->telegram_group_id,
        $contact->telegram_id
    );
    
    if ($result['success']) {
        // Atualiza status do contato
        $contact->update(['telegram_status' => 'active']);
        
        // Registra ação
        ContactActionService::logAction($contact, 'added_to_group');
    }
    
    return response()->json($result);
}
```

### Remover Membro do Grupo

```php
// GroupManagementController::removeMember()
public function removeMember(Request $request, string $botId): JsonResponse
{
    $bot = Bot::findOrFail($botId);
    $contact = Contact::findOrFail($request->input('contact_id'));
    
    $telegramService = new TelegramService();
    $result = $telegramService->removeUserFromGroup(
        $bot->token,
        $bot->telegram_group_id,
        $contact->telegram_id
    );
    
    if ($result['success']) {
        // Atualiza status do contato
        $contact->update(['telegram_status' => 'banned']);
        
        // Registra ação
        ContactActionService::logAction($contact, 'removed_from_group');
    }
    
    return response()->json($result);
}
```

### Verificar Status de Membro

```php
// TelegramService::getChatMember()
public function getChatMember(string $token, string $groupId, int $userId): array
{
    $response = Http::get("https://api.telegram.org/bot{$token}/getChatMember", [
        'chat_id' => $groupId,
        'user_id' => $userId
    ]);
    
    if (!$response->successful() || !$response->json()['ok']) {
        return [
            'is_member' => false,
            'status' => 'not_member'
        ];
    }
    
    $member = $response->json()['result'];
    $status = $member['status'] ?? 'unknown';
    
    return [
        'is_member' => in_array($status, ['member', 'administrator', 'creator']),
        'status' => $status,
        'can_invite_users' => $member['can_invite_users'] ?? false,
        'can_restrict_members' => $member['can_restrict_members'] ?? false,
        'can_delete_messages' => $member['can_delete_messages'] ?? false,
        'user' => $member['user'] ?? null
    ];
}
```

### Sincronizar Membros do Grupo

```php
// ContactController::syncGroupMembers()
public function syncGroupMembers(Request $request): JsonResponse
{
    $bot = Bot::findOrFail($request->input('bot_id'));
    
    if (!$bot->telegram_group_id) {
        return response()->json([
            'success' => false,
            'error' => 'Bot não tem grupo configurado'
        ], 400);
    }
    
    $contacts = Contact::where('bot_id', $bot->id)->get();
    $telegramService = new TelegramService();
    
    $updated = 0;
    
    foreach ($contacts as $contact) {
        $memberInfo = $telegramService->getChatMember(
            $bot->token,
            $bot->telegram_group_id,
            $contact->telegram_id
        );
        
        $newStatus = match ($memberInfo['status']) {
            'member', 'administrator', 'creator' => 'active',
            'left' => 'inactive',
            'kicked' => 'banned',
            default => 'deleted'
        };
        
        if ($contact->telegram_status !== $newStatus) {
            $contact->update(['telegram_status' => $newStatus]);
            $updated++;
        }
    }
    
    return response()->json([
        'success' => true,
        'message' => "Sincronização concluída. {$updated} contatos atualizados."
    ]);
}
```

---

## Sistema de Comandos

### Comandos Padrão do Sistema

A aplicação possui três comandos padrão:

1. **`/start`**: Inicia conversa e fluxo de onboarding
2. **`/help`**: Lista comandos disponíveis
3. **`/planos`**: Exibe planos de pagamento

### Comandos Personalizados

Administradores podem criar comandos personalizados:

```php
// BotCommand model
class BotCommand extends Model
{
    protected $fillable = [
        'bot_id',
        'command',      // Nome do comando (sem /)
        'response',     // Resposta a ser enviada
        'description',  // Descrição para menu
        'active',       // Comando está ativo?
        'usage_count'   // Contador de uso
    ];
}
```

**Exemplo:**

```json
{
  "command": "suporte",
  "response": "Para suporte, entre em contato: suporte@example.com\n\nHorário de atendimento: 9h às 18h",
  "description": "Falar com suporte",
  "active": true
}
```

### Registro de Comandos no Telegram

Os comandos são registrados no Telegram usando `setMyCommands`:

```php
// TelegramService::registerBotCommands()
public function registerBotCommands(Bot $bot): bool
{
    // Comandos padrão
    $commands = [
        ['command' => 'start', 'description' => 'Iniciar conversa com o bot'],
        ['command' => 'help', 'description' => 'Ver comandos disponíveis'],
        ['command' => 'planos', 'description' => 'Ver planos de pagamento']
    ];
    
    // Adiciona comandos personalizados
    $customCommands = BotCommand::where('bot_id', $bot->id)
        ->where('active', true)
        ->get();
        
    foreach ($customCommands as $cmd) {
        $commands[] = [
            'command' => $cmd->command,
            'description' => $cmd->description ?? 'Comando personalizado'
        ];
    }
    
    // Registra no Telegram
    $response = Http::asJson()->post(
        "https://api.telegram.org/bot{$bot->token}/setMyCommands",
        ['commands' => $commands]
    );
    
    return $response->successful() && $response->json()['ok'];
}
```

### Execução de Comandos

```php
protected function processCommand(Bot $bot, int $chatId, array $from, string $command): void
{
    // Limpa comando (remove / e @botname)
    $command = preg_replace('/@\w+/', '', $command);
    $command = strtolower(trim($command, '/'));
    
    // Comandos do sistema
    if ($command === 'start') {
        $this->handleStartCommand($bot, $chatId, $from);
        return;
    }
    
    if ($command === 'help') {
        $this->handleHelpCommand($bot, $chatId);
        return;
    }
    
    if ($command === 'planos') {
        $this->handlePlanosCommand($bot, $chatId);
        return;
    }
    
    // Busca comando personalizado
    $customCommand = BotCommand::where('bot_id', $bot->id)
        ->where('command', $command)
        ->where('active', true)
        ->first();
        
    if ($customCommand) {
        // Incrementa contador
        $customCommand->incrementUsage();
        
        // Envia resposta
        $this->sendMessage($bot, $chatId, $customCommand->response);
        
        $this->logBotAction($bot, "Comando executado: /{$command}", 'info', [
            'chat_id' => $chatId,
            'user_id' => $from['id'],
            'usage_count' => $customCommand->usage_count
        ]);
    } else {
        // Comando não encontrado
        $this->sendMessage($bot, $chatId, 
            "Comando /{$command} não encontrado. Use /help para ver comandos disponíveis."
        );
    }
}
```

---

## Sistema de Pagamentos

A aplicação integra com **MercadoPago** (PIX e Cartão) e **Stripe** (Cartão).

### Fluxo de Pagamento

```
Usuário → /start → Bot envia planos
              ↓
    Usuário escolhe plano
              ↓
    Bot envia métodos de pagamento
              ↓
    Usuário escolhe PIX ou Cartão
              ↓
        Processamento
              ↓
    Webhook de confirmação
              ↓
    Adiciona usuário ao grupo
```

### Envio de Planos

```php
protected function handlePlanosCommand(Bot $bot, int $chatId): void
{
    $plans = PaymentPlan::where('active', true)
        ->orderBy('price')
        ->get();
    
    if ($plans->isEmpty()) {
        $this->sendMessage($bot, $chatId, "Nenhum plano disponível no momento.");
        return;
    }
    
    $message = "💎 <b>Planos Disponíveis</b>\n\n";
    
    $buttons = [];
    foreach ($plans as $plan) {
        $message .= "📦 <b>{$plan->name}</b>\n";
        $message .= "   💰 R$ " . number_format($plan->price, 2, ',', '.') . "\n";
        $message .= "   ⏱ {$plan->duration_days} dias\n";
        if ($plan->description) {
            $message .= "   📝 {$plan->description}\n";
        }
        $message .= "\n";
        
        $buttons[] = [
            ['text' => "Assinar {$plan->name}", 'callback_data' => "plan_{$plan->id}"]
        ];
    }
    
    $keyboard = ['inline_keyboard' => $buttons];
    
    $this->sendMessage($bot, $chatId, $message, $keyboard);
}
```

### Callback de Plano Selecionado

```php
protected function handlePlanCallback(Bot $bot, array $from, string $data, ?array $message): void
{
    $planId = (int) str_replace('plan_', '', $data);
    $plan = PaymentPlan::find($planId);
    
    if (!$plan) {
        return;
    }
    
    $contact = Contact::where('bot_id', $bot->id)
        ->where('telegram_id', $from['id'])
        ->first();
    
    // Cria transação pendente
    $transaction = Transaction::create([
        'contact_id' => $contact->id,
        'payment_plan_id' => $plan->id,
        'amount' => $plan->price,
        'status' => 'pending',
        'payment_method' => $bot->payment_method
    ]);
    
    // Envia opções de pagamento
    $buttons = [];
    
    if ($bot->payment_method === 'pix' || $bot->payment_method === 'both') {
        $buttons[] = [
            ['text' => '💳 Pagar com PIX', 'callback_data' => "payment_pix_{$transaction->id}"]
        ];
    }
    
    if ($bot->payment_method === 'credit_card' || $bot->payment_method === 'both') {
        $buttons[] = [
            ['text' => '💳 Pagar com Cartão', 'callback_data' => "payment_card_{$transaction->id}"]
        ];
    }
    
    $keyboard = ['inline_keyboard' => $buttons];
    
    $message = "💰 <b>Plano selecionado: {$plan->name}</b>\n\n";
    $message .= "💵 Valor: R$ " . number_format($plan->price, 2, ',', '.') . "\n\n";
    $message .= "Escolha a forma de pagamento:";
    
    $this->sendMessage($bot, $message['chat']['id'], $message, $keyboard);
}
```

### Processamento de Pagamento PIX

```php
// PaymentController::processPix()
public function processPix(Request $request): JsonResponse
{
    $transaction = Transaction::findOrFail($request->input('transaction_id'));
    
    // Cria preferência no MercadoPago
    $mercadoPago = new \MercadoPago\SDK();
    $mercadoPago->setAccessToken(env('MERCADOPAGO_ACCESS_TOKEN'));
    
    $preference = new \MercadoPago\Preference();
    $item = new \MercadoPago\Item();
    $item->title = $transaction->paymentPlan->name;
    $item->quantity = 1;
    $item->unit_price = $transaction->amount;
    
    $preference->items = [$item];
    $preference->payment_methods = [
        'excluded_payment_types' => [
            ['id' => 'credit_card'],
            ['id' => 'debit_card']
        ]
    ];
    
    $preference->external_reference = $transaction->id;
    $preference->notification_url = env('MERCADOPAGO_WEBHOOK_URL');
    
    $preference->save();
    
    // Atualiza transação
    $transaction->update([
        'external_id' => $preference->id,
        'status' => 'pending'
    ]);
    
    // Envia QR Code PIX para o usuário
    $bot = $transaction->contact->bot;
    $message = "🔐 <b>Pagamento via PIX</b>\n\n";
    $message .= "Escaneie o QR Code abaixo ou copie o código PIX:\n\n";
    $message .= "<code>{$preference->init_point}</code>";
    
    $telegramService = new TelegramService();
    $telegramService->sendMessage(
        $bot,
        $transaction->contact->telegram_id,
        $message
    );
    
    return response()->json([
        'success' => true,
        'preference_id' => $preference->id,
        'init_point' => $preference->init_point
    ]);
}
```

### Webhook de Confirmação de Pagamento

```php
// PaymentController::mercadoPagoWebhook()
public function mercadoPagoWebhook(Request $request): JsonResponse
{
    $paymentId = $request->input('data.id');
    
    // Busca dados do pagamento
    $mercadoPago = new \MercadoPago\SDK();
    $mercadoPago->setAccessToken(env('MERCADOPAGO_ACCESS_TOKEN'));
    
    $payment = \MercadoPago\Payment::find_by_id($paymentId);
    
    if ($payment->status === 'approved') {
        // Busca transação
        $transaction = Transaction::where('external_id', $payment->external_reference)
            ->first();
            
        if ($transaction && $transaction->status !== 'completed') {
            // Atualiza transação
            $transaction->update([
                'status' => 'completed',
                'paid_at' => now()
            ]);
            
            // Adiciona usuário ao grupo
            $contact = $transaction->contact;
            $bot = $contact->bot;
            
            if ($bot->telegram_group_id) {
                $telegramService = new TelegramService();
                $result = $telegramService->addUserToGroup(
                    $bot->token,
                    $bot->telegram_group_id,
                    $contact->telegram_id
                );
                
                if ($result['success']) {
                    // Atualiza status e expiração
                    $expiresAt = now()->addDays($transaction->paymentPlan->duration_days);
                    $contact->update([
                        'telegram_status' => 'active',
                        'expires_at' => $expiresAt
                    ]);
                    
                    // Envia confirmação
                    $message = "✅ <b>Pagamento confirmado!</b>\n\n";
                    $message .= "Você foi adicionado ao grupo VIP.\n";
                    $message .= "Seu acesso expira em: " . $expiresAt->format('d/m/Y');
                    
                    $telegramService->sendMessage($bot, $contact->telegram_id, $message);
                }
            }
        }
    }
    
    return response()->json(['ok' => true]);
}
```

---

## Sistema de Alertas

O sistema de alertas permite enviar mensagens programadas para usuários.

### Modelo Alert

```php
class Alert extends Model
{
    protected $fillable = [
        'bot_id',
        'name',
        'message',
        'scheduled_at',
        'status',           // pending, processing, completed, failed
        'target_type',      // all, active, expired, specific
        'target_filters',   // JSON com filtros
        'sent_count',
        'failed_count'
    ];
}
```

### Criação de Alerta

```php
// POST /api/alerts
{
  "bot_id": 1,
  "name": "Lembrete de Renovação",
  "message": "⚠️ Seu acesso expira em 3 dias! Renove agora para continuar com os benefícios.",
  "scheduled_at": "2025-12-10 10:00:00",
  "target_type": "expiring",
  "target_filters": {
    "days_to_expire": 3
  }
}
```

### Processamento de Alertas

```bash
# Comando Artisan executado por CRON
php artisan alerts:process
```

```php
// ProcessScheduledAlerts command
public function handle()
{
    $alerts = Alert::where('status', 'pending')
        ->where('scheduled_at', '<=', now())
        ->get();
    
    foreach ($alerts as $alert) {
        ProcessAlertsJob::dispatch($alert);
    }
}
```

```php
// ProcessAlertsJob
public function handle()
{
    $alert = $this->alert;
    $bot = $alert->bot;
    
    // Marca como processando
    $alert->update(['status' => 'processing']);
    
    // Busca contatos alvo
    $contacts = $this->getTargetContacts($alert);
    
    $telegramService = new TelegramService();
    $sent = 0;
    $failed = 0;
    
    foreach ($contacts as $contact) {
        try {
            $telegramService->sendMessage(
                $bot,
                $contact->telegram_id,
                $alert->message
            );
            $sent++;
            
            // Delay para evitar rate limit
            usleep(100000); // 0.1 segundo
        } catch (\Exception $e) {
            $failed++;
            Log::error("Erro ao enviar alerta", [
                'alert_id' => $alert->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // Atualiza alerta
    $alert->update([
        'status' => 'completed',
        'sent_count' => $sent,
        'failed_count' => $failed
    ]);
}
```

### Filtros de Alvo

```php
protected function getTargetContacts(Alert $alert): Collection
{
    $query = Contact::where('bot_id', $alert->bot_id)
        ->where('is_blocked', false);
    
    switch ($alert->target_type) {
        case 'all':
            // Todos os contatos
            break;
            
        case 'active':
            $query->where('telegram_status', 'active');
            break;
            
        case 'expired':
            $query->where('expires_at', '<', now());
            break;
            
        case 'expiring':
            $filters = $alert->target_filters;
            $days = $filters['days_to_expire'] ?? 3;
            $query->whereBetween('expires_at', [now(), now()->addDays($days)]);
            break;
            
        case 'specific':
            $filters = $alert->target_filters;
            if (isset($filters['contact_ids'])) {
                $query->whereIn('id', $filters['contact_ids']);
            }
            break;
    }
    
    return $query->get();
}
```

---

## Logs e Monitoramento

### Sistema de Logs

A aplicação possui sistema de logs abrangente:

```php
class Log extends Model
{
    protected $fillable = [
        'bot_id',
        'user_id',
        'level',        // debug, info, warning, error, critical
        'message',
        'context',      // JSON com dados adicionais
        'ip_address',
        'user_agent'
    ];
}
```

### Log de Ações do Bot

```php
// TelegramService::logBotAction()
protected function logBotAction(Bot $bot, string $message, string $level = 'info', array $context = []): void
{
    Log::create([
        'bot_id' => $bot->id,
        'user_id' => auth()->id(),
        'level' => $level,
        'message' => $message,
        'context' => json_encode($context)
    ]);
}
```

**Exemplos de logs:**

```php
// Bot inicializado
$this->logBotAction($bot, 'Bot inicializado com sucesso', 'info');

// Mensagem recebida
$this->logBotAction($bot, 'Mensagem recebida', 'info', [
    'chat_id' => $chatId,
    'user_id' => $from['id'],
    'text' => substr($text, 0, 100)
]);

// Erro ao enviar mensagem
$this->logBotAction($bot, 'Erro ao enviar mensagem: ' . $e->getMessage(), 'error', [
    'chat_id' => $chatId,
    'trace' => $e->getTraceAsString()
]);

// Comando executado
$this->logBotAction($bot, "Comando executado: /{$command}", 'info', [
    'chat_id' => $chatId,
    'user_id' => $from['id']
]);
```

### Consulta de Logs

```php
// GET /api/logs
{
  "logs": [
    {
      "id": 1,
      "bot_id": 1,
      "level": "info",
      "message": "Bot inicializado com sucesso",
      "context": {},
      "created_at": "2025-12-03T10:00:00.000000Z"
    },
    {
      "id": 2,
      "bot_id": 1,
      "level": "error",
      "message": "Erro ao enviar mensagem: Connection timeout",
      "context": {
        "chat_id": 123456789,
        "trace": "..."
      },
      "created_at": "2025-12-03T10:05:00.000000Z"
    }
  ]
}
```

### Monitoramento de Webhook

O endpoint `/api/telegram/webhook/{botId}/info` fornece informações sobre o status do webhook:

```json
{
  "success": true,
  "webhook_info": {
    "url": "https://seu-dominio.com/api/telegram/webhook/1",
    "has_custom_certificate": false,
    "pending_update_count": 0,
    "last_error_date": null,
    "last_error_message": null,
    "max_connections": 40,
    "allowed_updates": ["message", "callback_query"]
  }
}
```

**Indicadores de problemas:**

- `pending_update_count > 0`: Há atualizações pendentes não processadas
- `last_error_date`: Data do último erro
- `last_error_message`: Mensagem do último erro

---

## Segurança e Permissões

### Sistema de Permissões

A aplicação possui sistema granular de permissões:

```php
// PermissionService
public function hasBotPermission(User $user, int $botId, string $permission): bool
{
    // Super admin tem acesso total
    if ($user->isSuperAdmin()) {
        return true;
    }
    
    // Verifica se é dono do bot
    $bot = Bot::find($botId);
    if ($bot && $bot->user_id === $user->id) {
        return true;
    }
    
    // Verifica permissões do grupo
    if ($user->userGroup) {
        $hasPermission = UserGroupPermission::where('user_group_id', $user->userGroup->id)
            ->where('resource_type', 'bot')
            ->where('resource_id', (string)$botId)
            ->where('permission', $permission)
            ->exists();
            
        return $hasPermission;
    }
    
    return false;
}
```

### Tipos de Permissões

- **`read`**: Visualizar informações do bot
- **`write`**: Editar configurações do bot
- **`delete`**: Excluir o bot
- **`manage_commands`**: Gerenciar comandos
- **`manage_contacts`**: Gerenciar contatos
- **`manage_group`**: Gerenciar grupo (adicionar/remover membros)

### Middleware de Autenticação

```php
// AuthenticateToken middleware
public function handle(Request $request, Closure $next)
{
    $token = $request->bearerToken();
    
    if (!$token) {
        return response()->json(['error' => 'Token not provided'], 401);
    }
    
    try {
        $payload = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
        $user = User::find($payload->sub);
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 401);
        }
        
        auth()->setUser($user);
        
        return $next($request);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
}
```

### Validação de Webhook

```php
protected function validateWebhookOrigin(Request $request, Bot $bot): bool
{
    // Verifica secret_token se configurado
    $expectedSecretToken = env('TELEGRAM_WEBHOOK_SECRET_TOKEN');
    if ($expectedSecretToken) {
        $receivedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if ($receivedToken !== $expectedSecretToken) {
            return false;
        }
        return true;
    }
    
    // Valida estrutura da atualização
    $update = $request->all();
    $validUpdateFields = [
        'message', 'edited_message', 'channel_post',
        'callback_query', 'inline_query', 'my_chat_member'
    ];
    
    foreach ($validUpdateFields as $field) {
        if (isset($update[$field])) {
            return true;
        }
    }
    
    return false;
}
```

### Rate Limiting

Para evitar sobrecarga da API do Telegram:

```php
// Delay entre envios de mensagens em massa
foreach ($contacts as $contact) {
    $telegramService->sendMessage($bot, $contact->telegram_id, $message);
    usleep(100000); // 0.1 segundo entre mensagens
}

// Retry em caso de timeout
$response = Http::timeout(30)
    ->retry(2, 1000) // 2 tentativas com 1 segundo de delay
    ->post("https://api.telegram.org/bot{$token}/sendMessage", $data);
```

**Limites da API do Telegram:**

- Mensagens: 30 mensagens/segundo
- Grupos: 20 mensagens/minuto por grupo
- Webhooks: 100 requisições/segundo

---

## Boas Práticas

### 1. Configuração de Bots

**✅ Faça:**
- Use webhooks em produção (mais eficiente)
- Configure `secret_token` para validar origem
- Ative apenas os `allowed_updates` necessários
- Mantenha token em variável de ambiente segura

**❌ Evite:**
- Usar polling em produção
- Expor token em código-fonte
- Processar todos os tipos de updates desnecessariamente

### 2. Processamento de Mensagens

**✅ Faça:**
- Use Jobs assíncronos para operações demoradas
- Implemente retry logic para falhas temporárias
- Registre logs detalhados para debug
- Valide e sanitize entrada de usuários

**❌ Evite:**
- Processar webhooks de forma síncrona por muito tempo
- Ignorar erros silenciosamente
- Confiar cegamente em dados de entrada

### 3. Gerenciamento de Grupos

**✅ Faça:**
- Verifique permissões do bot antes de operações
- Configure bot como administrador do grupo
- Sincronize status de membros periodicamente
- Registre todas as ações de gerenciamento

**❌ Evite:**
- Tentar adicionar usuários sem permissões adequadas
- Assumir que bot sempre tem acesso ao grupo
- Não tratar casos de usuários já no grupo

### 4. Segurança

**✅ Faça:**
- Implemente autenticação JWT
- Use HTTPS em produção
- Valide origem de webhooks
- Limite taxa de requisições
- Sanitize entrada de usuários
- Use prepared statements (Eloquent já faz isso)

**❌ Evite:**
- Expor tokens em logs
- Aceitar webhooks sem validação
- Processar comandos sem autenticação
- Permitir SQL injection

### 5. Performance

**✅ Faça:**
- Use cache para dados frequentes
- Processe mensagens em Jobs assíncronos
- Use índices em colunas de busca
- Implemente paginação em listagens
- Use `select()` para buscar apenas campos necessários

**❌ Evite:**
- Processar tudo de forma síncrona
- Fazer N+1 queries
- Carregar relações desnecessárias
- Não paginar resultados grandes

### 6. Monitoramento

**✅ Faça:**
- Registre logs de todas as operações importantes
- Monitore status de webhooks
- Acompanhe taxa de erros
- Configure alertas para problemas críticos
- Verifique `getWebhookInfo` periodicamente

**❌ Evite:**
- Ignorar erros de webhook
- Não monitorar rate limits
- Deixar `pending_update_count` crescer

### 7. Experiência do Usuário

**✅ Faça:**
- Responda rapidamente (< 5 segundos)
- Use mensagens claras e amigáveis
- Forneça feedback de ações
- Use teclados inline para navegação
- Configure comandos no menu do Telegram

**❌ Evite:**
- Deixar usuário sem resposta
- Usar mensagens técnicas para usuários finais
- Enviar mensagens muito longas
- Ignorar comandos desconhecidos

---

## Conclusão

Esta documentação técnica apresentou a arquitetura e implementação completa da integração com a API do Telegram. A aplicação oferece um sistema robusto e escalável para gerenciamento de múltiplos bots com funcionalidades avançadas.

### Recursos Principais

- ✅ Gerenciamento completo de bots
- ✅ Sistema de webhooks e polling
- ✅ Comandos personalizados
- ✅ Gerenciamento de grupos
- ✅ Integração com pagamentos
- ✅ Sistema de alertas
- ✅ Controle de permissões
- ✅ Logs detalhados

### Próximos Passos

Para começar a usar a aplicação:

1. Configure o ambiente (Docker, .env)
2. Crie um bot no [@BotFather](https://t.me/botfather)
3. Configure o bot na aplicação
4. Configure webhook (produção) ou inicie polling (desenvolvimento)
5. Teste comandos e fluxos
6. Configure pagamentos (opcional)
7. Configure alertas (opcional)

### Suporte e Documentação Adicional

- **Telegram Bot API**: https://core.telegram.org/bots/api
- **Laravel Documentation**: https://laravel.com/docs
- **MercadoPago API**: https://www.mercadopago.com.br/developers
- **Stripe API**: https://stripe.com/docs/api

---

**Versão:** 1.0.0  
**Data:** Dezembro 2025  
**Autor:** Sistema de Gerenciamento de Bots Telegram
