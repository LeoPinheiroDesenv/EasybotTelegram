-- =====================================================
-- Script SQL para adicionar campos de mensagens e configurações à tabela bots
-- Migration: 2025_11_15_203808_add_messages_and_settings_to_bots_table
-- =====================================================

USE bottelegram_db;

-- =====================================================
-- Verificar se os campos já existem antes de adicionar
-- =====================================================
-- Execute este SELECT primeiro para verificar quais campos já existem:
/*
SELECT COLUMN_NAME 
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
*/

-- =====================================================
-- Adicionar campos de mensagens e configurações
-- =====================================================
-- IMPORTANTE: Se algum campo já existir, você receberá um erro.
-- Nesse caso, comente ou remova a linha do campo que já existe.

-- Mensagens de boas-vindas
ALTER TABLE `bots` 
ADD COLUMN `initial_message` TEXT NULL AFTER `telegram_group_id`,
ADD COLUMN `top_message` TEXT NULL AFTER `initial_message`,
ADD COLUMN `button_message` VARCHAR(255) NULL AFTER `top_message`,
ADD COLUMN `activate_cta` TINYINT(1) NOT NULL DEFAULT 0 AFTER `button_message`;

-- URLs de mídia
ALTER TABLE `bots`
ADD COLUMN `media_1_url` VARCHAR(255) NULL AFTER `activate_cta`,
ADD COLUMN `media_2_url` VARCHAR(255) NULL AFTER `media_1_url`,
ADD COLUMN `media_3_url` VARCHAR(255) NULL AFTER `media_2_url`;

-- Configurações de privacidade
ALTER TABLE `bots`
ADD COLUMN `request_email` TINYINT(1) NOT NULL DEFAULT 0 AFTER `media_3_url`,
ADD COLUMN `request_phone` TINYINT(1) NOT NULL DEFAULT 0 AFTER `request_email`,
ADD COLUMN `request_language` TINYINT(1) NOT NULL DEFAULT 0 AFTER `request_phone`;

-- Configurações de pagamento
ALTER TABLE `bots`
ADD COLUMN `payment_method` VARCHAR(255) NOT NULL DEFAULT 'credit_card' AFTER `request_language`;

-- Status de ativação
ALTER TABLE `bots`
ADD COLUMN `activated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_method`;

-- =====================================================
-- Verificar se os campos foram adicionados com sucesso
-- =====================================================
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_TYPE
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
  )
ORDER BY ORDINAL_POSITION;

-- =====================================================
-- Fim do script
-- =====================================================
