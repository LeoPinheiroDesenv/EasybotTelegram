# Documentação Técnica — Integração com a Telegram Bot API

Este documento descreve, em nível técnico, como a plataforma integra e
orquestra bots do Telegram para cadastro, cobrança e gestão de grupos. O
foco está no backend Laravel localizado em `backend/`, responsável por
validar tokens, consumir a Telegram Bot API, armazenar contatos,
processar comandos e automatizar entrada/saída de membros de acordo com
regras de negócio.

---

## 1. Arquitetura de Alto Nível

- **Frontend (React)**: provê interface administrativa para operadores
  criarem bots, comandos, campanhas e acompanhar métricas.
- **Backend (Laravel 10+)**: expõe REST APIs em `/api/*`, autentica com
  JWT, grava dados relacionais (MySQL/PostgreSQL) e centraliza toda
  integração com o Telegram via `App\Services\TelegramService`.
- **Processamento Assíncrono**: jobs e filas (`database` driver) tratam
  atualizações de webhook/polling via `ProcessTelegramUpdate`,
  garantindo resiliência quando a resposta síncrona falha.
- **Telegram Bot API**: consumida via HTTPS direto para
  `https://api.telegram.org/bot<TOKEN>/`. O sistema suporta webhook (via
  endpoint público) ou polling (`php artisan telegram:polling`).

A separação clara entre controladores HTTP, serviços de domínio e
integrações externas segue o princípio **Single Responsibility (S de
SOLID)**, permitindo testar regras de negócio independentemente da
camada de transporte.

---

## 2. Componentes Relacionados ao Telegram

| Camada | Responsável | Descrição |
| --- | --- | --- |
| Controllers | `BotController`, `TelegramWebhookController`, `BotCommandController`, `GroupManagementController` | Exposição REST (CRUD de bots, comandos, webhooks, grupos). Validam permissões via `PermissionService`. |
| Services | `TelegramService` | Encapsula todas as chamadas à Telegram Bot API: validação de token (`getMe`), configuração de webhook, `getChatMember`, envio de mensagens, processamento de comandos, sincronização de grupos etc. |
| Jobs & Commands | `ProcessTelegramUpdate`, `TelegramPollingCommand`, `UpdateContactsTelegramStatus`, `ProcessScheduledAlerts` | Automatizam consumo de updates, execução periódica de alertas e saneamento de contatos. |
| Models | `Bot`, `BotCommand`, `TelegramGroup`, `Contact`, `Transaction`, `UserGroupPermission` | Persistem estado da integração (token, grupo, mídia, comandos registrados e permissões por operador). |
| Middleware | `AuthenticateToken`, `CheckPermission`, `SuperAdminOnly`, `HandleCors` | Protegem APIs sensíveis. |

---

## 3. Fluxo de Provisionamento do Bot

1. **Cadastro do Bot (`POST /api/bots`)**
   - Admin envia `name`, `token`, `telegram_group_id` e flags de
     onboarding.
   - `BotController@store` chama `TelegramService::validateToken` (usa
     `getMe`) e recusa tokens inválidos.
   - Opcional: `activate=true` dispara inicialização imediata.

2. **Validação de Token e Grupo**
   - `POST /api/bots/validate` e
     `POST /api/bots/validate-token-and-group` expõem validações de
     token e `chat_id` usando `getChat`.
   - O serviço verifica permissões críticas (`can_read_all_group_messages`
     e `can_join_groups`) e retorna _warnings_ operacionais.

3. **Ativação / Desativação**
   - `POST /api/bots/{id}/initialize`: confirma token, registra comandos
     ativos via `setMyCommands` e marca `activated=true`.
   - `POST /api/bots/{id}/stop`: apenas desativa sem alterar webhook.

4. **Configuração do Webhook**
   - `POST /api/telegram/webhook/{botId}/set` monta URL baseada em
     `TELEGRAM_WEBHOOK_URL` ou `APP_URL`, força HTTPS, valida portas
     permitidas (443, 80, 88, 8443) e aceita `secret_token`.
   - `GET /api/telegram/webhook/{botId}/info` consulta
     `getWebhookInfo`; `POST /api/telegram/webhook/{botId}/delete`
     remove o hook.
   - O endpoint público `POST /api/telegram/webhook/{botId}` consome
     updates e, por segurança, compara `X-Telegram-Bot-Api-Secret-Token`
     com `TELEGRAM_WEBHOOK_SECRET_TOKEN` quando configurado.

5. **Modo Polling (opcional)**
   - `php artisan telegram:polling --bot-id=ID --timeout=30 --limit=100`
     usa `getUpdates` em loop e delega cada update para
     `TelegramService::processUpdate`.

---

## 4. Processamento de Updates

### 4.1 Pipeline Geral
1. Webhook ou polling entrega o _payload_ bruto.
2. `TelegramService::processUpdate` identifica o tipo (message,
   edited_message, channel_post, callback_query, inline_query).
3. Mensagens são roteadas para `processMessage`, que:
   - Persiste/atualiza `Contact` (com Telegram ID, username, idioma,
     telefone compartilhado).
   - Detecta comandos via `entities` ou fallback textual.
   - Diferencia chats privados vs. grupos (`chat.type`).
4. Comandos mapeiam para handlers (`/start`, `/help`, `/plans` etc.)
   responsáveis por enviar mensagens formatadas, teclados e mídia.
5. `processCallbackQuery` trata botões inline (ex.: seleção de planos).
6. `GroupManagementService` atualiza estado de membros conforme regras de
   pagamento e ações manuais.

### 4.2 Contato e Sincronização
- `saveOrUpdateContact` mantém consistência de dados pessoais.
- `processSharedContact` armazena telefone quando o usuário utiliza
  botões `request_contact`.
- `ContactController::syncGroupMembers` e
  `GroupManagementService::syncGroupMembers` chamam `getChatMember` para
  validar status de cada usuário.

### 4.3 Tratamento de Comandos
- Comandos são cadastrados em `/api/bots/{botId}/commands`.
- `BotCommandController` sincroniza com o Telegram via
  `TelegramService::registerBotCommands`.
- Handlers padrão:
  - `/start`: envia `initial_message`, mídia opcional (`media_1_url`…),
    teclados customizados e CTA configurable (`activate_cta`).
  - `/help`: retorna instruções e principais comandos ativos.
  - `/plans`: renderiza inline keyboard com planos de pagamento,
    preparando o funil de cobrança.
- `handlePlanSelection` e `handlePaymentMethod` integram com
  `PaymentService`, geram transações e notificam o usuário em tempo real.

### 4.4 Mensageria e Mídia
- `sendMessage` centraliza `sendMessage`, adicionando `parse_mode`,
  keyboards e logs.
- `sendMessageWithKeyboard` e `removeKeyboard` controlam teclados
  persistentes (`reply_markup`).
- `sendMedia` suporta até três URLs pré-configuradas, enviadas via
  `sendPhoto`/`sendVideo`.
- `sendDocument` disponibiliza arquivos (contratos, tutoriais etc.).

---

## 5. Gestão de Grupos via Telegram API

| Ação | Método na plataforma | Chamada Telegram |
| --- | --- | --- |
| Validar grupo | `validateTokenAndGroup` | `getChat` |
| Verificar membro | `GroupManagementService::checkMemberStatus` | `getChatMember` |
| Adicionar membro | Após pagamento (`TransactionObserver`) ou manual (`POST /bots/{botId}/group/add-member`) | `addChatMember`* |
| Remover membro | Pagamento expirado ou manual | `kickChatMember` (versões recentes usam `banChatMember`/`unbanChatMember`) |
| Recuperar link de convite | `TelegramService::getChatInviteLink` | `exportChatInviteLink` |
| Estatísticas | `GroupStatisticsService` | Combinação de `getChatMember` + dados locais |

\* A API oficial substituiu `addChatMember` por `inviteLink` +
`approveChatJoinRequest`; o serviço abstrai essas nuances e normaliza
IDs de chat com `normalizeChatId`.

Pagamentos aprovados disparam `GroupManagementService::addMemberAfterPayment`;
cancelamentos/expirações chamam `removeMemberAfterPaymentExpiry`.
Ambos registram logs (`Log` model) e notificações via
`NotificationService`.

---

## 6. Endpoints REST Relevantes

- `POST /api/bots`, `GET /api/bots/{id}`, `PUT /api/bots/{id}`: CRUD.
- `POST /api/bots/{id}/initialize`, `/stop`, `/status`.
- `POST /api/telegram/webhook/{botId}/set|delete`, `GET /info`.
- `POST /api/bots/{botId}/commands` + `/register`, `/telegram`.
- `POST /api/bots/{botId}/group/add-member`, `/remove-member`,
  `/group/info`, `/group/statistics`, `/group/contact-history`.
- `POST /api/contacts/sync-group-members` e `/contacts/update-all-status`.

Todos exigem JWT (`Authorization: Bearer`) e, em alguns casos,
permissões avançadas (`super_admin`, `hasBotPermission`).

---

## 7. Configuração e Variáveis de Ambiente

| Variável | Uso | Observações |
| --- | --- | --- |
| `TELEGRAM_API_TIMEOUT` | Timeout (segundos) para chamadas HTTP | Default 30s com 2 tentativas (`retry`). |
| `TELEGRAM_WEBHOOK_URL` | Base HTTPS usada ao gerar webhook | Obrigatório apontar para domínio público válido. |
| `TELEGRAM_WEBHOOK_SECRET_TOKEN` | Validação do header `X-Telegram-Bot-Api-Secret-Token` | Recomenda-se string aleatória >= 32 chars. |
| `QUEUE_CONNECTION` | Default `database` | Necessário rodar `php artisan queue:work --queue=telegram-updates`. |
| `ALERTS_PROCESS_SECRET_TOKEN` | Protege `/api/alerts/process-auto` | Integradores externos devem enviar header/token correspondente. |

Além disso, as permissões `can_join_groups` e
`can_read_all_group_messages` devem ser habilitadas no BotFather via
`/setjoingroups` e `/setprivacy` respectivamente, caso contrário os
comandos em grupo não chegam ao backend.

---

## 8. Operação e Monitoramento

- **Logs estruturados**: `TelegramService::logBotAction` grava detalhes
  (bot, chat, comando, contexto) na tabela `logs`.
- **Health Check**: `GET /api/health` retorna `status=OK`.
- **Fila dedicada**: jobs em `telegram-updates` devem ter worker
  exclusivo para evitar latência ao processar callbacks de pagamento.
- **Alertas agendados**: `ProcessScheduledAlerts` dispara mensagens
  proativas (via Telegram) com base em `Alert` e `ContactAction`.
- **Fallback assíncrono**: se `TelegramWebhookController` falhar ao
  processar update em tempo real, o payload é enfileirado, garantindo
  idempotência e tolerância a falhas temporárias.

---

## 9. Testes e Troubleshooting

1. **Validar Token**: `curl -X POST /api/bots/validate -d '{"token":"..."}'`.
2. **Webhook HTTPS**: utilize `openssl s_client -connect domínio:443` ou
   `curl -v https://domínio/api/telegram/webhook/{botId}` para checar TLS.
3. **Fila**: `php artisan queue:failed` lista jobs com exceção; reprocessar
   via `php artisan queue:retry`.
4. **Permissões do Bot**: use `GET /api/bots/{id}/status` para ver
   _warnings_ de `can_read_all_group_messages`.
5. **Comandos**: `GET /api/bots/{id}/commands/telegram` confirma o que
   foi registrado no Telegram.

---

## 10. Boas Práticas e Extensões

- Centralize novas integrações da Telegram Bot API em
  `TelegramService`, mantendo o serviço coerente (SRP) e permitindo
  _mocking_ em testes.
- Para novos fluxos de automação, use Jobs dedicados e configure filas
  separadas se o SLA exigir (aplicando **Open/Closed**: estenda serviços
  via novos métodos sem modificar os existentes).
- Utilize `PermissionService` ao expor qualquer ação que manipule bots
  ou grupos para manter _least privilege_.
- Sempre sanitize `chat_id` com `normalizeChatId` quando receber dados
  de fontes externas (front ou automações).

---

Com esta visão técnica, desenvolvedores conseguem evoluir a integração
com o Telegram Bot API mantendo consistência com os fluxos atuais de
cadastro, cobrança e gestão de membros.
