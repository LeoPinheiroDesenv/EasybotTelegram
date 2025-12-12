# EasybotTelegram - Sistema Completo de Gerenciamento de Bots Telegram

Sistema completo e profissional para criação, gerenciamento e monetização de bots do Telegram com integração de pagamentos, automação de mensagens e muito mais.

## 🚀 Tecnologias

### Backend
- **Framework**: Laravel 12 (PHP 8.2+)
- **Banco de Dados**: MySQL/PostgreSQL
- **Autenticação**: JWT (tymon/jwt-auth)
- **Pagamentos**: 
  - Mercado Pago (PIX e Cartão)
  - Stripe (Cartão de Crédito)
- **Telegram**: longman/telegram-bot
- **Segurança**: Google 2FA (pragmarx/google2fa)
- **QR Code**: simplesoftwareio/simple-qrcode

### Frontend
- **Framework**: React.js
- **Roteamento**: React Router
- **UI**: Componentes customizados com CSS
- **Build**: Vite

### Infraestrutura
- **Containerização**: Docker & Docker Compose
- **Armazenamento**: Sistema de Storage configurável (Local, FTP, SFTP)

## ✨ Funcionalidades Principais

### 🤖 Gerenciamento de Bots
- ✅ Criação e edição de múltiplos bots do Telegram
- ✅ Configuração de mensagem de boas-vindas personalizada
- ✅ Gerenciamento de comandos customizados
- ✅ Configuração de administradores do bot
- ✅ Gerenciamento de grupos do Telegram
- ✅ Integração com BotFather para criação automática
- ✅ Botões de redirecionamento personalizados
- ✅ Sistema de alertas e notificações
- ✅ Downsell automático

### 💳 Sistema de Pagamentos
- ✅ **PIX via Mercado Pago**
  - Geração automática de QR Code PIX
  - Código PIX copia e cola
  - Verificação automática de pagamentos
  - Notificação de expiração de PIX
  - **Código PIX preservado exatamente como recebido do Mercado Pago** (sem modificações)
- ✅ **Cartão de Crédito**
  - Integração com Mercado Pago
  - Integração com Stripe
  - Processamento seguro de pagamentos
- ✅ Planos de pagamento personalizáveis
- ✅ Ciclos de pagamento (mensal, anual, etc.)
- ✅ Histórico completo de transações
- ✅ Status de pagamento em tempo real
- ✅ Webhook para confirmação automática

### 👥 Gerenciamento de Usuários e Contatos
- ✅ Sistema completo de autenticação (JWT)
- ✅ Níveis de acesso (Admin, Super Admin, Usuário)
- ✅ Grupos de usuários com permissões customizadas
- ✅ Gerenciamento de contatos do Telegram
- ✅ Histórico de interações
- ✅ Status de usuários no Telegram
- ✅ Perfil do usuário editável

### 📊 Dashboard e Relatórios
- ✅ Dashboard com métricas em tempo real
- ✅ Faturamento e relatórios financeiros
- ✅ Logs de atividades do sistema
- ✅ Visualização de logs do Laravel (apenas super-admin)
- ✅ Status de pagamentos
- ✅ Estatísticas de bots e usuários

### ⚙️ Configurações e Administração
- ✅ **Cron Jobs**
  - Criação e gerenciamento de tarefas agendadas
  - **Integração automática com cPanel** (criação/atualização/remoção automática)
  - Teste de conexão com cPanel
  - Sincronização com cPanel
- ✅ **Logs do Laravel**
  - Visualização de logs em tempo real
  - Filtros por nível (INFO, ERROR, WARNING, etc.)
  - Busca nos logs
  - Exclusão de arquivos de log
  - Teste de conexão com cPanel
- ✅ Configuração de gateways de pagamento
- ✅ Gerenciamento de armazenamento (Local, FTP, SFTP)
- ✅ Execução de comandos Artisan via interface web
- ✅ Configurações de segurança (2FA, senhas, etc.)
- ✅ Gerenciamento FTP/SFTP

### 🔐 Segurança
- ✅ Autenticação JWT
- ✅ Autenticação de dois fatores (2FA)
- ✅ Níveis de acesso granulares
- ✅ Proteção de rotas por permissões
- ✅ Logs de segurança
- ✅ Reset de senha por email

### 📱 Integração Telegram
- ✅ Webhook para recebimento de atualizações
- ✅ Polling como alternativa ao webhook
- ✅ Processamento de comandos
- ✅ Envio de mensagens personalizadas
- ✅ Gerenciamento de grupos
- ✅ Detecção automática de migração de grupos
- ✅ Envio de QR Code PIX via Telegram

### 🔄 Automações
- ✅ Verificação automática de pagamentos pendentes
- ✅ Verificação de expiração de PIX
- ✅ Envio automático de alertas agendados
- ✅ Processamento de downsell
- ✅ Atualização de status de contatos no Telegram
- ✅ Polling automático do Telegram

## 📋 Pré-requisitos

- PHP 8.2 ou superior
- Composer
- Node.js 18+ e npm
- MySQL 8.0+ ou PostgreSQL 13+
- Docker e Docker Compose (opcional, para desenvolvimento)
- Conta no Mercado Pago (para pagamentos PIX)
- Token de API do Telegram (BotFather)
- Token de API do cPanel (opcional, para cron jobs automáticos)

## 🛠️ Instalação

### 1. Clone o repositório

```bash
git clone <repository-url>
cd EasybotTelegram
```

### 2. Configure o Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

### 3. Configure as variáveis de ambiente

Edite o arquivo `backend/.env`:

```env
# Banco de Dados
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=easybot
DB_USERNAME=root
DB_PASSWORD=

# JWT
JWT_SECRET=your-jwt-secret-key
JWT_TTL=60

# Telegram
TELEGRAM_BOT_TOKEN=your-telegram-bot-token

# Mercado Pago
MERCADOPAGO_ACCESS_TOKEN=your-mercadopago-access-token
MERCADOPAGO_PUBLIC_KEY=your-mercadopago-public-key

# Stripe (opcional)
STRIPE_KEY=your-stripe-key
STRIPE_SECRET=your-stripe-secret

# cPanel (opcional - para cron jobs automáticos)
CPANEL_HOST=seu-dominio.com
CPANEL_USERNAME=seu_usuario
CPANEL_API_TOKEN=seu_token_api
CPANEL_PORT=2083
CPANEL_USE_SSL=true

# Aplicação
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000
```

### 4. Execute as migrações

```bash
php artisan migrate
php artisan db:seed
```

### 5. Configure o Frontend

```bash
cd ../frontend
npm install
cp .env.example .env
```

Edite o arquivo `frontend/.env`:

```env
REACT_APP_API_URL=http://localhost:8000/api
```

### 6. Inicie o servidor de desenvolvimento

**Backend:**
```bash
cd backend
php artisan serve
```

**Frontend:**
```bash
cd frontend
npm start
```

### 7. Acesse a aplicação

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000/api
- **Health Check**: http://localhost:8000/api/health

## 🐳 Instalação com Docker

### 1. Configure as variáveis de ambiente

Copie e edite os arquivos `.env` conforme necessário.

### 2. Inicie os containers

```bash
docker-compose up -d
```

### 3. Execute as migrações

```bash
docker-compose exec backend php artisan migrate
docker-compose exec backend php artisan db:seed
```

## 👤 Credenciais Padrão

Após executar o seeder, você terá acesso com:

- **Email**: admin@admin.com
- **Senha**: admin123
- **Nível**: Super Admin

⚠️ **IMPORTANTE**: Altere a senha padrão imediatamente após o primeiro acesso!

## 📁 Estrutura do Projeto

```
EasybotTelegram/
├── backend/                    # Aplicação Laravel
│   ├── app/
│   │   ├── Console/Commands/   # Comandos Artisan
│   │   ├── Http/Controllers/   # Controladores
│   │   ├── Models/             # Modelos Eloquent
│   │   ├── Services/           # Serviços de negócio
│   │   └── Jobs/               # Jobs em fila
│   ├── config/                 # Configurações
│   ├── database/
│   │   ├── migrations/         # Migrações
│   │   └── seeders/            # Seeders
│   ├── routes/                 # Rotas da API
│   └── storage/logs/           # Logs do Laravel
├── frontend/                   # Aplicação React
│   ├── src/
│   │   ├── components/         # Componentes React
│   │   ├── pages/              # Páginas
│   │   ├── contexts/           # Contextos React
│   │   └── hooks/              # Hooks customizados
│   └── public/
├── docker-compose.yml          # Configuração Docker
└── README.md
```

## 🔐 API Endpoints Principais

### Autenticação
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `POST /api/auth/refresh` - Atualizar token
- `GET /api/auth/me` - Usuário atual
- `POST /api/auth/forgot-password` - Solicitar reset de senha
- `POST /api/auth/reset-password` - Resetar senha

### Bots
- `GET /api/bots` - Listar bots
- `POST /api/bots` - Criar bot
- `GET /api/bots/{id}` - Obter bot
- `PUT /api/bots/{id}` - Atualizar bot
- `DELETE /api/bots/{id}` - Excluir bot

### Pagamentos
- `POST /api/payments/pix/generate` - Gerar QR Code PIX
- `POST /api/payments/card/process` - Processar pagamento com cartão
- `GET /api/payments/status/{id}` - Status do pagamento
- `POST /api/payments/webhook/mercadopago` - Webhook Mercado Pago
- `POST /api/payments/webhook/stripe` - Webhook Stripe

### Cron Jobs
- `GET /api/cron-jobs` - Listar cron jobs
- `POST /api/cron-jobs` - Criar cron job
- `PUT /api/cron-jobs/{id}` - Atualizar cron job
- `DELETE /api/cron-jobs/{id}` - Excluir cron job
- `POST /api/cron-jobs/{id}/sync-cpanel` - Sincronizar com cPanel

### Logs do Laravel
- `GET /api/laravel-logs` - Listar logs
- `GET /api/laravel-logs/{filename}` - Visualizar log
- `DELETE /api/laravel-logs/{filename}` - Deletar log
- `POST /api/laravel-logs/test-cpanel` - Testar conexão cPanel

## 🔒 Níveis de Acesso

- **super_admin**: Acesso total ao sistema, incluindo logs do Laravel e configurações avançadas
- **admin**: Acesso administrativo completo, exceto logs do Laravel
- **user**: Acesso básico, visualização de dados próprios

## ⚙️ Configuração de Cron Jobs Automáticos no cPanel

O sistema suporta criação automática de cron jobs no cPanel. Veja a documentação completa em:
- `backend/CONFIGURACAO_CPANEL_AUTOMATICO.md`

### Configuração Rápida

1. Obtenha um token de API do cPanel
2. Configure as variáveis no `.env`:
   ```env
   CPANEL_HOST=seu-dominio.com
   CPANEL_USERNAME=seu_usuario
   CPANEL_API_TOKEN=seu_token
   CPANEL_PORT=2083
   CPANEL_USE_SSL=true
   ```
3. Crie/atualize cron jobs pela interface - eles serão criados automaticamente no cPanel

## 📱 Comandos Artisan

### Comandos de Pagamento
```bash
php artisan payments:check-pending    # Verifica pagamentos pendentes
php artisan pix:check-expiration      # Verifica PIX expirados
php artisan check:group-link-expiration  # Verifica expiração de links de grupo e notifica usuários
```

### Comandos do Telegram
```bash
php artisan telegram:polling          # Inicia polling do Telegram
php artisan contacts:update-status    # Atualiza status dos contatos
```

### Comandos de Marketing
```bash
php artisan alerts:process            # Processa alertas agendados
```

## 🎯 Funcionalidades Especiais

### Código PIX Preservado
O sistema preserva o código PIX **exatamente como recebido do Mercado Pago**, sem modificações. Isso garante que o código funcione corretamente com os aplicativos bancários.

### Integração Automática com cPanel
- Criação automática de cron jobs no cPanel
- Atualização automática quando você edita um cron job
- Remoção automática quando você deleta um cron job
- Teste de conexão com cPanel
- Sincronização manual quando necessário

### Logs do Laravel
- Visualização de logs em tempo real
- Filtros por nível de log
- Busca nos logs
- Exclusão de arquivos de log
- Teste de conexão com cPanel

### Webhook Inteligente
- Processamento automático de confirmações de pagamento
- Detecção de migração de grupos do Telegram
- Atualização automática de status
- Envio automático de links de grupo após pagamento

## 🐛 Troubleshooting

### Erro ao gerar código PIX
- Verifique se as credenciais do Mercado Pago estão corretas
- Certifique-se de que a conta tem uma chave PIX cadastrada
- Verifique os logs em `storage/logs/laravel.log`

### Cron jobs não são criados no cPanel
- Verifique as credenciais do cPanel no `.env`
- Teste a conexão pela tela de Logs do Laravel
- Verifique se o token tem permissões de Cron
- Alguns servidores podem ter módulos Perl desabilitados - nesse caso, crie os cron jobs manualmente

### Erro de conexão com Telegram
- Verifique se o token do bot está correto
- Certifique-se de que o webhook está configurado ou use polling
- Verifique os logs para mais detalhes

### Frontend não conecta ao backend
- Verifique se `REACT_APP_API_URL` está correto
- Certifique-se de que o CORS está configurado
- Verifique se ambos os servidores estão rodando

## 📚 Documentação Adicional

- `backend/CONFIGURACAO_CPANEL_AUTOMATICO.md` - Configuração de cron jobs no cPanel
- `backend/CORRECAO_CPANEL_CRON_JOBS.md` - Correções de problemas com cron jobs
- `backend/CORRECAO_WEBHOOK_MERCADOPAGO.md` - Correções de webhook do Mercado Pago
- `backend/CORRECAO_CODIGO_PIX_INCORRETO.md` - Correções de código PIX

## 🔄 Atualizações Recentes

### Versão Atual
- ✅ Código PIX preservado exatamente como recebido do Mercado Pago
- ✅ Tratamento de erros melhorado com mensagens claras ao usuário
- ✅ Integração automática com cPanel para cron jobs
- ✅ Tela de visualização de logs do Laravel
- ✅ Teste de conexão com cPanel
- ✅ Sincronização de cron jobs com cPanel
- ✅ Detecção automática de migração de grupos do Telegram
- ✅ Logs detalhados para diagnóstico

## 📄 Licença

Este projeto é de código aberto e está disponível sob a licença MIT.

## 🤝 Suporte

Para suporte, verifique os logs em:
- `backend/storage/logs/laravel.log`
- Tela de Logs do Laravel (apenas super-admin)

## 📝 Notas Importantes

- O sistema utiliza JWT para autenticação
- As senhas são criptografadas usando bcrypt
- Em produção, altere todas as credenciais padrão
- Configure adequadamente as variáveis de ambiente
- Faça backup regular do banco de dados
- Monitore os logs regularmente
