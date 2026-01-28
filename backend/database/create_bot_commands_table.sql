-- =====================================================
-- Script para Criar Tabela bot_commands
-- EasyBot Telegram - Comandos Personalizados dos Bots
-- =====================================================

USE bottelegram_db;

-- Verifica se a tabela já existe
SET @table_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = 'bottelegram_db' 
    AND TABLE_NAME = 'bot_commands'
);

-- Remove a tabela se já existir (CUIDADO: apaga dados existentes!)
SET @sql = IF(@table_exists > 0, 
    'DROP TABLE IF EXISTS `bot_commands`', 
    'SELECT "Tabela não existe, será criada" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Cria a tabela bot_commands
CREATE TABLE `bot_commands` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bot_id` BIGINT UNSIGNED NOT NULL,
    `command` VARCHAR(50) NOT NULL COMMENT 'Nome do comando sem barra (ex: info, sobre)',
    `response` TEXT NOT NULL COMMENT 'Resposta do comando',
    `description` TEXT NULL COMMENT 'Descrição do comando',
    `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se o comando está ativo',
    `usage_count` INT NOT NULL DEFAULT 0 COMMENT 'Contador de vezes que o comando foi usado',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `bot_commands_bot_id_index` (`bot_id`),
    INDEX `bot_commands_command_index` (`command`),
    UNIQUE KEY `bot_commands_bot_command_unique` (`bot_id`, `command`),
    CONSTRAINT `bot_commands_bot_id_foreign` 
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
    AND TABLE_NAME = 'bot_commands';

-- Mostra a estrutura da tabela
DESCRIBE `bot_commands`;

-- Mostra os índices criados
SHOW INDEX FROM `bot_commands`;

-- =====================================================
-- Script concluído!
-- =====================================================
