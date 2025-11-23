-- =====================================================
-- Script SQL para remover campos de mensagens e configurações da tabela bots
-- Rollback da Migration: 2025_11_15_203808_add_messages_and_settings_to_bots_table
-- =====================================================

USE bottelegram_db;

-- =====================================================
-- Remover campos de mensagens e configurações
-- =====================================================

ALTER TABLE `bots`
DROP COLUMN IF EXISTS `initial_message`,
DROP COLUMN IF EXISTS `top_message`,
DROP COLUMN IF EXISTS `button_message`,
DROP COLUMN IF EXISTS `activate_cta`,
DROP COLUMN IF EXISTS `media_1_url`,
DROP COLUMN IF EXISTS `media_2_url`,
DROP COLUMN IF EXISTS `media_3_url`,
DROP COLUMN IF EXISTS `request_email`,
DROP COLUMN IF EXISTS `request_phone`,
DROP COLUMN IF EXISTS `request_language`,
DROP COLUMN IF EXISTS `payment_method`,
DROP COLUMN IF EXISTS `activated`;

-- =====================================================
-- Nota: Se o MySQL não suportar DROP COLUMN IF EXISTS,
-- use o comando abaixo sem o IF EXISTS:
-- =====================================================
/*
ALTER TABLE `bots`
DROP COLUMN `initial_message`,
DROP COLUMN `top_message`,
DROP COLUMN `button_message`,
DROP COLUMN `activate_cta`,
DROP COLUMN `media_1_url`,
DROP COLUMN `media_2_url`,
DROP COLUMN `media_3_url`,
DROP COLUMN `request_email`,
DROP COLUMN `request_phone`,
DROP COLUMN `request_language`,
DROP COLUMN `payment_method`,
DROP COLUMN `activated`;
*/

-- =====================================================
-- Fim do script
-- =====================================================

