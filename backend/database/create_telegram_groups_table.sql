-- =====================================================
-- Script SQL para criação da tabela telegram_groups
-- EasyBot Telegram - Database Schema
-- =====================================================

USE bottelegram_db;

-- =====================================================
-- Tabela: telegram_groups
-- =====================================================
CREATE TABLE IF NOT EXISTS `telegram_groups` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bot_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `telegram_group_id` VARCHAR(255) NOT NULL,
    `payment_plan_id` BIGINT UNSIGNED NULL,
    `invite_link` VARCHAR(500) NULL,
    `type` ENUM('group', 'channel') NOT NULL DEFAULT 'group',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `telegram_groups_bot_id_index` (`bot_id`),
    KEY `telegram_groups_telegram_group_id_index` (`telegram_group_id`),
    UNIQUE KEY `telegram_groups_bot_id_telegram_group_id_unique` (`bot_id`, `telegram_group_id`),
    CONSTRAINT `telegram_groups_bot_id_foreign` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE,
    CONSTRAINT `telegram_groups_payment_plan_id_foreign` FOREIGN KEY (`payment_plan_id`) REFERENCES `payment_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Comentários sobre a tabela
-- =====================================================
-- Esta tabela armazena grupos e canais do Telegram associados aos bots
-- 
-- Campos:
-- - id: Identificador único do registro
-- - bot_id: ID do bot ao qual o grupo/canal pertence
-- - title: Título do grupo ou canal
-- - telegram_group_id: ID do grupo/canal no Telegram (pode ser numérico ou @username)
-- - payment_plan_id: ID do plano de pagamento associado (opcional)
-- - invite_link: Link de convite para o grupo/canal (gerado automaticamente)
-- - type: Tipo do registro ('group' para grupos, 'channel' para canais)
-- - active: Indica se o grupo/canal está ativo (1) ou inativo (0)
-- - created_at: Data de criação do registro
-- - updated_at: Data da última atualização do registro
--
-- Índices:
-- - bot_id: Para buscas rápidas por bot
-- - telegram_group_id: Para buscas rápidas por ID do Telegram
-- - UNIQUE (bot_id, telegram_group_id): Garante que um grupo/canal só pode estar associado a um bot uma vez
--
-- Foreign Keys:
-- - bot_id -> bots.id (CASCADE): Se o bot for deletado, os grupos associados também serão
-- - payment_plan_id -> payment_plans.id (SET NULL): Se o plano for deletado, o campo será definido como NULL

