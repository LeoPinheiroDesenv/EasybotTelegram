# Scripts SQL - Tabela logs

## Descrição

Scripts SQL para criar e remover manualmente a tabela `logs` que armazena todos os logs da aplicação.

## Estrutura da Tabela

A tabela `logs` possui os seguintes campos:

- `id` - ID único do log
- `bot_id` - Referência ao bot (foreign key para `bots.id`, nullable)
- `level` - Nível do log (info, warning, error, critical, debug)
- `message` - Mensagem do log
- `context` - Contexto adicional em formato JSON (nullable)
- `details` - Detalhes adicionais do log (nullable)
- `user_email` - Email do usuário que gerou o log (nullable)
- `ip_address` - Endereço IP de origem (nullable)
- `created_at` - Data de criação
- `updated_at` - Data de atualização

## Índices e Constraints

- **Índices**: `bot_id`, `level`, `created_at`
- **Foreign Key**: `bot_id` referencia `bots.id` com `ON DELETE CASCADE`
- **Níveis de log suportados**: info, warning, error, critical, debug

## Como Usar

### Criar a Tabela

#### Opção 1: Via MySQL CLI
```bash
mysql -u root -p bottelegram_db < database/create_logs_table.sql
```

#### Opção 2: Via Docker
```bash
docker-compose exec mysql mysql -u root -proot123 bottelegram_db < backend/database/create_logs_table.sql
```

#### Opção 3: Via Laravel Migration (Recomendado)
```bash
cd backend
php artisan migrate
```

### Remover a Tabela

#### Opção 1: Via MySQL CLI
```bash
mysql -u root -p bottelegram_db < database/rollback_logs_table.sql
```

#### Opção 2: Via Docker
```bash
docker-compose exec mysql mysql -u root -proot123 bottelegram_db < backend/database/rollback_logs_table.sql
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
AND TABLE_NAME = 'logs';
```

### Ver estrutura
```sql
DESCRIBE logs;
```

### Ver dados
```sql
SELECT * FROM logs ORDER BY created_at DESC LIMIT 10;
```

### Contar logs por nível
```sql
SELECT level, COUNT(*) as total 
FROM logs 
GROUP BY level 
ORDER BY total DESC;
```

### Contar logs por bot
```sql
SELECT bot_id, COUNT(*) as total 
FROM logs 
WHERE bot_id IS NOT NULL 
GROUP BY bot_id 
ORDER BY total DESC;
```

## Exemplos de Uso

### Inserir Log Manualmente
```sql
INSERT INTO logs (bot_id, level, message, context, user_email, ip_address) 
VALUES (
    1, 
    'info', 
    'Bot inicializado manualmente', 
    '{"source": "manual"}', 
    'admin@admin.com', 
    '127.0.0.1'
);
```

### Buscar Logs de Erro Recentes
```sql
SELECT * 
FROM logs 
WHERE level = 'error' 
AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY created_at DESC;
```

### Buscar Logs de um Bot Específico
```sql
SELECT * 
FROM logs 
WHERE bot_id = 1 
ORDER BY created_at DESC 
LIMIT 50;
```

### Buscar Logs por Usuário
```sql
SELECT * 
FROM logs 
WHERE user_email = 'admin@admin.com' 
ORDER BY created_at DESC;
```

### Estatísticas de Logs
```sql
-- Total de logs
SELECT COUNT(*) as total_logs FROM logs;

-- Logs por nível
SELECT level, COUNT(*) as total 
FROM logs 
GROUP BY level;

-- Logs por dia (últimos 7 dias)
SELECT DATE(created_at) as date, COUNT(*) as total 
FROM logs 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Top 10 bots com mais logs
SELECT bot_id, COUNT(*) as total_logs 
FROM logs 
WHERE bot_id IS NOT NULL 
GROUP BY bot_id 
ORDER BY total_logs DESC 
LIMIT 10;
```

### Limpar Logs Antigos
```sql
-- Remover logs com mais de 30 dias
DELETE FROM logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Remover logs de um nível específico com mais de 7 dias
DELETE FROM logs 
WHERE level = 'debug' 
AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

## Notas Importantes

- A tabela `logs` é populada automaticamente pelo sistema de logging
- Logs podem ser criados via `LogService` ou `Log` Facade do Laravel
- Quando um bot é deletado, seus logs são deletados automaticamente (CASCADE)
- O campo `context` armazena dados em formato JSON
- O campo `ip_address` suporta IPv4 e IPv6 (VARCHAR(45))
- Índices foram criados para otimizar consultas por `bot_id`, `level` e `created_at`

## Relacionamentos

- **logs.bot_id** → **bots.id** (Many-to-One, nullable)
- Um bot pode ter vários logs
- Um log pode pertencer a um bot ou ser geral (bot_id = NULL)

## Performance

Para melhorar a performance em tabelas com muitos logs:

1. **Índices**: Já estão criados em `bot_id`, `level` e `created_at`
2. **Particionamento**: Considere particionar por data se a tabela crescer muito
3. **Limpeza**: Configure limpeza automática de logs antigos
4. **Arquivamento**: Considere arquivar logs antigos em outra tabela

## Manutenção

### Verificar tamanho da tabela
```sql
SELECT 
    TABLE_NAME,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size (MB)',
    TABLE_ROWS
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'bottelegram_db' 
AND TABLE_NAME = 'logs';
```

### Otimizar tabela
```sql
OPTIMIZE TABLE logs;
```

### Analisar índices
```sql
SHOW INDEX FROM logs;
```

