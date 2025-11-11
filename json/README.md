# Insomnia Collection - BotTelegram API

Esta pasta contém a collection do Insomnia com todos os endpoints da API BotTelegram.

## Como importar no Insomnia

1. Abra o Insomnia
2. Clique em **Application** → **Preferences** (ou `Ctrl/Cmd + ,`)
3. Vá na aba **Data**
4. Clique em **Import Data** → **From File**
5. Selecione o arquivo `Insomnia_Collection.json`
6. A collection será importada com todas as rotas organizadas

## Estrutura da Collection

A collection está organizada nas seguintes categorias:

### 🔐 Authentication
- **Login** - Fazer login e obter token
- **Get Current User** - Obter informações do usuário atual

### 🤖 Bots
- **Get All Bots** - Listar todos os bots
- **Get Bot by ID** - Obter bot por ID
- **Create Bot** - Criar novo bot
- **Update Bot** - Atualizar bot existente
- **Delete Bot** - Excluir bot
- **Validate Bot Token** - Validar token do bot
- **Initialize Bot** - Inicializar bot (iniciar Telegram bot)
- **Stop Bot** - Parar bot (parar Telegram bot)
- **Get Bot Status** - Obter status do bot

### 📇 Contacts
- **Get All Contacts** - Listar todos os contatos
- **Get Contact Stats** - Obter estatísticas de contatos
- **Get Latest Contacts** - Obter contatos mais recentes
- **Get Contact by ID** - Obter contato por ID
- **Create Contact** - Criar novo contato
- **Update Contact** - Atualizar contato
- **Delete Contact** - Excluir contato
- **Block Contact** - Bloquear contato

### 💳 Payment Plans
- **Get All Payment Plans** - Listar todos os planos de pagamento
- **Get Payment Plan by ID** - Obter plano de pagamento por ID
- **Create Payment Plan** - Criar novo plano de pagamento
- **Update Payment Plan** - Atualizar plano de pagamento
- **Delete Payment Plan** - Excluir plano de pagamento

### 👥 Users (Admin only)
- **Get All Users** - Listar todos os usuários
- **Get User by ID** - Obter usuário por ID
- **Create User** - Criar novo usuário
- **Update User** - Atualizar usuário
- **Delete User** - Excluir usuário

### 📋 Logs (Admin only)
- **Get All Logs** - Listar todos os logs
- **Get Log by ID** - Obter log por ID

### 🏥 Health Check
- **Health Check** - Verificar status do servidor

## Variáveis de Ambiente

A collection inclui um ambiente base com as seguintes variáveis:

- `base_url`: `http://localhost:5000/api` (URL base da API)
- `token`: Token JWT obtido após o login (será preenchido automaticamente)

## Como usar

1. **Faça login primeiro**: Execute a requisição **Login** na categoria **Authentication**
2. **Token capturado automaticamente**: O token será automaticamente salvo na variável de ambiente `token` após o login bem-sucedido
3. **Teste as rotas**: Agora você pode testar todas as outras rotas que requerem autenticação - o token será usado automaticamente

### ⚡ Captura Automática do Token

A requisição de **Login** está configurada para capturar automaticamente o token da resposta e salvá-lo na variável de ambiente `token`. Isso significa que:

- ✅ Você não precisa copiar e colar o token manualmente
- ✅ O token é atualizado automaticamente a cada login
- ✅ Todas as outras requisições usam automaticamente o token mais recente

**Nota**: Se a captura automática não funcionar, você pode:
1. Abrir a resposta do login
2. Copiar o valor do campo `token`
3. Ir em **Manage Environments** → **Base Environment**
4. Colar o token no campo `token`

## Notas Importantes

- A maioria das rotas requer autenticação via token JWT
- Rotas de **Users** e **Logs** requerem permissão de administrador
- O token expira após 24 horas
- A URL base padrão é `http://localhost:5000/api` - ajuste conforme necessário

## Exemplos de Uso

### Login
```json
POST /api/auth/login
{
  "email": "admin@admin.com",
  "password": "admin123"
}
```

### Criar Bot
```json
POST /api/bots
Authorization: Bearer {{ token }}
{
  "name": "Meu Bot",
  "token": "123456789:ABCdefGHIjklMNOpqrsTUVwxyz",
  "telegram_group_id": "@meugrupo"
}
```

### Listar Logs (Admin)
```json
GET /api/logs?limit=100&offset=0&level=error
Authorization: Bearer {{ token }}
```

