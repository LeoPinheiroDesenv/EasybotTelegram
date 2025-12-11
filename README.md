# Bot Telegram - Sistema de Gerenciamento de Usuários

Sistema completo de gerenciamento de usuários com autenticação e níveis de acesso.

## 🚀 Tecnologias

- **Frontend**: React 18 (Create React App) com Context API, Chart.js e Axios
- **Backend**: Laravel 12 em PHP 8.2 com serviços e jobs dedicados
- **Banco de Dados**: MySQL 8 (serviço `mysql` definido em `docker-compose.yml`)
- **Filas & Tarefas**: Jobs Laravel (`ProcessTelegramUpdate`, `ProcessAlertsJob`, `SendDownsell`) executados via Artisan
- **Integrações**: Telegram Bot API, Mercado Pago, Stripe e PIX (via `PixCrcService`)
- **Containerização**: Docker & Docker Compose

## 🧠 Visão Geral do Funcionamento

O sistema oferece um painel administrativo para orquestrar bots do Telegram, contatos e cobranças. O frontend React (`frontend/src`) consome a API Laravel (`backend/app`) por meio de tokens JWT emitidos por `AuthController`. Todo o tráfego passa por middlewares como `AuthenticateToken`, `AdminMiddleware` e `CheckPermission`, garantindo autorização granular antes de alcançar os controladores setoriais.

### Fluxo de alto nível
1. **Autenticação e sessão**: o usuário acessa o frontend, realiza login e recebe um JWT que fica armazenado no `AuthContext`. Requisições subsequentes incluem o token via `frontend/src/services/api.js`.
2. **Orquestração de bots**: dados de bots são carregados por `botService`, e a tela de gerenciamento (`ManageBot`) libera abas de configurações, mensagens, planos e integrações do Telegram.
3. **Cobranças e métricas**: `billingService` busca estatísticas consolidadas que alimentam o dashboard financeiro e as páginas de planos/pagamentos.
4. **Automações**: comandos Artisan (`ProcessScheduledAlerts`, `TelegramPollingCommand`, `GenerateCrcDiagnosticReport`) alimentam filas que disparam jobs (`SendAlert`, `SendDownsell`) e serviços (`TelegramService`, `NotificationService`).

### Backend (Laravel 12)
- Controladores REST em `backend/app/Http/Controllers` delegam regras para serviços especializados em `backend/app/Services`, aplicando o princípio **Single Responsibility (SRP)** do SOLID.
- `PaymentService`, `BillingService` e `PaymentGatewayConfigController` conectam-se a Mercado Pago, Stripe e PIX; `PixCrcService` valida QR Codes e logs são centralizados via `DatabaseLogHandler`.
- Middleware de segurança (`AdminMiddleware`, `SuperAdminOnly`, `GroupManagementPermission`) protege rotas, enquanto `TwoFactorService` habilita MFA.
- Fila padrão do Laravel processa integrações do Telegram e disparos de downsell/alertas, garantindo resiliência quando há alta demanda.

### Frontend (React 18)
- Layout unificado em `frontend/src/components/Layout.js` organiza páginas em `frontend/src/pages`, cada uma conectada ao backend via serviços dedicados (`frontend/src/services/*`).
- `ManageBotContext` e `useAlert` concentram estado e feedbacks; `PrivateRoute` e `ProtectedRoute` controlam o acesso baseado em autenticação.
- Componentes de UI (cards, botões, tabelas) e gráficos (`react-chartjs-2`) fornecem experiência responsiva e orientada a métricas.

### Processos assíncronos e integrações
- **Telegram**: `TelegramService`, `TelegramWebhookController` e comandos `TelegramPollingCommand`/`ProcessTelegramUpdate` sincronizam bots, grupos e webhooks.
- **Cobrança**: `PaymentStatusService`, `PaymentGatewayConfig` e `TransactionObserver` acompanham o ciclo de vida de pagamentos e atualizam dashboards em tempo quase real.
- **Alertas/Diagnósticos**: `ProcessScheduledAlerts`, `PixDiagnosticController` e `GenerateCrcDiagnosticReport` monitoram critério de sucesso dos disparos PIX e notificações transacionais.

## 📋 Pré-requisitos

- Docker e Docker Compose instalados
- Git (opcional)

## 🛠️ Instalação e Execução

### 1. Clone o repositório (se aplicável)

```bash
cd /var/www/html/botTelegram
```

### 2. Configure as variáveis de ambiente

Copie o arquivo `.env.example` para `.env` e ajuste as variáveis conforme necessário:

```bash
cp .env.example .env
```

Edite o arquivo `.env` se precisar alterar as configurações padrão.

### 3. Inicie os containers com Docker Compose

```bash
docker-compose up -d
```

Este comando irá:
- Criar e iniciar o banco de dados MySQL
- Criar e iniciar o servidor backend
- Criar e iniciar o frontend React
- Executar as migrações do banco de dados
- Criar o usuário administrador padrão

### 4. Acesse a aplicação

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:5000/api
- **Health Check**: http://localhost:5000/api/health

## 👤 Credenciais Padrão

- **Email**: admin@admin.com
- **Senha**: admin123
- **Nível de Acesso**: Administrador

## 📁 Estrutura do Projeto

```
botTelegram/
├── backend/
│   ├── config/
│   │   └── database.js
│   ├── controllers/
│   │   ├── authController.js
│   │   └── userController.js
│   ├── middleware/
│   │   └── auth.js
│   ├── migrations/
│   │   ├── createTables.sql
│   │   ├── createDefaultAdmin.js
│   │   └── runMigrations.js
│   ├── routes/
│   │   ├── auth.js
│   │   └── users.js
│   ├── Dockerfile
│   ├── package.json
│   ├── server.js
│   └── .env
├── frontend/
│   ├── public/
│   ├── src/
│   │   ├── components/
│   │   ├── contexts/
│   │   ├── pages/
│   │   ├── services/
│   │   ├── App.js
│   │   └── index.js
│   ├── Dockerfile
│   └── package.json
├── docker-compose.yml
├── .env.example
├── .gitignore
└── README.md
```

## ✨ Principais Recursos

### Dashboard financeiro e insights em tempo real
- Consolidação de métricas de receita, assinaturas e transações através de `billingService.getDashboardStatistics`, exibindo gráficos (barras, pizza, donut) em `Dashboard.js`.
- Visão por método de pagamento, gateway e bot, com alertas de ausência de dados e ações rápidas como "Criar Bot" ou "Atualizar" diretamente na interface.

### Gestão completa de bots Telegram
- CRUD de bots, validação de tokens, upload de mídia e controle de webhooks expostos por `BotController`/`botService`.
- Tela de gerenciamento (`ManageBot`) com abas para Configurações, Mensagens Iniciais, Planos de Pagamento, Botões de Redirecionamento, Comandos, Administradores, Grupos/Canais e BotFather, permitindo acompanhar a jornada do usuário sem sair do fluxo.

### Planos, ciclos e meios de pagamento flexíveis
- `PaymentPlanController`, `PaymentCycleController` e `PaymentGatewayConfigController` administram assinaturas, recorrência, gateways e credenciais.
- `PaymentStatusController` e `BillingController` fornecem relatórios, exportações e histórico de transações para conciliações financeiras.

### Contatos, grupos e segmentação
- `ContactController`, `GroupManagementController` e `TelegramGroupController` mantêm contatos sincronizados ao Telegram, possibilitando segmentações por grupos e botões de downsell.
- `UserGroupController` e `UserGroupPermission` viabilizam perfis de acesso específicos por módulo, alinhados ao princípio **Interface Segregation**.

### Automação, alertas e jornadas de downsell
- `AlertController`, `DownsellController` e jobs `ProcessAlertsJob`/`SendDownsell` coordenam campanhas (alertas, mensagens pós-compra, fluxos de recuperação).
- Observadores como `TransactionObserver` disparam eventos após cada pagamento, atualizando estatísticas e filas.

### Governança, segurança e auditoria
- Autenticação JWT com refresh tokens, MFA via `TwoFactorService`, redefinição de senha e monitoramento de login por `AuthController`.
- `LogController`, `DatabaseLogHandler` e `logs/` fornecem histórico de ações administrativas e integração com observabilidade externa.

### Infraestrutura pronta para DevOps
- Docker Compose sobe `mysql`, `backend` e `frontend` com healthcheck, hot reload (volumes) e secrets via `.env`.
- Scripts Composer (`composer setup`, `composer dev`) realizam provisioning completo: dependências PHP, geração de chave, migrações e build front.

## 🔐 API Endpoints

### Autenticação

- `POST /api/auth/login` - Login de usuário
- `GET /api/auth/me` - Obter usuário atual (requer autenticação)

### Usuários (requer autenticação e nível admin)

- `GET /api/users` - Listar todos os usuários
- `GET /api/users/:id` - Obter usuário por ID
- `POST /api/users` - Criar novo usuário
- `PUT /api/users/:id` - Atualizar usuário
- `DELETE /api/users/:id` - Excluir usuário

## 🔒 Níveis de Acesso

- **admin**: Administrador com acesso completo ao sistema
- **user**: Usuário padrão (sem acesso ao gerenciamento de usuários)

## 🐳 Comandos Docker

### Parar os containers

```bash
docker-compose down
```

### Ver logs

```bash
docker-compose logs -f
```

### Reconstruir os containers

```bash
docker-compose up -d --build
```

### Executar migrações manualmente

```bash
docker-compose exec backend php artisan migrate --force
```

### Criar usuário admin padrão manualmente

```bash
docker-compose exec backend php artisan db:seed --class=CreateAdminUserSeeder
```

## 🛠️ Desenvolvimento

### Executar sem Docker

#### Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

#### Frontend

```bash
cd frontend
npm install
npm start
```

### Variáveis de Ambiente

Certifique-se de configurar as seguintes variáveis:

- `DB_HOST`: Host do MySQL
- `DB_PORT`: Porta do MySQL
- `DB_USER`: Usuário do banco de dados
- `DB_PASSWORD`: Senha do banco de dados
- `DB_NAME`: Nome do banco de dados
- `JWT_SECRET`: Chave secreta para JWT (use uma chave forte em produção)
- `PORT`: Porta do servidor backend
- `REACT_APP_API_URL`: URL da API para o frontend

## 📝 Notas

- O sistema utiliza JWT para autenticação
- As senhas são criptografadas usando bcrypt
- O banco de dados MySQL é persistido em um volume Docker
- Em produção, certifique-se de alterar a `JWT_SECRET` e outras credenciais padrão

## 🐛 Troubleshooting

### Erro de conexão com o banco de dados

Verifique se o MySQL está rodando e as credenciais estão corretas.

### Erro de permissão no Docker

Certifique-se de que o Docker tem permissões adequadas para acessar o diretório do projeto.

### Frontend não conecta ao backend

Verifique se a variável `REACT_APP_API_URL` está configurada corretamente e se ambos os serviços estão rodando.

## 📄 Licença

Este projeto é de código aberto e está disponível sob a licença MIT.

