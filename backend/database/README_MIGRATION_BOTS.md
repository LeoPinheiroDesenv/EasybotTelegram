# Scripts SQL - Migration de Mensagens e Configurações dos Bots

Este diretório contém scripts SQL equivalentes à migration Laravel `2025_11_15_203808_add_messages_and_settings_to_bots_table.php`.

## Arquivos

- `add_messages_and_settings_to_bots_table.sql` - Script para adicionar os campos
- `rollback_messages_and_settings_from_bots_table.sql` - Script para remover os campos (rollback)

## Campos Adicionados

### Mensagens de Boas-vindas
- `initial_message` (TEXT) - Mensagem inicial
- `top_message` (TEXT) - Mensagem superior
- `button_message` (VARCHAR(255)) - Mensagem do botão
- `activate_cta` (TINYINT(1)) - Ativar CTA (padrão: 0/false)

### URLs de Mídia
- `media_1_url` (VARCHAR(255)) - URL da mídia 1
- `media_2_url` (VARCHAR(255)) - URL da mídia 2
- `media_3_url` (VARCHAR(255)) - URL da mídia 3

### Configurações de Privacidade
- `request_email` (TINYINT(1)) - Solicitar e-mail (padrão: 0/false)
- `request_phone` (TINYINT(1)) - Solicitar telefone (padrão: 0/false)
- `request_language` (TINYINT(1)) - Solicitar idioma (padrão: 0/false)

### Configurações de Pagamento
- `payment_method` (VARCHAR(255)) - Método de pagamento (padrão: 'credit_card')

### Status de Ativação
- `activated` (TINYINT(1)) - Bot ativado (padrão: 0/false)

## Como Usar

### Aplicar a Migration (Adicionar Campos)

#### Via MySQL CLI:
```bash
mysql -u root -p bottelegram_db < database/add_messages_and_settings_to_bots_table.sql
```

#### Via Docker:
```bash
docker-compose exec mysql mysql -u root -proot123 bottelegram_db < backend/database/add_messages_and_settings_to_bots_table.sql
```

#### Via MySQL Workbench ou phpMyAdmin:
1. Abra o arquivo `add_messages_and_settings_to_bots_table.sql`
2. Execute o conteúdo no banco de dados `bottelegram_db`

### Reverter a Migration (Remover Campos)

#### Via MySQL CLI:
```bash
mysql -u root -p bottelegram_db < database/rollback_messages_and_settings_from_bots_table.sql
```

#### Via Docker:
```bash
docker-compose exec mysql mysql -u root -proot123 bottelegram_db < backend/database/rollback_messages_and_settings_from_bots_table.sql
```

## Verificação

Após executar o script, você pode verificar se os campos foram adicionados:

```sql
DESCRIBE bots;
```

Ou:

```sql
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'bottelegram_db'
  AND TABLE_NAME = 'bots'
  AND COLUMN_NAME IN (
    'initial_message',
    'top_message',
    'button_message',
    'activate_cta',
    'media_1_url',
    'media_2_url',
    'media_3_url',
    'request_email',
    'request_phone',
    'request_language',
    'payment_method',
    'activated'
  );
```

## Notas Importantes

1. **Backup**: Sempre faça backup do banco de dados antes de executar scripts SQL diretamente
2. **Ordem**: Os campos são adicionados na ordem especificada usando `AFTER`
3. **Valores Padrão**: Todos os campos booleanos têm valor padrão `0` (false)
4. **Nullable**: Campos de texto e URLs são nullable (podem ser NULL)
5. **Compatibilidade**: O script usa sintaxe MySQL padrão

## Equivalência com Laravel Migration

Este script SQL é equivalente à migration Laravel:
- `database/migrations/2025_11_15_203808_add_messages_and_settings_to_bots_table.php`

A migration Laravel pode ser executada com:
```bash
php artisan migrate
```

E revertida com:
```bash
php artisan migrate:rollback
```

## Troubleshooting

### Erro: "Duplicate column name"
Se você receber este erro, significa que os campos já existem. Verifique com:
```sql
DESCRIBE bots;
```

### Erro: "Unknown database"
Certifique-se de que o banco de dados `bottelegram_db` existe:
```sql
SHOW DATABASES;
CREATE DATABASE IF NOT EXISTS bottelegram_db;
```

### Erro: "Table doesn't exist"
Certifique-se de que a tabela `bots` existe:
```sql
SHOW TABLES;
```

