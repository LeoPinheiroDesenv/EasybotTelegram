# Scripts para Criação do Usuário Admin

Este diretório contém scripts para criar/atualizar o usuário administrador do sistema.

## Scripts Disponíveis

### 1. Script PHP (Recomendado) - `fix_admin_password.php`

**Localização:** `backend/fix_admin_password.php`

**Descrição:** Script PHP que cria ou atualiza o usuário admin com senha hasheada corretamente usando Bcrypt.

**Como usar:**
```bash
docker-compose exec backend php fix_admin_password.php
```

**Ou diretamente:**
```bash
cd backend
php fix_admin_password.php
```

**Características:**
- ✅ Cria o usuário se não existir
- ✅ Atualiza senha e informações se já existir
- ✅ Usa Bcrypt para hash da senha
- ✅ Verifica se a senha está correta
- ✅ Exibe informações do usuário criado

### 2. Script Shell - `create_admin.sh`

**Localização:** `backend/create_admin.sh`

**Descrição:** Script shell que executa o script PHP e exibe informações formatadas.

**Como usar:**
```bash
cd backend
./create_admin.sh
```

**Ou:**
```bash
bash backend/create_admin.sh
```

**Características:**
- ✅ Verifica se o container está rodando
- ✅ Executa o script PHP automaticamente
- ✅ Exibe mensagens formatadas
- ✅ Mostra credenciais ao final

### 3. Script SQL - `create_admin.sql`

**Localização:** `backend/database/create_admin.sql`

**Descrição:** Script SQL para criar o usuário admin diretamente no banco de dados.

**Como usar:**
```bash
# Executar via docker-compose
docker-compose exec -T mysql mysql -u root -proot123 bottelegram_db < backend/database/create_admin.sql

# Ou via MySQL CLI
docker-compose exec mysql mysql -u root -proot123 bottelegram_db
source /var/www/database/create_admin.sql;
```

**⚠️ IMPORTANTE:** 
- Este script cria o usuário, mas a senha precisa ser atualizada usando o script PHP
- O script SQL usa uma senha temporária que deve ser atualizada

## Credenciais Padrão

Após executar qualquer um dos scripts:

- **Email:** `admin@admin.com`
- **Senha:** `admin123`
- **Role:** `admin`
- **Status:** Ativo
- **2FA:** Desativado

## Recomendações de Segurança

1. **Altere a senha após o primeiro login**
2. **Ative o 2FA (Autenticação de Dois Fatores)**
3. **Use senhas fortes em produção**
4. **Não compartilhe as credenciais**

## Exemplo de Uso Completo

```bash
# 1. Criar o usuário admin
docker-compose exec backend php fix_admin_password.php

# 2. Verificar se foi criado
docker-compose exec backend php artisan tinker
# Dentro do tinker:
# $user = App\Models\User::where('email', 'admin@admin.com')->first();
# echo $user->name . " - " . $user->role;

# 3. Testar login via API
curl -X POST http://172.18.0.3:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@admin.com","password":"admin123"}'
```

## Troubleshooting

### Erro: "Container não está rodando"
```bash
docker-compose up -d
```

### Erro: "Database connection failed"
```bash
# Verificar se o MySQL está rodando
docker-compose ps mysql

# Verificar logs
docker-compose logs mysql
```

### Erro: "User already exists"
O script atualiza automaticamente se o usuário já existir. Não é necessário remover manualmente.

### Senha não funciona após criar
Execute o script PHP novamente para garantir que a senha seja hasheada corretamente:
```bash
docker-compose exec backend php fix_admin_password.php
```

