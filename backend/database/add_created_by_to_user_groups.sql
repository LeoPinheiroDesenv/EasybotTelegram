-- Migration: Adicionar campo created_by à tabela user_groups
-- Data: 2025-12-10

-- Verifica se a coluna já existe antes de adicionar
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'user_groups' 
    AND COLUMN_NAME = 'created_by'
);

-- Adiciona a coluna created_by se ela não existir
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `user_groups` 
     ADD COLUMN `created_by` BIGINT UNSIGNED NULL AFTER `active`,
     ADD CONSTRAINT `user_groups_created_by_foreign` 
     FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT "Coluna created_by já existe na tabela user_groups" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Versão alternativa sem verificação (descomente se preferir):
-- ALTER TABLE `user_groups` 
-- ADD COLUMN `created_by` BIGINT UNSIGNED NULL AFTER `active`,
-- ADD CONSTRAINT `user_groups_created_by_foreign` 
-- FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
