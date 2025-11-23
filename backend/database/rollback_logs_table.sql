-- =====================================================
-- Script para Remover Tabela logs
-- EasyBot Telegram - Rollback
-- =====================================================

USE bottelegram_db;

-- Remove a tabela logs
DROP TABLE IF EXISTS `logs`;

-- Verifica se a tabela foi removida
SELECT 
    TABLE_NAME
FROM 
    information_schema.TABLES 
WHERE 
    TABLE_SCHEMA = 'bottelegram_db' 
    AND TABLE_NAME = 'logs';

-- Se não retornar nenhum resultado, a tabela foi removida com sucesso

