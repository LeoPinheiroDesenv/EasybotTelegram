-- =====================================================
-- Script SQL para adicionar campo created_by à tabela users
-- Equivalente à migration: 2025_12_10_003231_add_created_by_to_users_table.php
-- =====================================================

-- Verificar se a coluna já existe antes de adicionar
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'created_by'
);

-- Adicionar coluna created_by se não existir
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `users` 
     ADD COLUMN `created_by` BIGINT UNSIGNED NULL 
     AFTER `user_group_id`',
    'SELECT "Coluna created_by já existe na tabela users" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar se a foreign key já existe antes de adicionar
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'created_by'
    AND REFERENCED_TABLE_NAME = 'users'
);

-- Adicionar foreign key se não existir
SET @sql_fk = IF(@fk_exists = 0,
    'ALTER TABLE `users` 
     ADD CONSTRAINT `users_created_by_foreign` 
     FOREIGN KEY (`created_by`) 
     REFERENCES `users` (`id`) 
     ON DELETE SET NULL',
    'SELECT "Foreign key para created_by já existe" AS message'
);

PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- Verificar resultado
SELECT 
    'Campo created_by adicionado com sucesso!' AS message,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'users' 
AND COLUMN_NAME = 'created_by';

