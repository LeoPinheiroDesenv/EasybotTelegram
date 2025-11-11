# Bot Telegram - Sistema de Gerenciamento de Usuários

Sistema completo de gerenciamento de usuários com autenticação e níveis de acesso.

## 🚀 Tecnologias

- **Frontend**: React.js
- **Backend**: Node.js com Express
- **Banco de Dados**: PostgreSQL
- **Containerização**: Docker & Docker Compose

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

Certifique-se de configurar as seguintes variáveis:

- `DB_HOST`: Host do PostgreSQL
- `DB_PORT`: Porta do PostgreSQL
- `DB_USER`: Usuário do banco de dados
- `DB_PASSWORD`: Senha do banco de dados
- `DB_NAME`: Nome do banco de dados
- `JWT_SECRET`: Chave secreta para JWT (use uma chave forte em produção)
- `PORT`: Porta do servidor backend
- `REACT_APP_API_URL`: URL da API para o frontend

## 📝 Notas

- O sistema utiliza JWT para autenticação
- As senhas são criptografadas usando bcrypt
- O banco de dados PostgreSQL é persistido em um volume Docker
- Em produção, certifique-se de alterar a `JWT_SECRET` e outras credenciais padrão

## 🐛 Troubleshooting

### Erro de conexão com o banco de dados

Verifique se o PostgreSQL está rodando e as credenciais estão corretas.

### Erro de permissão no Docker

Certifique-se de que o Docker tem permissões adequadas para acessar o diretório do projeto.

### Frontend não conecta ao backend

Verifique se a variável `REACT_APP_API_URL` está configurada corretamente e se ambos os serviços estão rodando.

## 📄 Licença

Este projeto é de código aberto e está disponível sob a licença MIT.

