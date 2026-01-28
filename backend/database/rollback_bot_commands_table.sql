-- =====================================================
-- Script para Remover Tabela bot_commands
-- EasyBot Telegram - Rollback
-- =====================================================

USE bottelegram_db;

-- Remove a tabela bot_commands
DROP TABLE IF EXISTS `bot_commands`;

-- Verifica se a tabela foi removida
SELECT 
    TABLE_NAME
FROM 
    information_schema.TABLES 
WHERE 
    TABLE_SCHEMA = 'bottelegram_db' 
    AND TABLE_NAME = 'bot_commands';

-- Se não retornar nenhum resultado, a tabela foi removida com sucesso

