-- Query SQL simples para criação da tabela telegram_groups
-- Execute esta query no seu banco de dados MySQL

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

