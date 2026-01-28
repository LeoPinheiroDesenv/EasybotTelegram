# Scripts SQL - Tabela bot_commands

## Descrição

Scripts SQL para criar e remover manualmente a tabela `bot_commands` que armazena comandos personalizados dos bots do Telegram.

## Estrutura da Tabela

A tabela `bot_commands` possui os seguintes campos:

- `id` - ID único do comando
- `bot_id` - Referência ao bot (foreign key para `bots.id`)
- `command` - Nome do comando sem barra (ex: "info", "sobre")
- `response` - Texto da resposta do comando
- `description` - Descrição opcional do comando
- `active` - Se o comando está ativo (boolean)
- `usage_count` - Contador de vezes que o comando foi usado
- `created_at` - Data de criação
- `updated_at` - Data de atualização

## Índices e Constraints

- **Índices**: `bot_id`, `command`
- **Unique**: `(bot_id, command)` - Um bot não pode ter comandos duplicados
- **Foreign Key**: `bot_id` referencia `bots.id` com `ON DELETE CASCADE`

## Como Usar

### Criar a Tabela

#### Opção 1: Via MySQL CLI
```bash
mysql -u root -p bottelegram_db < database/create_bot_commands_table.sql
```

#### Opção 2: Via Docker
```bash
docker-compose exec mysql mysql -u root -proot123 bottelegram_db < backend/database/create_bot_commands_table.sql
```

#### Opção 3: Via Laravel Migration (Recomendado)
```bash
cd backend
php artisan migrate
```

### Remover a Tabela

#### Opção 1: Via MySQL CLI
```bash
mysql -u root -p bottelegram_db < database/rollback_bot_commands_table.sql
```

#### Opção 2: Via Docker
```bash
docker-compose exec mysql mysql -u root -proot123 bottelegram_db < backend/database/rollback_bot_commands_table.sql
```

#### Opção 3: Via Laravel Migration
```bash
cd backend
php artisan migrate:rollback --step=1
```

## Verificar Tabela

### Verificar se existe
```sql
SELECT TABLE_NAME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'bottelegram_db' 
AND TABLE_NAME = 'bot_commands';
```

### Ver estrutura
```sql
DESCRIBE bot_commands;
```

### Ver dados
```sql
SELECT * FROM bot_commands;
```

## Exemplos de Uso

### Inserir Comando Manualmente
```sql
INSERT INTO bot_commands (bot_id, command, response, description, active) 
VALUES (1, 'info', 'Este é um bot de exemplo!', 'Mostra informações do bot', 1);
```

### Listar Comandos de um Bot
```sql
SELECT * FROM bot_commands WHERE bot_id = 1 AND active = 1;
```

### Atualizar Resposta de um Comando
```sql
UPDATE bot_commands 
SET response = 'Nova resposta', updated_at = NOW() 
WHERE bot_id = 1 AND command = 'info';
```

### Desativar Comando
```sql
UPDATE bot_commands 
SET active = 0, updated_at = NOW() 
WHERE bot_id = 1 AND command = 'info';
```

### Ver Comandos Mais Usados
```sql
SELECT bot_id, command, usage_count 
FROM bot_commands 
WHERE active = 1 
ORDER BY usage_count DESC 
LIMIT 10;
```

## Notas Importantes

- O campo `command` não deve conter a barra `/` (ex: "info" e não "/info")
- O comando é único por bot (não pode ter dois comandos "info" para o mesmo bot)
- Quando um bot é deletado, seus comandos são deletados automaticamente (CASCADE)
- O campo `usage_count` é incrementado automaticamente quando o comando é usado

## Relacionamentos

- **bot_commands.bot_id** → **bots.id** (Many-to-One)
- Um bot pode ter vários comandos
- Um comando pertence a apenas um bot

