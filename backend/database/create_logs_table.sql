-- =====================================================
-- Script para Criar Tabela logs
-- EasyBot Telegram - Sistema de Logging
-- =====================================================

USE bottelegram_db;

-- Verifica se a tabela já existe
SET @table_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = 'bottelegram_db' 
    AND TABLE_NAME = 'logs'
);

-- Remove a tabela se já existir (CUIDADO: apaga dados existentes!)
SET @sql = IF(@table_exists > 0, 
    'DROP TABLE IF EXISTS `logs`', 
    'SELECT "Tabela não existe, será criada" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Cria a tabela logs
CREATE TABLE `logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bot_id` BIGINT UNSIGNED NULL COMMENT 'ID do bot relacionado (nullable)',
    `level` VARCHAR(50) NOT NULL COMMENT 'Nível do log (info, warning, error, critical, debug)',
    `message` TEXT NOT NULL COMMENT 'Mensagem do log',
    `context` JSON NULL COMMENT 'Contexto adicional em formato JSON',
    `details` TEXT NULL COMMENT 'Detalhes adicionais do log',
    `user_email` VARCHAR(255) NULL COMMENT 'Email do usuário que gerou o log',
    `ip_address` VARCHAR(45) NULL COMMENT 'Endereço IP de origem',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `logs_bot_id_index` (`bot_id`),
    INDEX `logs_level_index` (`level`),
    INDEX `logs_created_at_index` (`created_at`),
    CONSTRAINT `logs_bot_id_foreign` 
        FOREIGN KEY (`bot_id`) 
        REFERENCES `bots` (`id`) 
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verifica se a tabela foi criada com sucesso
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME,
    TABLE_COLLATION
FROM 
    information_schema.TABLES 
WHERE 
    TABLE_SCHEMA = 'bottelegram_db' 
    AND TABLE_NAME = 'logs';

-- Mostra a estrutura da tabela
DESCRIBE `logs`;

-- Mostra os índices criados
SHOW INDEX FROM `logs`;

-- =====================================================
-- Script concluído!
-- =====================================================

