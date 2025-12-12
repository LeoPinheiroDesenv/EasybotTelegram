-- Migration: Adiciona campo cpanel_cron_id à tabela cron_jobs
-- Data: 2025-12-12
-- Descrição: Adiciona campo para armazenar o ID do cron job no cPanel

-- Verifica se a tabela existe antes de adicionar a coluna
SET @table_exists = (
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'cron_jobs'
);

-- Adiciona a coluna cpanel_cron_id se a tabela existir
SET @sql = IF(@table_exists > 0,
    'ALTER TABLE `cron_jobs` 
     ADD COLUMN `cpanel_cron_id` INT(11) NULL DEFAULT NULL 
     COMMENT ''ID do cron job no cPanel'' 
     AFTER `is_system`',
    'SELECT ''Tabela cron_jobs não existe. Execute primeiro a migration create_cron_jobs_table.'' AS error'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica se a coluna foi adicionada com sucesso
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'Coluna cpanel_cron_id adicionada com sucesso!'
        ELSE 'Erro: Coluna cpanel_cron_id não foi adicionada.'
    END AS resultado
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'cron_jobs' 
AND column_name = 'cpanel_cron_id';
