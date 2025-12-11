# Bot Telegram - Sistema de Gerenciamento de Bots

Sistema completo de gerenciamento e automação de bots do Telegram com funcionalidades avançadas de pagamento, marketing, gerenciamento de usuários e integração com gateways de pagamento.

## 🚀 Tecnologias

- **Frontend**: React.js com React Router
- **Backend**: Laravel (PHP)
- **Banco de Dados**: MySQL 8.0
- **Containerização**: Docker & Docker Compose
- **Pagamentos**: Integração com Stripe e Mercado Pago
- **Integração**: API do Telegram Bot

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
- Criar e iniciar o servidor backend Laravel
- Criar e iniciar o frontend React
- Executar as migrações do banco de dados
- Criar o usuário administrador padrão

### 4. Acesse a aplicação

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000/api
- **Health Check**: http://localhost:8000/api/health

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

## 🔐 API Endpoints Principais

### Autenticação

- `POST /api/auth/login` - Login de usuário
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Obter usuário atual
- `POST /api/auth/forgot-password` - Solicitar recuperação de senha
- `POST /api/auth/reset-password` - Redefinir senha

### Bots

- `GET /api/bots` - Listar todos os bots
- `GET /api/bots/:id` - Obter bot por ID
- `POST /api/bots` - Criar novo bot
- `PUT /api/bots/:id` - Atualizar bot
- `DELETE /api/bots/:id` - Excluir bot
- `GET /api/bots/:id/stats` - Estatísticas do bot

### Comandos de Bot

- `GET /api/bots/:botId/commands` - Listar comandos do bot
- `POST /api/bots/:botId/commands` - Criar comando
- `PUT /api/bot-commands/:id` - Atualizar comando
- `DELETE /api/bot-commands/:id` - Excluir comando

### Pagamentos

- `POST /api/payments/pix` - Processar pagamento PIX
- `POST /api/payments/credit-card` - Processar pagamento com cartão
- `GET /api/payment/transaction/:token` - Obter transação por token
- `GET /api/payment-status/contact/:contactId` - Status de pagamento do contato
- `POST /api/payment/card/create-intent` - Criar intent de pagamento Stripe
- `POST /api/payment/card/confirm` - Confirmar pagamento

### Planos de Pagamento

- `GET /api/payment-plans` - Listar planos
- `POST /api/payment-plans` - Criar plano
- `PUT /api/payment-plans/:id` - Atualizar plano
- `DELETE /api/payment-plans/:id` - Excluir plano

### Contatos

- `GET /api/contacts` - Listar contatos
- `GET /api/contacts/:id` - Obter contato por ID
- `GET /api/contacts/:id/actions` - Histórico de ações do contato

### Alertas

- `GET /api/alerts` - Listar alertas
- `POST /api/alerts` - Criar alerta
- `PUT /api/alerts/:id` - Atualizar alerta
- `DELETE /api/alerts/:id` - Excluir alerta

### Usuários (requer autenticação e nível admin)

- `GET /api/users` - Listar todos os usuários
- `GET /api/users/:id` - Obter usuário por ID
- `POST /api/users` - Criar novo usuário
- `PUT /api/users/:id` - Atualizar usuário
- `DELETE /api/users/:id` - Excluir usuário

### Logs

- `GET /api/logs` - Listar logs do sistema
- `GET /api/logs/:id` - Obter log por ID

### Outros Endpoints

- `GET /api/dashboard/stats` - Estatísticas do dashboard
- `GET /api/billing/stats` - Estatísticas de faturamento
- `POST /api/artisan/run` - Executar comando Artisan
- `GET /api/ftp/*` - Endpoints de gerenciamento FTP

## 🔒 Níveis de Acesso

- **super_admin**: Super administrador com acesso completo ao sistema
- **admin**: Administrador com acesso à maioria das funcionalidades
- **user**: Usuário padrão com acesso limitado

## 🎯 Funcionalidades e Recursos

### 🤖 Gerenciamento de Bots

O sistema permite criar e gerenciar múltiplos bots do Telegram de forma centralizada:

- **Criação de Bots**: Configure novos bots com token do BotFather
- **Listagem de Bots**: Visualize todos os bots cadastrados com status e informações
- **Edição de Configurações**: Atualize configurações de cada bot individualmente
- **Gerenciamento Centralizado**: Interface unificada para gerenciar múltiplos bots

### 💬 Mensagens e Comandos

- **Mensagem de Boas-vindas**: Configure mensagens personalizadas de boas-vindas para novos usuários
- **Comandos Personalizados**: Crie e gerencie comandos customizados para seus bots
- **Bot Commands**: Sistema completo de comandos com respostas configuráveis
- **Bot Administrators**: Gerencie administradores específicos para cada bot

### 💳 Sistema de Pagamentos

Sistema completo de processamento de pagamentos com múltiplos gateways:

#### Pagamentos PIX
- Geração automática de QR Code PIX
- Validação de código PIX com CRC
- Rastreamento de status de pagamento
- Notificações automáticas de confirmação

#### Pagamentos com Cartão de Crédito
- Integração com **Stripe** para pagamentos internacionais
- Integração com **Mercado Pago** para pagamentos nacionais
- Processamento seguro de cartões
- Página pública de pagamento com token único

#### Planos de Pagamento
- Criação de planos de assinatura recorrente
- Configuração de ciclos de pagamento (mensal, trimestral, anual, etc.)
- Gerenciamento de valores e períodos
- Ativação/desativação de planos

#### Status de Pagamento
- Acompanhamento em tempo real do status de pagamentos
- Histórico completo de transações
- Filtros por bot, contato e período
- Relatórios detalhados de faturamento

### 📊 Dashboard e Estatísticas

- **Dashboard Principal**: Visão geral com métricas importantes
- **Gráficos Interativos**: Visualização de dados com Chart.js
- **Estatísticas de Bots**: Número de bots ativos, contatos, pagamentos
- **Estatísticas de Faturamento**: Receita por período, planos mais vendidos
- **Estatísticas de Assinantes**: Crescimento de base de usuários

### 📈 Marketing e Automação

#### Alertas Programados
- Criação de alertas automáticos para contatos
- Agendamento de mensagens por data/hora
- Filtros avançados por grupo, status de pagamento, etc.
- Processamento em background via Jobs

#### Downsell
- Configuração de ofertas de downsell
- Sequência automática de mensagens
- Integração com sistema de pagamento
- Acompanhamento de conversões

#### Grupos do Telegram
- Gerenciamento de grupos associados aos bots
- Controle de permissões por grupo
- Estatísticas por grupo
- Integração com sistema de alertas

### 👥 Gerenciamento de Usuários e Contatos

#### Usuários do Sistema
- CRUD completo de usuários administrativos
- Níveis de permissão granulares
- Perfis de usuário com configurações
- Autenticação com JWT e recuperação de senha

#### Contatos (Usuários dos Bots)
- Listagem completa de contatos de todos os bots
- Detalhes individuais de cada contato
- Histórico de interações e ações
- Status de pagamento por contato
- Filtros e buscas avançadas

#### Grupos de Usuários
- Criação de grupos de usuários
- Permissões customizadas por grupo
- Gerenciamento de membros
- Controle de acesso baseado em grupos

### 🔐 Segurança e Configurações

#### Configurações de Segurança
- Autenticação de dois fatores (2FA)
- Configurações de sessão
- Políticas de senha
- Logs de auditoria

#### Configurações de Armazenamento
- Configuração de drivers de armazenamento
- Integração com serviços de nuvem
- Gerenciamento de arquivos

#### Gerenciamento FTP
- Interface para gerenciamento de arquivos via FTP
- Upload e download de arquivos
- Navegação de diretórios

### 📝 Logs e Auditoria

- **Logs do Sistema**: Registro completo de ações do sistema
- **Logs de Requisições HTTP**: Rastreamento de todas as requisições API
- **Logs de Transações**: Histórico detalhado de pagamentos
- **Filtros e Buscas**: Pesquisa avançada em logs
- **Exportação**: Exportação de logs para análise

### 🔧 Ferramentas Administrativas

#### Comandos Artisan
- Interface web para executar comandos Laravel Artisan
- Comandos personalizados do sistema:
  - `ProcessScheduledAlerts`: Processa alertas agendados
  - `TelegramPollingCommand`: Polling de atualizações do Telegram
  - `UpdateContactsTelegramStatus`: Atualiza status de contatos
  - `GenerateCrcDiagnosticReport`: Relatórios de diagnóstico PIX

#### BotFather Management
- Integração com BotFather do Telegram
- Configuração de comandos globais
- Gerenciamento de descrições e sobre

### 🔄 Integração com Telegram

- **Webhooks**: Recebimento de atualizações via webhook
- **Polling**: Alternativa de polling para atualizações
- **Processamento Assíncrono**: Jobs em background para processar mensagens
- **Telegram Service**: Camada de abstração para comunicação com API do Telegram

### 💰 Faturamento e Relatórios

- **Página de Billing**: Visão consolidada de faturamento
- **Relatórios por Período**: Análise de receita por período
- **Estatísticas de Planos**: Performance de cada plano de pagamento
- **Gráficos de Receita**: Visualização de tendências de faturamento

## 🏗️ Arquitetura do Sistema

### Frontend (React.js)

O frontend é construído com React e utiliza uma arquitetura baseada em componentes:

- **Pages**: Páginas principais da aplicação
- **Components**: Componentes reutilizáveis (Layout, Sidebar, Header, etc.)
- **Services**: Camada de comunicação com a API
- **Contexts**: Gerenciamento de estado global (AuthContext, ManageBotContext)
- **Hooks**: Hooks customizados (useAlert, useConfirm)
- **UI Components**: Componentes de interface (Button, Card, TextInput)

### Backend (Laravel)

O backend segue os princípios SOLID e padrões de arquitetura MVC:

- **Controllers**: Controladores RESTful para cada recurso
- **Models**: Modelos Eloquent com relacionamentos
- **Services**: Lógica de negócio isolada em serviços
- **Jobs**: Processamento assíncrono de tarefas
- **Middleware**: Autenticação, CORS, permissões, logging
- **Observers**: Eventos de modelo (ex: TransactionObserver)
- **Commands**: Comandos Artisan personalizados

### Fluxo de Funcionamento

1. **Autenticação**: Usuário faz login e recebe token JWT
2. **Navegação**: Frontend utiliza React Router para navegação
3. **Requisições API**: Services fazem chamadas HTTP para endpoints Laravel
4. **Middleware**: Requisições passam por autenticação e validação
5. **Controllers**: Processam requisições e delegam para Services
6. **Services**: Executam lógica de negócio e interagem com Models
7. **Database**: Models fazem queries no MySQL
8. **Resposta**: JSON retornado para o frontend
9. **Jobs**: Tarefas assíncronas processadas em background (alertas, mensagens Telegram)

### Processamento de Pagamentos

1. **Solicitação**: Cliente solicita pagamento via bot
2. **Geração**: Sistema gera transação e token único
3. **Gateway**: Redirecionamento para gateway (Stripe/Mercado Pago) ou QR Code PIX
4. **Webhook**: Gateway notifica sistema sobre status
5. **Atualização**: Sistema atualiza status da transação
6. **Notificação**: Bot notifica usuário sobre confirmação
7. **Acesso**: Usuário recebe acesso ao conteúdo

### Sistema de Alertas

1. **Criação**: Administrador cria alerta com filtros e agendamento
2. **Armazenamento**: Alerta salvo no banco de dados
3. **Job Agendado**: Comando Artisan processa alertas pendentes
4. **Filtragem**: Sistema filtra contatos conforme critérios
5. **Envio**: Mensagens enviadas via Telegram Service
6. **Registro**: Ações registradas em logs

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
docker-compose exec backend php artisan migrate
```

### Executar comandos Artisan

```bash
docker-compose exec backend php artisan [comando]
```

### Acessar shell do Laravel (Tinker)

```bash
docker-compose exec backend php artisan tinker
```

### Limpar cache

```bash
docker-compose exec backend php artisan cache:clear
docker-compose exec backend php artisan config:clear
docker-compose exec backend php artisan route:clear
```

## 🛠️ Desenvolvimento

### Executar sem Docker

#### Backend

```bash
cd backend
composer install
php artisan serve
```

#### Frontend

```bash
cd frontend
npm install
npm start
```

### Variáveis de Ambiente

Certifique-se de configurar as seguintes variáveis no arquivo `.env`:

#### Banco de Dados
- `DB_HOST`: Host do MySQL (padrão: mysql)
- `DB_PORT`: Porta do MySQL (padrão: 3306)
- `DB_USER`: Usuário do banco de dados
- `DB_PASSWORD`: Senha do banco de dados
- `DB_NAME`: Nome do banco de dados
- `DB_ROOT_PASSWORD`: Senha root do MySQL

#### Aplicação
- `APP_ENV`: Ambiente da aplicação (local, production)
- `APP_DEBUG`: Modo debug (true/false)
- `JWT_SECRET`: Chave secreta para JWT (use uma chave forte em produção)

#### Pagamentos - Mercado Pago
- `MERCADOPAGO_ACCESS_TOKEN`: Token de acesso do Mercado Pago
- `MERCADOPAGO_WEBHOOK_URL`: URL do webhook do Mercado Pago

#### Pagamentos - Stripe
- `STRIPE_SECRET_KEY`: Chave secreta do Stripe
- `STRIPE_PUBLIC_KEY`: Chave pública do Stripe
- `STRIPE_WEBHOOK_SECRET`: Secret do webhook do Stripe

#### Frontend
- `REACT_APP_API_URL`: URL da API para o frontend (ex: http://localhost:8000/api)

## 📝 Notas Importantes

- O sistema utiliza JWT para autenticação de API
- As senhas são criptografadas usando bcrypt (Laravel Hash)
- O banco de dados MySQL é persistido em um volume Docker (`mysql_data`)
- Em produção, certifique-se de:
  - Alterar a `JWT_SECRET` para uma chave forte e única
  - Configurar credenciais seguras de banco de dados
  - Configurar tokens de pagamento (Stripe e Mercado Pago)
  - Desabilitar `APP_DEBUG` (definir como `false`)
  - Configurar `APP_ENV` como `production`
  - Configurar webhooks dos gateways de pagamento
- O sistema processa mensagens do Telegram de forma assíncrona via Jobs
- Alertas agendados são processados pelo comando `ProcessScheduledAlerts`
- O sistema suporta múltiplos bots simultaneamente

## 🐛 Troubleshooting

### Erro de conexão com o banco de dados

Verifique se o MySQL está rodando e as credenciais estão corretas. Execute:
```bash
docker-compose logs mysql
```

### Erro ao processar pagamentos

Certifique-se de que as variáveis de ambiente dos gateways de pagamento estão configuradas corretamente.

### Alertas não são enviados

Verifique se o comando `ProcessScheduledAlerts` está sendo executado via cron ou scheduler do Laravel.

### Erro de permissão no Docker

Certifique-se de que o Docker tem permissões adequadas para acessar o diretório do projeto.

### Frontend não conecta ao backend

Verifique se a variável `REACT_APP_API_URL` está configurada corretamente e se ambos os serviços estão rodando.

## 📄 Licença

Este projeto é de código aberto e está disponível sob a licença MIT.

