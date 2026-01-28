# Script SQL - Criação das Tabelas

Este diretório contém o script SQL para criação de todas as tabelas do banco de dados MySQL 8.0.

## Arquivo

- `create_tables.sql` - Script completo para criação de todas as tabelas

## Como usar

### Opção 1: Via MySQL CLI

```bash
# Conectar ao container MySQL
docker-compose exec mysql mysql -u root -proot123

# Dentro do MySQL, executar:
source /var/www/database/create_tables.sql;
```

### Opção 2: Via docker-compose exec

```bash
# Executar o script diretamente
docker-compose exec -T mysql mysql -u root -proot123 < backend/database/create_tables.sql
```

### Opção 3: Copiar para o container e executar

```bash
# Copiar o arquivo para o container
docker cp backend/database/create_tables.sql bottelegram_mysql:/tmp/create_tables.sql

# Executar dentro do container
docker-compose exec mysql mysql -u root -proot123 bottelegram_db < /tmp/create_tables.sql
```

### Opção 4: Via Laravel Migrations (Recomendado)

```bash
# Usar as migrations do Laravel
docker-compose exec backend php artisan migrate --force
```

## Estrutura das Tabelas

### Tabelas Principais

1. **users** - Usuários do sistema
2. **bots** - Bots do Telegram
3. **contacts** - Contatos dos bots
4. **payment_cycles** - Ciclos de pagamento
5. **payment_plans** - Planos de pagamento
6. **payment_gateway_configs** - Configurações de gateways de pagamento
7. **transactions** - Transações de pagamento
8. **logs** - Logs do sistema

### Tabelas do Laravel

- **password_reset_tokens** - Tokens de reset de senha
- **sessions** - Sessões de usuários
- **cache** - Cache do sistema
- **cache_locks** - Locks do cache
- **jobs** - Jobs em fila
- **job_batches** - Lotes de jobs
- **failed_jobs** - Jobs que falharam

## Notas Importantes

1. O script cria o database `bottelegram_db` se não existir
2. Todas as tabelas usam charset `utf8mb4` e collation `utf8mb4_unicode_ci`
3. Foreign keys estão configuradas com `ON DELETE CASCADE` ou `ON DELETE RESTRICT` conforme apropriado
4. O usuário admin padrão é criado, mas a senha precisa ser atualizada usando o script `fix_admin_password.php`

## Após executar o script

1. Atualizar a senha do admin:
   ```bash
   docker-compose exec backend php fix_admin_password.php
   ```

2. Verificar se tudo está funcionando:
   ```bash
   docker-compose exec backend php artisan migrate:status
   ```

