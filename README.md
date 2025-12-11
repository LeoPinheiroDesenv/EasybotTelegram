<div align="center">

# 🤖 Bot Telegram - Plataforma de Gerenciamento Completa

### Sistema profissional para gerenciamento de bots do Telegram com recursos avançados de pagamento, marketing, automação e análise de dados

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![React](https://img.shields.io/badge/React-18.2-61DAFB?style=for-the-badge&logo=react&logoColor=black)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Enabled-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

[Recursos](#-principais-recursos) •
[Instalação](#️-instalação-e-execução) •
[Documentação](#-api-endpoints) •
[Contribuir](#-contribuindo)

</div>

---

## 📑 Índice

- [Stack Tecnológico](#-stack-tecnológico)
- [Principais Recursos](#-principais-recursos)
- [Pré-requisitos](#-pré-requisitos)
- [Instalação e Execução](#️-instalação-e-execução)
- [Credenciais Padrão](#-credenciais-padrão)
- [Como Funciona](#-como-funciona)
- [Estrutura do Projeto](#-estrutura-do-projeto)
- [API Endpoints](#-api-endpoints)
- [Níveis de Acesso](#-níveis-de-acesso)
- [Comandos Docker](#-comandos-docker)
- [Desenvolvimento](#️-desenvolvimento)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Notas Importantes](#-notas-importantes)
- [Troubleshooting](#-troubleshooting)
- [Recursos Avançados](#-recursos-avançados)
- [Contribuindo](#-contribuindo)
- [Documentação Adicional](#-documentação-adicional)
- [Tutoriais e Guias](#-tutoriais-e-guias)
- [Casos de Uso](#-casos-de-uso)
- [Roadmap](#️-roadmap)
- [Perguntas Frequentes](#-perguntas-frequentes-faq)
- [Licença](#-licença)
- [Aviso Legal](#️-aviso-legal)
- [Agradecimentos](#-agradecimentos)

---

## 🚀 Stack Tecnológico

### Backend
- **PHP 8.2**: Linguagem de programação moderna e performática
- **Laravel 12**: Framework PHP robusto e elegante
- **JWT Auth**: Autenticação stateless com tokens
- **Eloquent ORM**: Mapeamento objeto-relacional intuitivo
- **Queue Jobs**: Processamento assíncrono de tarefas
- **Laravel Sanctum**: Autenticação de API
- **Middleware**: Pipeline de processamento de requisições

### Frontend
- **React.js 18.2**: Biblioteca JavaScript para UI
- **React Router 6**: Roteamento declarativo
- **Axios**: Cliente HTTP para requisições API
- **Chart.js**: Visualização de dados em gráficos
- **Font Awesome**: Biblioteca de ícones
- **CSS Modules**: Estilização componentizada
- **Context API**: Gerenciamento de estado global

### Banco de Dados
- **MySQL 8.0**: SGBD relacional confiável
- **Migrations**: Versionamento de schema
- **Seeders**: População de dados iniciais
- **Eloquent**: Query builder elegante

### Infraestrutura
- **Docker**: Containerização de aplicações
- **Docker Compose**: Orquestração de containers
- **Nginx/Apache**: Servidor web
- **Redis** (opcional): Cache e filas
- **Supervisor** (recomendado): Gerenciamento de workers

### Integrações Externas
- **Telegram Bot API**: Integração completa com Telegram
- **Mercado Pago SDK**: Pagamentos PIX e cartão
- **Stripe SDK**: Pagamentos com cartão internacional
- **Google2FA**: Autenticação de dois fatores
- **Simple QRCode**: Geração de QR Codes
- **FTP/SFTP**: Gerenciamento de arquivos remotos

### Ferramentas de Desenvolvimento
- **Composer**: Gerenciador de dependências PHP
- **NPM**: Gerenciador de dependências JavaScript
- **Laravel Pint**: Formatador de código PHP
- **ESLint**: Linter para JavaScript
- **PHPUnit**: Framework de testes
- **Laravel Tinker**: REPL interativo
- **Laravel Pail**: Visualizador de logs em tempo real

## ✨ Principais Recursos

### 🤖 Gerenciamento de Bots
- **Criação e Configuração**: Interface intuitiva para criar e configurar múltiplos bots do Telegram
- **Validação em Tempo Real**: Valida tokens e grupos antes da ativação
- **Monitoramento de Status**: Visualize o status de cada bot em tempo real
- **BotFather Integration**: Configure nome, descrição, comandos e menu diretamente pela plataforma
- **Webhooks**: Configure e gerencie webhooks do Telegram
- **Upload de Mídia**: Faça upload de imagens e arquivos para mensagens do bot

### 💰 Sistema de Pagamentos
- **Múltiplas Gateways**: Suporte para Mercado Pago e Stripe
- **Métodos de Pagamento**: 
  - PIX (com geração de QR Code e código copia-e-cola)
  - Cartão de Crédito (via Stripe)
- **Planos de Pagamento**: Configure planos recorrentes ou únicos
- **Ciclos de Pagamento**: Defina períodos (mensal, trimestral, anual, etc.)
- **Gestão de Transações**: Acompanhe todas as transações em tempo real
- **Webhooks de Pagamento**: Receba notificações automáticas de status de pagamento
- **Verificação de Expiração**: Sistema automático para detectar pagamentos expirados ou a expirar
- **Página de Pagamento**: Interface pública para processar pagamentos com cartão

### 📊 Marketing e Automação
- **Alertas Programados**: 
  - Configure alertas automáticos para contatos
  - Agende envios por data/hora específica
  - Processamento em background via fila de jobs
- **Downsell**: Ofereça planos alternativos automaticamente
- **Botões de Redirecionamento**: Crie botões personalizados com links externos
- **Mensagens de Boas-vindas**: Configure mensagens personalizadas para novos membros

### 👥 Gerenciamento de Contatos
- **Sincronização Automática**: Sincronize membros dos grupos do Telegram
- **Status do Telegram**: Verifique se contatos estão ativos no Telegram
- **Histórico Completo**: Visualize todo o histórico de ações de cada contato
- **Bloqueio de Contatos**: Bloqueie contatos indesejados
- **Estatísticas**: Visualize métricas de engajamento e conversão
- **Detalhes Completos**: Acesse informações detalhadas de cada contato

### 🏢 Gerenciamento de Grupos
- **Adicionar/Remover Membros**: Gerencie membros dos grupos via API
- **Verificação de Status**: Confira se um membro está no grupo
- **Informações do Grupo**: Visualize detalhes completos do grupo
- **Estatísticas**: Acompanhe métricas de crescimento e atividade
- **Atualização de Links**: Atualize links de convite automaticamente

### 🛠️ Comandos do Bot
- **CRUD Completo**: Crie, edite e exclua comandos personalizados
- **Registro no Telegram**: Registre comandos diretamente no BotFather via API
- **Visualização**: Liste todos os comandos registrados no Telegram
- **Exclusão Seletiva**: Remova comandos específicos ou todos de uma vez

### 👮 Administração
- **Administradores de Bot**: Gerencie quem pode administrar cada bot
- **Grupos de Usuários**: Organize usuários em grupos com permissões específicas
- **Níveis de Acesso**: Sistema com 3 níveis (Super Admin, Admin, User)
- **Permissões Granulares**: Controle acesso a menus e funcionalidades específicas
- **Logs de Auditoria**: Registre todas as ações no sistema (disponível para super admins)

### 💳 Faturamento e Relatórios
- **Dashboard de Faturamento**: Visualize receitas mensais e totais
- **Gráficos Interativos**: Charts.js para visualização de dados
- **Estatísticas em Tempo Real**: Métricas atualizadas automaticamente
- **Exportação de Dados**: Baixe relatórios em diversos formatos

### 🔐 Segurança
- **Autenticação JWT**: Tokens seguros com expiração configurável
- **2FA (Autenticação de Dois Fatores)**: 
  - Integração com Google Authenticator
  - QR Code para configuração fácil
  - Verificação obrigatória no login
- **Recuperação de Senha**: Sistema completo de reset de senha via e-mail
- **Criptografia de Senhas**: Bcrypt para armazenamento seguro
- **Middleware de Autorização**: Proteção em todas as rotas sensíveis

### 📁 Gerenciamento de Arquivos
- **FTP Manager**: 
  - Navegue em servidores FTP/SFTP
  - Upload e download de arquivos
  - Crie diretórios
  - Teste conexões
- **Storage Settings**: Configure links simbólicos para armazenamento público

### ⚙️ Configurações Avançadas
- **Comandos Artisan**: Execute comandos Laravel via interface (super admins)
- **Limpeza de Cache**: Clear de todos os caches com um clique
- **Perfil de Usuário**: 
  - Avatar personalizado
  - Informações de contato
  - Endereço completo com integração de CEP
  - Estados e municípios brasileiros
- **Diagnóstico PIX**: Ferramentas para validar e diagnosticar códigos PIX

### 📱 Interface do Usuário
- **Design Moderno**: Interface limpa e profissional
- **Responsiva**: Funciona perfeitamente em desktop, tablet e mobile
- **Componentes Reutilizáveis**: Biblioteca de componentes UI customizados
- **Font Awesome Icons**: Mais de 1.000 ícones disponíveis
- **Feedback Visual**: Alertas, modais e confirmações intuitivas
- **Paginação**: Sistema de paginação para listas grandes
- **Refresh Automático**: Dados atualizados em tempo real

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
- Criar e iniciar o banco de dados PostgreSQL
- Criar e iniciar o servidor backend
- Criar e iniciar o frontend React
- Executar as migrações do banco de dados
- Criar o usuário administrador padrão

### 4. Acesse a aplicação

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000/api
- **Health Check**: http://localhost:8000/api/health

## 👤 Credenciais Padrão

Após a primeira execução, o sistema criará automaticamente um usuário administrador:

- **Email**: admin@example.com
- **Senha**: password
- **Nível de Acesso**: Super Administrador

> ⚠️ **IMPORTANTE**: Altere essas credenciais imediatamente após o primeiro acesso em produção!

## 🎯 Como Funciona

### Fluxo de Uso Básico

1. **Login e Configuração Inicial**
   - Faça login com as credenciais padrão
   - Configure seu perfil e ative 2FA para maior segurança
   - Crie usuários adicionais e defina permissões

2. **Criação do Bot**
   - Acesse "Bots" > "Criar Novo Bot"
   - Insira o token obtido do @BotFather do Telegram
   - Configure o grupo/canal do Telegram que será gerenciado
   - Valide e ative o bot

3. **Configuração de Pagamentos**
   - Acesse "Configurações" > "Gateways de Pagamento"
   - Configure suas credenciais do Mercado Pago e/ou Stripe
   - Crie planos de pagamento (valores, descrições, ciclos)
   - Defina ciclos de pagamento (mensal, trimestral, etc.)

4. **Personalização do Bot**
   - Configure mensagens de boas-vindas personalizadas
   - Crie comandos customizados para o bot
   - Adicione botões de redirecionamento
   - Configure administradores do bot

5. **Marketing e Automação**
   - Configure alertas programados para engajar contatos
   - Crie ofertas de downsell para recuperar vendas
   - Monitore o status de pagamentos
   - Acompanhe métricas no dashboard

6. **Gerenciamento de Contatos**
   - Sincronize membros do grupo automaticamente
   - Visualize histórico completo de cada contato
   - Adicione ou remova membros via interface
   - Acompanhe estatísticas de engajamento

### Arquitetura do Sistema

O sistema segue uma arquitetura moderna e escalável baseada no padrão **MVC (Model-View-Controller)** e princípios **SOLID**:

#### Backend (Laravel)
- **Controllers**: Gerenciam requisições HTTP e lógica de negócio
- **Models**: Representam entidades do banco de dados (Eloquent ORM)
- **Services**: Camada de serviços para lógica de negócio complexa
- **Middleware**: Autenticação, autorização e validações
- **Jobs**: Processamento assíncrono de tarefas (filas)
- **Observers**: Eventos automáticos (ex: atualizar estatísticas após transação)

#### Frontend (React)
- **Components**: Componentes reutilizáveis (botões, cards, modais)
- **Pages**: Páginas completas da aplicação
- **Services**: Camada de comunicação com a API
- **Contexts**: Gerenciamento de estado global (AuthContext, etc.)
- **Hooks**: Lógica reutilizável (useAlert, useConfirm)

#### Integrações Externas
- **Telegram Bot API**: Comunicação direta com o Telegram
- **Mercado Pago SDK**: Processamento de pagamentos PIX
- **Stripe SDK**: Processamento de cartões de crédito
- **Webhooks**: Recebimento automático de eventos externos

#### Banco de Dados
O sistema utiliza MySQL 8.0 com as seguintes tabelas principais:
- `users`: Usuários do sistema
- `bots`: Bots do Telegram
- `contacts`: Contatos/leads capturados
- `transactions`: Transações financeiras
- `payment_plans`: Planos de pagamento
- `payment_cycles`: Ciclos de cobrança
- `alerts`: Alertas programados
- `downsells`: Ofertas de downsell
- `logs`: Auditoria do sistema
- E muitas outras...

## 📁 Estrutura do Projeto

```
botTelegram/
├── backend/                      # Backend Laravel
│   ├── app/
│   │   ├── Console/
│   │   │   └── Commands/        # Comandos Artisan customizados
│   │   ├── Http/
│   │   │   ├── Controllers/     # Controllers da API
│   │   │   └── Middleware/      # Middleware de autenticação e autorização
│   │   ├── Jobs/                # Jobs para processamento assíncrono
│   │   ├── Mail/                # Templates de e-mail
│   │   ├── Models/              # Models Eloquent (ORM)
│   │   ├── Observers/           # Observers para eventos
│   │   ├── Services/            # Camada de serviços
│   │   └── Providers/           # Service Providers
│   ├── config/                  # Arquivos de configuração
│   ├── database/
│   │   ├── migrations/          # Migrations do banco de dados
│   │   ├── seeders/             # Seeders para popular o BD
│   │   └── factories/           # Factories para testes
│   ├── routes/
│   │   ├── api.php              # Rotas da API
│   │   ├── web.php              # Rotas web
│   │   └── console.php          # Rotas de console
│   ├── public/                  # Arquivos públicos
│   ├── resources/
│   │   └── views/               # Templates Blade
│   ├── storage/                 # Armazenamento de logs e cache
│   ├── tests/                   # Testes automatizados
│   ├── .env                     # Variáveis de ambiente
│   ├── composer.json            # Dependências PHP
│   ├── artisan                  # CLI do Laravel
│   └── Dockerfile               # Docker do backend
│
├── frontend/                    # Frontend React
│   ├── public/                  # Arquivos públicos
│   │   ├── index.html
│   │   └── favicon.*
│   ├── src/
│   │   ├── components/          # Componentes reutilizáveis
│   │   │   ├── ui/              # Componentes de UI básicos
│   │   │   ├── Header.js
│   │   │   ├── Sidebar.js
│   │   │   ├── Layout.js
│   │   │   └── ...
│   │   ├── contexts/            # Contextos React
│   │   │   ├── AuthContext.js   # Contexto de autenticação
│   │   │   └── ManageBotContext.js
│   │   ├── hooks/               # Custom hooks
│   │   │   ├── useAlert.js
│   │   │   └── useConfirm.js
│   │   ├── pages/               # Páginas da aplicação
│   │   │   ├── Login.js
│   │   │   ├── Dashboard.js
│   │   │   ├── BotList.js
│   │   │   ├── ManageBot.js
│   │   │   └── ...
│   │   ├── services/            # Serviços de API
│   │   │   ├── api.js           # Configuração do Axios
│   │   │   ├── authService.js
│   │   │   ├── botService.js
│   │   │   └── ...
│   │   ├── styles/              # Estilos globais
│   │   │   ├── colors.css
│   │   │   ├── forms.css
│   │   │   └── ...
│   │   ├── utils/               # Utilitários
│   │   ├── App.js               # Componente principal
│   │   ├── App.css
│   │   └── index.js             # Entry point
│   ├── build/                   # Build de produção
│   ├── package.json             # Dependências Node
│   ├── Dockerfile               # Docker do frontend
│   └── .dockerignore
│
├── docker-compose.yml           # Orquestração de containers
├── .gitignore                   # Arquivos ignorados pelo Git
└── README.md                    # Documentação
```

## 🔐 API Endpoints

### Autenticação (Público)
- `POST /api/auth/login` - Login de usuário
- `POST /api/auth/verify-2fa` - Verificação de 2FA no login
- `POST /api/auth/password/request-reset` - Solicitar reset de senha
- `POST /api/auth/password/reset` - Resetar senha com token

### Autenticação (Protegido)
- `GET /api/auth/me` - Obter usuário atual
- `GET /api/auth/2fa/setup` - Configurar 2FA (gerar QR Code)
- `POST /api/auth/2fa/verify` - Verificar e ativar 2FA
- `POST /api/auth/2fa/disable` - Desativar 2FA

### Perfil
- `GET /api/profile` - Obter perfil do usuário
- `PUT /api/profile` - Atualizar perfil
- `POST /api/profile/avatar` - Upload de avatar
- `DELETE /api/profile/avatar` - Remover avatar
- `GET /api/profile/states` - Listar estados brasileiros
- `GET /api/profile/municipalities` - Listar municípios por estado
- `GET /api/profile/consult-cep` - Consultar CEP

### Usuários (Admin)
- `GET /api/users` - Listar todos os usuários
- `POST /api/users` - Criar novo usuário
- `GET /api/users/{id}` - Obter usuário específico
- `PUT /api/users/{id}` - Atualizar usuário
- `DELETE /api/users/{id}` - Excluir usuário

### Grupos de Usuários (Admin)
- `GET /api/user-groups` - Listar grupos
- `POST /api/user-groups` - Criar grupo
- `GET /api/user-groups/{id}` - Obter grupo
- `PUT /api/user-groups/{id}` - Atualizar grupo
- `DELETE /api/user-groups/{id}` - Excluir grupo
- `GET /api/user-groups/menus/available` - Menus disponíveis
- `GET /api/user-groups/bots/available` - Bots disponíveis

### Bots
- `GET /api/bots` - Listar todos os bots
- `POST /api/bots` - Criar novo bot
- `GET /api/bots/{id}` - Obter bot específico
- `PUT /api/bots/{id}` - Atualizar bot
- `DELETE /api/bots/{id}` - Excluir bot
- `POST /api/bots/{id}/initialize` - Inicializar bot
- `POST /api/bots/{id}/stop` - Parar bot
- `POST /api/bots/{id}/validate-and-activate` - Validar e ativar
- `GET /api/bots/{id}/status` - Obter status do bot
- `POST /api/bots/validate` - Validar token
- `POST /api/bots/validate-token-and-group` - Validar token e grupo
- `POST /api/bots/{id}/media/upload` - Upload de mídia
- `DELETE /api/bots/{id}/media` - Excluir mídia
- `POST /api/bots/{id}/update-invite-link` - Atualizar link de convite

### Comandos do Bot
- `GET /api/bots/{botId}/commands` - Listar comandos
- `POST /api/bots/{botId}/commands` - Criar comando
- `PUT /api/bots/{botId}/commands/{commandId}` - Atualizar comando
- `DELETE /api/bots/{botId}/commands/{commandId}` - Excluir comando
- `POST /api/bots/{botId}/commands/register` - Registrar comandos no Telegram
- `GET /api/bots/{botId}/commands/telegram` - Listar comandos do Telegram
- `DELETE /api/bots/{botId}/commands/telegram` - Excluir todos os comandos
- `DELETE /api/bots/{botId}/commands/telegram/command` - Excluir comando específico

### Botões de Redirecionamento
- `GET /api/bots/{botId}/redirect-buttons` - Listar botões
- `POST /api/bots/{botId}/redirect-buttons` - Criar botão
- `PUT /api/bots/{botId}/redirect-buttons/{buttonId}` - Atualizar botão
- `DELETE /api/bots/{botId}/redirect-buttons/{buttonId}` - Excluir botão

### Administradores de Bot
- `GET /api/bot-administrators` - Listar administradores
- `POST /api/bot-administrators` - Adicionar administrador
- `GET /api/bot-administrators/{id}` - Obter administrador
- `PUT /api/bot-administrators/{id}` - Atualizar administrador
- `DELETE /api/bot-administrators/{id}` - Remover administrador

### Grupos do Telegram
- `GET /api/telegram-groups` - Listar grupos
- `POST /api/telegram-groups` - Criar grupo
- `GET /api/telegram-groups/{id}` - Obter grupo
- `PUT /api/telegram-groups/{id}` - Atualizar grupo
- `DELETE /api/telegram-groups/{id}` - Excluir grupo

### BotFather
- `GET /api/bots/{botId}/botfather/info` - Obter informações do bot
- `POST /api/bots/{botId}/botfather/set-name` - Definir nome
- `POST /api/bots/{botId}/botfather/set-description` - Definir descrição
- `POST /api/bots/{botId}/botfather/set-short-description` - Definir descrição curta
- `POST /api/bots/{botId}/botfather/set-about` - Definir "sobre"
- `POST /api/bots/{botId}/botfather/set-menu-button` - Configurar botão de menu
- `POST /api/bots/{botId}/botfather/set-default-admin-rights` - Definir direitos de admin
- `POST /api/bots/{botId}/botfather/delete-commands` - Excluir comandos

### Webhooks do Telegram
- `GET /api/telegram/webhook/{botId}/info` - Obter info do webhook
- `POST /api/telegram/webhook/{botId}/set` - Configurar webhook
- `POST /api/telegram/webhook/{botId}/delete` - Excluir webhook
- `POST /api/telegram/webhook/{botId}` - Receber eventos (público)

### Planos de Pagamento
- `GET /api/payment-plans` - Listar planos
- `POST /api/payment-plans` - Criar plano
- `GET /api/payment-plans/{id}` - Obter plano
- `PUT /api/payment-plans/{id}` - Atualizar plano
- `DELETE /api/payment-plans/{id}` - Excluir plano

### Ciclos de Pagamento
- `GET /api/payment-cycles` - Listar ciclos
- `GET /api/payment-cycles/active` - Listar ciclos ativos
- `POST /api/payment-cycles` - Criar ciclo
- `GET /api/payment-cycles/{id}` - Obter ciclo
- `PUT /api/payment-cycles/{id}` - Atualizar ciclo
- `DELETE /api/payment-cycles/{id}` - Excluir ciclo

### Pagamentos (Público)
- `GET /api/payment/transaction/{token}` - Obter transação
- `GET /api/payment/stripe-config` - Obter config Stripe
- `POST /api/payment/card/create-intent` - Criar intent de pagamento
- `POST /api/payment/card/confirm` - Confirmar pagamento

### Pagamentos (Protegido)
- `POST /api/payments/pix` - Processar pagamento PIX
- `POST /api/payments/credit-card` - Processar cartão de crédito

### Webhooks de Pagamento (Público)
- `POST /api/payments/webhook/mercadopago` - Webhook Mercado Pago
- `POST /api/payments/webhook/stripe` - Webhook Stripe

### Status de Pagamento
- `GET /api/payment-status/contact/{contactId}` - Status do contato
- `GET /api/payment-status/bot/{botId}` - Status do bot
- `POST /api/payment-status/check-expired/{botId?}` - Verificar expirados
- `POST /api/payment-status/check-expiring/{botId?}` - Verificar a expirar

### Configurações de Gateway
- `GET /api/payment-gateway-configs` - Listar configurações
- `POST /api/payment-gateway-configs` - Criar configuração
- `GET /api/payment-gateway-configs/{id}` - Obter configuração
- `PUT /api/payment-gateway-configs/{id}` - Atualizar configuração
- `DELETE /api/payment-gateway-configs/{id}` - Excluir configuração
- `GET /api/payment-gateway-configs/config` - Obter configuração ativa
- `GET /api/payment-gateway-configs/status` - Verificar status da API

### Contatos
- `GET /api/contacts` - Listar contatos
- `POST /api/contacts` - Criar contato
- `GET /api/contacts/{id}` - Obter contato
- `PUT /api/contacts/{id}` - Atualizar contato
- `DELETE /api/contacts/{id}` - Excluir contato
- `POST /api/contacts/{id}/block` - Bloquear contato
- `GET /api/contacts/stats` - Estatísticas
- `GET /api/contacts/latest` - Contatos recentes
- `POST /api/contacts/sync-group-members` - Sincronizar membros
- `POST /api/contacts/update-all-status` - Atualizar status

### Gerenciamento de Grupos
- `POST /api/bots/{botId}/group/add-member` - Adicionar membro
- `POST /api/bots/{botId}/group/remove-member` - Remover membro
- `GET /api/bots/{botId}/group/member-status/{contactId}` - Status do membro
- `GET /api/bots/{botId}/group/info` - Informações do grupo
- `GET /api/bots/{botId}/group/statistics` - Estatísticas do grupo
- `GET /api/bots/{botId}/group/contact-history/{contactId}` - Histórico

### Faturamento
- `GET /api/billing` - Obter faturamento
- `GET /api/billing/monthly` - Faturamento mensal
- `GET /api/billing/chart` - Dados para gráfico
- `GET /api/billing/total` - Faturamento total
- `GET /api/billing/dashboard-stats` - Estatísticas do dashboard

### Alertas
- `GET /api/alerts` - Listar alertas
- `POST /api/alerts` - Criar alerta
- `GET /api/alerts/{id}` - Obter alerta
- `PUT /api/alerts/{id}` - Atualizar alerta
- `DELETE /api/alerts/{id}` - Excluir alerta
- `POST /api/alerts/process` - Processar alertas
- `POST /api/alerts/process-auto` - Processar automaticamente (público com token)

### Downsell
- `GET /api/downsells` - Listar downsells
- `POST /api/downsells` - Criar downsell
- `GET /api/downsells/{id}` - Obter downsell
- `PUT /api/downsells/{id}` - Atualizar downsell
- `DELETE /api/downsells/{id}` - Excluir downsell

### FTP Manager
- `GET /api/ftp/files` - Listar arquivos
- `POST /api/ftp/upload` - Upload de arquivo
- `GET /api/ftp/download` - Download de arquivo
- `DELETE /api/ftp/delete` - Excluir arquivo
- `POST /api/ftp/directory` - Criar diretório
- `POST /api/ftp/test-connection` - Testar conexão

### Storage (Super Admin)
- `GET /api/storage/link/status` - Verificar link de storage
- `POST /api/storage/link/create` - Criar link de storage
- `POST /api/storage/test` - Testar acesso ao storage

### Comandos Artisan (Super Admin)
- `GET /api/artisan/commands` - Comandos disponíveis
- `POST /api/artisan/execute` - Executar comando
- `POST /api/artisan/clear-all-caches` - Limpar todos os caches

### Diagnóstico PIX (Super Admin)
- `POST /api/pix-diagnostic/validate-code` - Validar código PIX
- `GET /api/pix-diagnostic/statistics` - Estatísticas CRC
- `GET /api/pix-diagnostic/mercado-pago-report` - Relatório Mercado Pago

### Logs (Super Admin)
- `GET /api/logs` - Listar logs
- `GET /api/logs/{id}` - Obter log
- `DELETE /api/logs` - Excluir todos os logs

### Health Check (Público)
- `GET /api/health` - Verificar status do servidor

## 🔒 Níveis de Acesso

O sistema possui três níveis de acesso com permissões hierárquicas:

### Super Admin
- **Acesso Total**: Todas as funcionalidades do sistema
- **Gerenciamento de Admins**: Pode criar e gerenciar outros administradores
- **Logs de Auditoria**: Acesso completo aos logs do sistema
- **Comandos Artisan**: Execução de comandos de manutenção
- **Diagnósticos**: Ferramentas avançadas de diagnóstico (PIX, etc.)
- **Storage**: Configurações de armazenamento e links simbólicos

### Admin
- **Gerenciamento de Usuários**: Criar e gerenciar usuários comuns
- **Gerenciamento de Bots**: CRUD completo de bots
- **Grupos de Usuários**: Criar grupos e definir permissões
- **Configurações**: Acesso a configurações gerais
- **Relatórios**: Visualização de relatórios e estatísticas
- **Marketing**: Configuração de alertas e downsells

### User (Usuário Comum)
- **Acesso Limitado**: Apenas visualização de dados
- **Permissões Customizadas**: Definidas pelo admin via grupos
- **Próprio Perfil**: Pode editar apenas seu próprio perfil
- **Bots Atribuídos**: Acesso apenas aos bots permitidos

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
docker-compose exec backend npm run migrate
```

### Criar usuário admin padrão manualmente

```bash
docker-compose exec backend node migrations/createDefaultAdmin.js
```

## 🛠️ Desenvolvimento

### Executar sem Docker

#### Backend

```bash
cd backend
npm install
npm run dev
```

#### Frontend

```bash
cd frontend
npm install
npm start
```

### Variáveis de Ambiente

#### Backend (.env)

Configure as seguintes variáveis no arquivo `backend/.env`:

**Aplicação:**
```env
APP_ENV=local                    # Ambiente (local, production)
APP_DEBUG=true                   # Debug mode (false em produção)
APP_URL=http://localhost:8000    # URL da aplicação
APP_KEY=                         # Gerado automaticamente
```

**Banco de Dados:**
```env
DB_CONNECTION=mysql              # Driver do banco
DB_HOST=mysql                    # Host (mysql para Docker)
DB_PORT=3306                     # Porta do MySQL
DB_DATABASE=bottelegram_db       # Nome do banco
DB_USERNAME=bottelegram_user     # Usuário do banco
DB_PASSWORD=bottelegram123       # Senha do banco
```

**Autenticação:**
```env
JWT_SECRET=your-secret-key       # Chave secreta JWT (mude em produção!)
JWT_TTL=60                       # Tempo de expiração em minutos
```

**Mercado Pago (PIX):**
```env
MERCADOPAGO_ACCESS_TOKEN=        # Token de acesso do Mercado Pago
MERCADOPAGO_WEBHOOK_URL=         # URL do webhook
```

**Stripe (Cartão de Crédito):**
```env
STRIPE_PUBLIC_KEY=               # Chave pública Stripe
STRIPE_SECRET_KEY=               # Chave secreta Stripe
STRIPE_WEBHOOK_SECRET=           # Secret do webhook
```

**E-mail (Recuperação de Senha):**
```env
MAIL_MAILER=smtp                 # Driver de e-mail
MAIL_HOST=smtp.mailtrap.io       # Host SMTP
MAIL_PORT=2525                   # Porta SMTP
MAIL_USERNAME=                   # Usuário SMTP
MAIL_PASSWORD=                   # Senha SMTP
MAIL_ENCRYPTION=tls              # Tipo de criptografia
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Bot Telegram"
```

**Processamento de Filas:**
```env
QUEUE_CONNECTION=database        # Driver de fila (database, redis)
```

**Alertas Automáticos (Opcional):**
```env
ALERTS_PROCESS_SECRET_TOKEN=     # Token para processar alertas via API
```

#### Frontend (.env)

Configure as seguintes variáveis no arquivo `frontend/.env`:

```env
REACT_APP_API_URL=http://localhost:8000/api  # URL da API
```

#### Docker Compose (.env na raiz)

```env
# Banco de Dados
DB_ROOT_PASSWORD=root123
DB_NAME=bottelegram_db
DB_USER=bottelegram_user
DB_PASSWORD=bottelegram123
DB_PORT=3306

# JWT
JWT_SECRET=your-secret-key-change-in-production

# Mercado Pago
MERCADOPAGO_ACCESS_TOKEN=
MERCADOPAGO_WEBHOOK_URL=

# Stripe
STRIPE_PUBLIC_KEY=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
```

## 📝 Notas Importantes

### Segurança
- 🔐 O sistema utiliza **JWT** para autenticação com suporte a **2FA**
- 🔑 As senhas são criptografadas usando **bcrypt**
- 🛡️ Todas as rotas sensíveis são protegidas por middleware de autenticação
- ⚠️ **SEMPRE** altere a `JWT_SECRET` e credenciais padrão em produção
- 🔒 Configure SSL/HTTPS em produção para proteger dados sensíveis
- 📧 Configure e-mail real para recuperação de senha funcionar

### Banco de Dados
- 💾 O banco de dados **MySQL 8.0** é persistido em um volume Docker (`mysql_data`)
- 🔄 As migrations são executadas automaticamente na primeira inicialização
- 📊 O sistema cria automaticamente um super admin no primeiro deploy
- 🗄️ Faça backups regulares do volume Docker em produção

### Processamento Assíncrono
- ⚡ O sistema utiliza **filas** (queues) para processar tarefas pesadas:
  - Envio de alertas em massa
  - Processamento de webhooks
  - Sincronização de grupos
  - Envio de e-mails
- 🔧 Execute `php artisan queue:work` para processar a fila
- 📦 Use **Supervisor** em produção para manter o worker sempre ativo

### Integrações Telegram
- 🤖 É necessário criar um bot via [@BotFather](https://t.me/BotFather) no Telegram
- 🔑 Guarde o token do bot com segurança
- 👥 O bot precisa ser adicionado ao grupo/canal como administrador
- 🌐 Configure webhooks para receber atualizações em tempo real
- 📨 O sistema suporta múltiplos bots simultaneamente

### Pagamentos
- 💳 Configure pelo menos um gateway de pagamento (Mercado Pago ou Stripe)
- 🇧🇷 **PIX**: Utilize Mercado Pago (requer conta brasileira)
- 💳 **Cartão**: Utilize Stripe (internacional) ou Mercado Pago
- 🔔 Configure webhooks nos painéis dos gateways para receber notificações
- 🧪 Teste sempre em modo sandbox/teste antes de produção

### Performance
- 🚀 Use **Redis** para cache em produção (altere `CACHE_STORE=redis`)
- 🗄️ Use **Redis** para filas em produção (altere `QUEUE_CONNECTION=redis`)
- 📈 Monitore o uso de recursos com ferramentas como New Relic ou Datadog
- 🔍 Ative logging adequado mas evite logs excessivos em produção

### Desenvolvimento
- 🛠️ Use `composer dev` no backend para rodar servidor, fila e logs simultaneamente
- 🎨 Use `npm start` no frontend para desenvolvimento com hot reload
- 🧪 Execute `php artisan test` para rodar os testes
- 📝 Siga os padrões PSR-12 para código PHP
- ♻️ Utilize os princípios SOLID para manter código limpo e manutenível

### Deploy em Produção
1. 🔄 Configure variáveis de ambiente adequadas
2. 🔐 Altere todas as credenciais e secrets padrão
3. 🌐 Configure domínio e SSL/HTTPS
4. 📧 Configure servidor de e-mail real (SMTP)
5. 🔔 Configure webhooks dos gateways de pagamento
6. 🔧 Configure Supervisor para queue workers
7. ⏰ Configure cron jobs para tarefas agendadas
8. 💾 Configure backups automáticos
9. 📊 Configure monitoramento e alertas
10. 🧪 Teste tudo antes de liberar para usuários

## 🐛 Troubleshooting

### Erro de conexão com o banco de dados

**Problema**: `SQLSTATE[HY000] [2002] Connection refused`

**Soluções**:
```bash
# Verifique se o MySQL está rodando
docker-compose ps

# Verifique os logs do MySQL
docker-compose logs mysql

# Recrie o container se necessário
docker-compose down
docker-compose up -d mysql
```

### Erro de permissão no Docker

**Problema**: `Permission denied` ao criar containers

**Soluções**:
```bash
# Adicione seu usuário ao grupo docker
sudo usermod -aG docker $USER

# Reinicie a sessão ou execute
newgrp docker

# Ou execute com sudo (não recomendado)
sudo docker-compose up -d
```

### Frontend não conecta ao backend

**Problema**: Erro de CORS ou `Network Error`

**Soluções**:
1. Verifique se a variável `REACT_APP_API_URL` está correta no `frontend/.env`
2. Verifique se o backend está rodando: `curl http://localhost:8000/api/health`
3. Verifique os logs: `docker-compose logs backend`
4. Reconstrua o frontend se alterou variáveis de ambiente:
```bash
docker-compose down
docker-compose up -d --build frontend
```

### JWT Token inválido

**Problema**: `Token invalid` ou `Token expired`

**Soluções**:
```bash
# Gere uma nova chave JWT
docker-compose exec backend php artisan jwt:secret

# Ou defina manualmente no .env
JWT_SECRET=$(openssl rand -base64 32)
```

### Migrations não executam

**Problema**: Tabelas não são criadas no banco

**Soluções**:
```bash
# Execute migrations manualmente
docker-compose exec backend php artisan migrate

# Force a execução (cuidado!)
docker-compose exec backend php artisan migrate --force

# Resetar banco de dados (CUIDADO: apaga tudo!)
docker-compose exec backend php artisan migrate:fresh
```

### Fila não processa jobs

**Problema**: Alertas ou e-mails não são enviados

**Soluções**:
```bash
# Verifique a tabela de jobs
docker-compose exec backend php artisan queue:monitor

# Execute o worker manualmente
docker-compose exec backend php artisan queue:work

# Limpe jobs falhados
docker-compose exec backend php artisan queue:flush
```

### Webhook do Telegram não recebe eventos

**Problema**: Bot não responde a mensagens

**Soluções**:
1. Verifique se o webhook está configurado:
```bash
curl https://api.telegram.org/bot<TOKEN>/getWebhookInfo
```

2. URL do webhook deve ser HTTPS em produção
3. Verifique os logs: `docker-compose logs backend | grep telegram`
4. Teste com polling se webhook não funcionar

### Erro ao processar pagamento PIX

**Problema**: QR Code não é gerado

**Soluções**:
1. Verifique as credenciais do Mercado Pago no `.env`
2. Teste a conexão:
```bash
docker-compose exec backend php artisan tinker
>>> $config = App\Models\PaymentGatewayConfig::first();
>>> $config->mercadopago_access_token;
```
3. Verifique os logs: `docker-compose logs backend | grep mercadopago`
4. Use a rota de diagnóstico: `GET /api/pix-diagnostic/statistics`

### Erro ao upload de arquivo

**Problema**: `Failed to upload file` ou erro 500

**Soluções**:
```bash
# Verifique permissões do storage
docker-compose exec backend chmod -R 775 storage
docker-compose exec backend chown -R www-data:www-data storage

# Crie link simbólico do storage
docker-compose exec backend php artisan storage:link

# Verifique o tamanho máximo de upload no PHP
docker-compose exec backend php -i | grep upload_max_filesize
```

### Cache causando problemas

**Problema**: Alterações não aparecem

**Soluções**:
```bash
# Limpe todos os caches
docker-compose exec backend php artisan optimize:clear

# Ou individualmente
docker-compose exec backend php artisan cache:clear
docker-compose exec backend php artisan config:clear
docker-compose exec backend php artisan route:clear
docker-compose exec backend php artisan view:clear
```

### Erro 500 genérico

**Problema**: Internal Server Error

**Soluções**:
```bash
# Verifique os logs do Laravel
docker-compose exec backend tail -f storage/logs/laravel.log

# Ou use o Pail
docker-compose exec backend php artisan pail

# Verifique logs do Apache/Nginx
docker-compose logs backend
```

### Porta já em uso

**Problema**: `Port 3000 is already in use`

**Soluções**:
```bash
# Encontre o processo usando a porta
lsof -i :3000

# Mate o processo
kill -9 <PID>

# Ou altere a porta no docker-compose.yml
ports:
  - "3001:3000"  # Use 3001 no host
```

### Composer/NPM dependencies desatualizadas

**Problema**: Erro ao instalar dependências

**Soluções**:
```bash
# Backend - atualize dependências
docker-compose exec backend composer update

# Frontend - atualize dependências
docker-compose exec frontend npm update

# Ou reconstrua os containers
docker-compose down
docker-compose up -d --build
```

### Performance lenta

**Problema**: Sistema lento ou travando

**Soluções**:
1. **Configure cache Redis**:
```env
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

2. **Otimize o Laravel**:
```bash
docker-compose exec backend php artisan optimize
docker-compose exec backend php artisan config:cache
docker-compose exec backend php artisan route:cache
```

3. **Monitore recursos**:
```bash
docker stats
```

4. **Aumente recursos do Docker** nas configurações do Docker Desktop

### Logs estão muito grandes

**Problema**: Arquivo de log ocupando muito espaço

**Soluções**:
```bash
# Limpe logs antigos
docker-compose exec backend php artisan log:clear

# Ou manualmente
docker-compose exec backend truncate -s 0 storage/logs/laravel.log

# Configure rotação de logs no config/logging.php
'daily' => [
    'driver' => 'daily',
    'days' => 7,  // Mantém apenas 7 dias
],
```

### Precisa de mais ajuda?

- 📧 Verifique a documentação oficial do Laravel: https://laravel.com/docs
- 💬 Consulte a documentação do Telegram Bot API: https://core.telegram.org/bots/api
- 🐛 Crie uma issue no repositório com detalhes do erro
- 📝 Sempre inclua logs relevantes ao reportar problemas

## 🚀 Recursos Avançados

### Comandos Úteis do Artisan

```bash
# Entrar no container do backend
docker-compose exec backend bash

# Criar nova migration
php artisan make:migration create_table_name

# Criar novo controller
php artisan make:controller NomeController --api

# Criar novo model com migration e controller
php artisan make:model NomeModel -mcr

# Criar novo service
php artisan make:service NomeService

# Criar novo job para fila
php artisan make:job NomeJob

# Executar testes
php artisan test

# Gerar relatório de cobertura
php artisan test --coverage

# Verificar qualidade do código
./vendor/bin/pint

# Interagir com o sistema (Tinker)
php artisan tinker

# Listar todas as rotas
php artisan route:list

# Verificar configuração
php artisan config:show

# Processar alertas manualmente
php artisan alerts:process

# Atualizar status do Telegram dos contatos
php artisan contacts:update-telegram-status

# Processar polling do Telegram (alternativa a webhooks)
php artisan telegram:polling
```

### Comandos Úteis do Docker

```bash
# Ver logs de todos os serviços
docker-compose logs -f

# Ver logs de um serviço específico
docker-compose logs -f backend

# Reiniciar um serviço
docker-compose restart backend

# Parar todos os serviços
docker-compose stop

# Parar e remover containers
docker-compose down

# Parar e remover containers, volumes e imagens
docker-compose down -v --rmi all

# Reconstruir apenas um serviço
docker-compose up -d --build backend

# Verificar status dos containers
docker-compose ps

# Verificar uso de recursos
docker stats

# Executar comando dentro do container
docker-compose exec backend php artisan --version

# Acessar banco de dados diretamente
docker-compose exec mysql mysql -u bottelegram_user -p bottelegram_db

# Backup do banco de dados
docker-compose exec mysql mysqldump -u bottelegram_user -p bottelegram_db > backup.sql

# Restaurar backup
docker-compose exec -T mysql mysql -u bottelegram_user -p bottelegram_db < backup.sql
```

### Integração Contínua (CI/CD)

Exemplo de workflow para GitHub Actions (`.github/workflows/tests.yml`):

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: test_db
          MYSQL_ROOT_PASSWORD: root
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      
      - name: Install Dependencies
        run: composer install
        working-directory: ./backend
      
      - name: Run Tests
        run: php artisan test
        working-directory: ./backend
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: test_db
          DB_USERNAME: root
          DB_PASSWORD: root
```

### Monitoramento e Logs

Configure ferramentas de monitoramento em produção:

**1. Sentry (Rastreamento de Erros)**
```bash
composer require sentry/sentry-laravel
```

**2. New Relic (APM)**
```bash
# Adicione ao Dockerfile
RUN curl -L https://download.newrelic.com/php_agent/release/newrelic-php5-*.tar.gz | tar -C /tmp -zx
```

**3. Papertrail (Logs Centralizados)**
```env
LOG_CHANNEL=stack
LOG_CHANNELS=single,papertrail
```

### Otimizações de Performance

**1. Habilite OPcache no PHP:**
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
```

**2. Use CDN para assets estáticos:**
```env
ASSET_URL=https://cdn.seudominio.com
```

**3. Configure compressão Gzip:**
```apache
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>
```

**4. Cache de consultas do banco:**
```php
// No controller
$users = Cache::remember('users', 3600, function () {
    return User::all();
});
```

### Segurança Adicional

**1. Configure Rate Limiting:**
```php
// No arquivo routes/api.php
Route::middleware(['throttle:60,1'])->group(function () {
    // Máximo 60 requisições por minuto
});
```

**2. Proteção contra SQL Injection:**
```php
// SEMPRE use prepared statements
User::where('email', $email)->first(); // ✅ Correto
DB::select("SELECT * FROM users WHERE email = ?", [$email]); // ✅ Correto
DB::select("SELECT * FROM users WHERE email = $email"); // ❌ NUNCA faça isso!
```

**3. Validação de entrada:**
```php
$validated = $request->validate([
    'email' => 'required|email|max:255',
    'password' => 'required|min:8|confirmed',
    'name' => 'required|string|max:255',
]);
```

**4. HTTPS obrigatório em produção:**
```php
// No AppServiceProvider
if (config('app.env') === 'production') {
    URL::forceScheme('https');
}
```

## 🤝 Contribuindo

Contribuições são bem-vindas! Para contribuir:

1. **Fork** o projeto
2. **Crie uma branch** para sua feature (`git checkout -b feature/MinhaFeature`)
3. **Commit** suas mudanças (`git commit -m 'Adiciona MinhaFeature'`)
4. **Push** para a branch (`git push origin feature/MinhaFeature`)
5. **Abra um Pull Request**

### Padrões de Código

- **PHP**: Siga o PSR-12
- **JavaScript**: Use ESLint com configuração Airbnb
- **Commits**: Use Conventional Commits (feat, fix, docs, etc.)
- **Testes**: Escreva testes para novas funcionalidades

### Exemplo de Commit Convencional

```bash
feat(bot): adiciona suporte a mensagens agendadas
fix(payment): corrige validação de código PIX
docs(readme): atualiza seção de instalação
style(frontend): ajusta espaçamento dos botões
refactor(auth): simplifica lógica de autenticação
test(payment): adiciona testes para gateway Stripe
chore(deps): atualiza dependências do Laravel
```

## 📚 Documentação Adicional

- [Laravel Documentation](https://laravel.com/docs) - Framework backend
- [React Documentation](https://react.dev) - Framework frontend
- [Telegram Bot API](https://core.telegram.org/bots/api) - API do Telegram
- [Mercado Pago Developers](https://www.mercadopago.com.br/developers) - API de pagamentos
- [Stripe Documentation](https://stripe.com/docs) - API de cartões
- [Docker Documentation](https://docs.docker.com) - Containerização

## 🎓 Tutoriais e Guias

### Como Criar um Bot no Telegram

1. Abra o Telegram e procure por [@BotFather](https://t.me/BotFather)
2. Envie o comando `/newbot`
3. Escolha um nome para o bot (ex: "Meu Bot de Vendas")
4. Escolha um username (deve terminar com "bot", ex: "meu_vendas_bot")
5. Copie o **token** fornecido
6. Adicione o bot ao seu grupo/canal como **administrador**
7. Use o token na plataforma para configurar o bot

### Como Obter Chat ID do Grupo

1. Adicione o bot ao grupo
2. Envie qualquer mensagem no grupo
3. Acesse: `https://api.telegram.org/bot<TOKEN>/getUpdates`
4. Procure por `"chat":{"id":-1001234567890}` (número negativo para grupos)
5. Use esse ID ao criar o bot na plataforma

### Como Configurar Mercado Pago

1. Acesse [Mercado Pago Developers](https://www.mercadopago.com.br/developers)
2. Crie uma aplicação
3. Obtenha o **Access Token** (Production ou Test)
4. Configure webhook em "Notificações" > "Webhooks"
5. URL do webhook: `https://seudominio.com/api/payments/webhook/mercadopago`
6. Adicione o token no `.env` do backend

### Como Configurar Stripe

1. Acesse [Stripe Dashboard](https://dashboard.stripe.com)
2. Obtenha as chaves em "Developers" > "API keys"
3. Copie **Publishable key** e **Secret key**
4. Configure webhook em "Developers" > "Webhooks"
5. URL do webhook: `https://seudominio.com/api/payments/webhook/stripe`
6. Copie o **Signing secret**
7. Adicione as chaves no `.env` do backend

## 🌟 Casos de Uso

### E-commerce
- Adicionar compradores automaticamente em grupo VIP
- Enviar alertas de produtos novos
- Gerenciar assinaturas recorrentes
- Oferecer downsells para carrinhos abandonados

### Educação
- Adicionar alunos em grupos de turma
- Enviar lembretes de aulas
- Gerenciar pagamentos de mensalidades
- Disponibilizar materiais exclusivos

### Comunidades
- Gerenciar membros por nível de assinatura
- Enviar conteúdo exclusivo
- Coletar pagamentos de mensalidades
- Automatizar boas-vindas e onboarding

### Marketing
- Capturar leads via bot do Telegram
- Segmentar contatos por interesse
- Enviar campanhas automatizadas
- Acompanhar métricas de conversão

## 🗺️ Roadmap

### ✅ Versão Atual (v1.0)
- [x] Gerenciamento completo de bots
- [x] Sistema de pagamentos (PIX e Cartão)
- [x] Marketing com alertas e downsell
- [x] Gerenciamento de grupos e contatos
- [x] Autenticação com 2FA
- [x] Dashboard com estatísticas
- [x] Sistema de logs e auditoria
- [x] BotFather integration
- [x] FTP Manager
- [x] Múltiplos níveis de acesso

### 🔨 Em Desenvolvimento (v1.1)
- [ ] Dashboard com mais métricas e gráficos
- [ ] Exportação de relatórios (PDF, Excel)
- [ ] Temas personalizáveis (dark mode)
- [ ] Notificações push em tempo real
- [ ] Sistema de templates para mensagens
- [ ] Integração com WhatsApp Business API
- [ ] API pública para integrações
- [ ] Webhooks customizáveis

### 🎯 Planejado (v2.0)
- [ ] Editor visual de fluxos (flow builder)
- [ ] A/B testing para mensagens
- [ ] Segmentação avançada de contatos
- [ ] Chatbot com IA (GPT integration)
- [ ] Multi-idioma (i18n)
- [ ] App mobile (React Native)
- [ ] Integração com CRMs populares
- [ ] Sistema de afiliados
- [ ] Marketplace de templates
- [ ] Analytics avançado com funil de conversão

### 💡 Ideias Futuras
- [ ] Integração com Instagram Direct
- [ ] Integração com Discord
- [ ] Sistema de gamificação
- [ ] Automação com Zapier/Make
- [ ] Reconhecimento de voz
- [ ] OCR para documentos
- [ ] Video calls via Telegram
- [ ] Live chat para suporte

> **Contribua!** Se você tem ideias ou quer implementar algum recurso, abra uma issue ou pull request!

---

## ❓ Perguntas Frequentes (FAQ)

### Posso gerenciar múltiplos bots na mesma plataforma?
**Sim!** O sistema suporta múltiplos bots simultaneamente. Cada bot pode ter suas próprias configurações, planos de pagamento, comandos e administradores.

### Preciso ter conhecimento técnico para usar?
A interface foi desenvolvida para ser intuitiva e fácil de usar. No entanto, para instalação e configuração inicial, é recomendado conhecimento básico de Docker e linha de comando.

### O sistema funciona com grupos privados e canais?
Sim, o sistema funciona com grupos privados, grupos públicos e canais. O bot precisa ser adicionado como administrador com as permissões necessárias.

### Quais métodos de pagamento são suportados?
- **PIX**: Via Mercado Pago (Brasil)
- **Cartão de Crédito**: Via Stripe (internacional) ou Mercado Pago
- **Outros**: É possível adicionar novos gateways estendendo o código

### O sistema é escalável para muitos usuários?
Sim! A arquitetura foi projetada para escalar. Utilize Redis para cache e filas, configure load balancers e aumente recursos conforme necessário.

### Como funcionam os alertas programados?
Alertas são processados via filas (queues) e podem ser agendados para data/hora específica. O sistema verifica e envia automaticamente quando chega o momento.

### Posso personalizar as mensagens do bot?
Totalmente! Você pode personalizar:
- Mensagens de boas-vindas
- Comandos customizados
- Botões de redirecionamento
- Alertas e downsells
- Todas as interações com usuários

### O sistema registra logs das ações?
Sim, super admins têm acesso a logs detalhados de todas as ações realizadas no sistema, incluindo requisições HTTP, erros e eventos importantes.

### Posso usar em produção gratuitamente?
Sim! O sistema é open source sob licença MIT. Você pode usar comercialmente, mas é responsável por hospedagem, manutenção e suporte.

### Oferece suporte a 2FA?
Sim! O sistema possui autenticação de dois fatores integrada com Google Authenticator para maior segurança.

### Como faço para recuperar senha de usuário?
O sistema possui fluxo completo de recuperação de senha via e-mail. Configure o SMTP no `.env` para ativar.

### Posso integrar com outras APIs?
Sim! O Laravel facilita a integração com APIs externas. Você pode estender os services e criar novos controllers conforme necessário.

### O código está documentado?
Sim! O código segue padrões PSR-12 e possui comentários em pontos importantes. A documentação da API está neste README.

### Posso vender acesso à plataforma?
Sim! Sendo open source sob licença MIT, você pode usar comercialmente, inclusive vender acesso. Mas deve manter a licença original no código.

### Funciona em Windows/Mac/Linux?
Sim! Como utiliza Docker, funciona em qualquer sistema operacional que suporte Docker e Docker Compose.

### Quanto custa hospedar?
Depende do provedor e tráfego. Para começar, um VPS básico (~$10-20/mês) é suficiente. Para grandes volumes, considere cloud providers com auto-scaling.

### Preciso de servidor dedicado?
Não necessariamente. Pode usar VPS compartilhado para começar. Para produção com alto tráfego, considere servidores dedicados ou cloud.

### Como faço backup dos dados?
Faça backup regular do volume Docker `mysql_data` e dos arquivos de configuração (`.env`). Use comandos do MySQL para exportar o banco periodicamente.

### O sistema é seguro?
Sim! Implementa:
- JWT para autenticação
- 2FA opcional
- Criptografia bcrypt para senhas
- Middleware de autorização
- Proteção contra SQL Injection
- Rate limiting
- Validação de entrada

Mas em produção, sempre:
- Use HTTPS
- Altere secrets padrão
- Mantenha sistema atualizado
- Configure firewall adequado
- Monitore logs de segurança

## 📄 Licença

Este projeto é de código aberto e está disponível sob a **Licença MIT**.

```
MIT License

Copyright (c) 2025 Bot Telegram Platform

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## ⚖️ Aviso Legal

### Termos de Uso

Este software é fornecido "como está", sem garantias de qualquer tipo. Os autores não se responsabilizam por:
- Perda de dados ou lucros
- Uso indevido da plataforma
- Violação de termos de serviço de terceiros (Telegram, Mercado Pago, Stripe)
- Problemas legais relacionados ao uso comercial

### Compliance

Ao usar este sistema, você é responsável por:
- ✅ Cumprir os [Termos de Serviço do Telegram](https://telegram.org/tos)
- ✅ Respeitar a [Lei Geral de Proteção de Dados (LGPD)](http://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm) no Brasil
- ✅ Seguir as políticas dos gateways de pagamento
- ✅ Obter consentimento dos usuários para coleta de dados
- ✅ Implementar políticas de privacidade adequadas
- ✅ Não usar para spam ou atividades ilegais

### Privacidade

Este sistema coleta e armazena:
- Informações de contatos do Telegram
- Dados de transações financeiras
- Logs de atividades do sistema
- Informações de perfil de usuários

**Você deve**:
1. Informar os usuários sobre coleta de dados
2. Obter consentimento explícito (LGPD)
3. Proteger dados com medidas de segurança
4. Permitir que usuários solicitem remoção de dados
5. Não compartilhar dados sem autorização

### Limitações de Responsabilidade

- 🚫 Não garantimos disponibilidade 100% do sistema
- 🚫 Não nos responsabilizamos por problemas com APIs de terceiros
- 🚫 Não oferecemos suporte comercial oficial
- 🚫 Não garantimos compatibilidade futura
- 🚫 Mudanças nas APIs externas podem quebrar funcionalidades

### Uso Comercial

Você **pode**:
- ✅ Usar comercialmente
- ✅ Vender acesso à plataforma
- ✅ Modificar conforme necessário
- ✅ Criar produtos derivados

Você **deve**:
- ✅ Manter a licença MIT no código
- ✅ Dar crédito aos autores originais
- ✅ Não remover avisos de copyright

---

## 🙏 Agradecimentos

Este projeto foi possível graças a:

- **Laravel Community** - Framework PHP excepcional
- **React Community** - Biblioteca JavaScript incrível
- **Telegram Team** - API de bot fantástica
- **Mercado Pago** - Facilidade de integração de pagamentos
- **Stripe** - Gateway de pagamento confiável
- **Docker** - Simplificando deployment
- **Open Source Community** - Todas as bibliotecas utilizadas

### Bibliotecas e Dependências Principais

#### Backend
- [Laravel](https://laravel.com) - Taylor Otwell
- [JWT Auth](https://github.com/tymondesigns/jwt-auth) - Sean Tymon
- [Telegram Bot SDK](https://github.com/php-telegram-bot/core) - Longman
- [Mercado Pago SDK](https://github.com/mercadopago/dx-php) - Mercado Pago
- [Stripe SDK](https://github.com/stripe/stripe-php) - Stripe
- [Google2FA](https://github.com/antonioribeiro/google2fa) - Antonio Carlos Ribeiro
- [QR Code Generator](https://github.com/SimpleSoftwareIO/simple-qrcode) - Simple Software

#### Frontend
- [React](https://react.dev) - Meta
- [React Router](https://reactrouter.com) - Remix
- [Axios](https://axios-http.com) - Matt Zabriskie
- [Chart.js](https://www.chartjs.org) - Chart.js Team
- [Font Awesome](https://fontawesome.com) - Fonticons, Inc.

---

## 📞 Contato e Suporte

### Comunidade

- 💬 **Discussões**: Abra uma [Discussion](https://github.com/seu-usuario/bot-telegram/discussions) para perguntas gerais
- 🐛 **Issues**: Reporte bugs via [Issues](https://github.com/seu-usuario/bot-telegram/issues)
- 🔀 **Pull Requests**: Contribua com código via [Pull Requests](https://github.com/seu-usuario/bot-telegram/pulls)

### Recursos

- 📖 **Wiki**: [Documentação Completa](https://github.com/seu-usuario/bot-telegram/wiki)
- 🎥 **Tutoriais**: [Canal no YouTube](#) (em breve)
- 💼 **LinkedIn**: [Perfil do Desenvolvedor](#)
- 🌐 **Website**: [Site Oficial](#) (em breve)

### Suporte Comercial

Para suporte dedicado, consultoria ou desenvolvimento customizado:
- 📧 Email: seu-email@example.com
- 💼 Consultoria: [Agende uma reunião](#)

---

## 🌟 Star History

Se este projeto foi útil para você, considere dar uma ⭐ no repositório!

[![Star History Chart](https://api.star-history.com/svg?repos=seu-usuario/bot-telegram&type=Date)](https://star-history.com/#seu-usuario/bot-telegram&Date)

---

## 📊 Estatísticas do Projeto

![GitHub stars](https://img.shields.io/github/stars/seu-usuario/bot-telegram?style=social)
![GitHub forks](https://img.shields.io/github/forks/seu-usuario/bot-telegram?style=social)
![GitHub watchers](https://img.shields.io/github/watchers/seu-usuario/bot-telegram?style=social)
![GitHub contributors](https://img.shields.io/github/contributors/seu-usuario/bot-telegram)
![GitHub last commit](https://img.shields.io/github/last-commit/seu-usuario/bot-telegram)
![GitHub issues](https://img.shields.io/github/issues/seu-usuario/bot-telegram)
![GitHub pull requests](https://img.shields.io/github/issues-pr/seu-usuario/bot-telegram)

---

<div align="center">

### ⭐ Se este projeto te ajudou, considere dar uma estrela!

**Desenvolvido com ❤️ usando Laravel e React**

**Copyright © 2025 Bot Telegram Platform**

[⬆ Voltar ao topo](#-bot-telegram---plataforma-de-gerenciamento-completa)

</div>

