-- =====================================================
-- Script SQL para criação das tabelas - MySQL 8.0
-- EasyBot Telegram - Database Schema
-- =====================================================

-- Criar database se não existir
CREATE DATABASE IF NOT EXISTS bottelegram_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE bottelegram_db;

-- =====================================================
-- Tabela: users
-- =====================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `role` VARCHAR(255) NOT NULL DEFAULT 'user',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `two_factor_secret` VARCHAR(255) NULL,
    `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `email_verified_at` TIMESTAMP NULL,
    `password` VARCHAR(255) NOT NULL,
    `remember_token` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `users_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: password_reset_tokens
-- =====================================================
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: sessions
-- =====================================================
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(255) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: cache
-- =====================================================
CREATE TABLE IF NOT EXISTS `cache` (
    `key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: cache_locks
-- =====================================================
CREATE TABLE IF NOT EXISTS `cache_locks` (
    `key` VARCHAR(255) NOT NULL,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: jobs
-- =====================================================
CREATE TABLE IF NOT EXISTS `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: job_batches
-- =====================================================
CREATE TABLE IF NOT EXISTS `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL,
    `cancelled_at` INT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: failed_jobs
-- =====================================================
CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: bots
-- =====================================================
CREATE TABLE IF NOT EXISTS `bots` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `token` TEXT NOT NULL,
    `telegram_group_id` VARCHAR(255) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `bots_user_id_index` (`user_id`),
    KEY `bots_token_index` (`token`(255)),
    CONSTRAINT `bots_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: contacts
-- =====================================================
CREATE TABLE IF NOT EXISTS `contacts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bot_id` BIGINT UNSIGNED NOT NULL,
    `telegram_id` BIGINT NOT NULL,
    `username` VARCHAR(255) NULL,
    `first_name` VARCHAR(255) NULL,
    `last_name` VARCHAR(255) NULL,
    `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
    `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `contacts_bot_id_index` (`bot_id`),
    KEY `contacts_telegram_id_index` (`telegram_id`),
    UNIQUE KEY `contacts_bot_telegram_unique` (`bot_id`, `telegram_id`),
    CONSTRAINT `contacts_bot_id_foreign` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: payment_cycles
-- =====================================================
CREATE TABLE IF NOT EXISTS `payment_cycles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `days` INT NOT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `payment_cycles_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: payment_plans
-- =====================================================
CREATE TABLE IF NOT EXISTS `payment_plans` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bot_id` BIGINT UNSIGNED NOT NULL,
    `payment_cycle_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    `charge_period` VARCHAR(50) NOT NULL,
    `cycle` INT NOT NULL DEFAULT 1,
    `message` TEXT NULL,
    `pix_message` TEXT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `payment_plans_bot_id_index` (`bot_id`),
    KEY `payment_plans_payment_cycle_id_index` (`payment_cycle_id`),
    KEY `payment_plans_active_index` (`active`),
    CONSTRAINT `payment_plans_bot_id_foreign` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE,
    CONSTRAINT `payment_plans_payment_cycle_id_foreign` FOREIGN KEY (`payment_cycle_id`) REFERENCES `payment_cycles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: payment_gateway_configs
-- =====================================================
CREATE TABLE IF NOT EXISTS `payment_gateway_configs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bot_id` BIGINT UNSIGNED NOT NULL,
    `gateway` VARCHAR(50) NOT NULL,
    `environment` VARCHAR(50) NOT NULL DEFAULT 'sandbox',
    `api_key` VARCHAR(255) NULL,
    `api_secret` TEXT NULL,
    `webhook_url` VARCHAR(255) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `payment_gateway_configs_bot_id_index` (`bot_id`),
    KEY `payment_gateway_configs_gateway_index` (`gateway`),
    UNIQUE KEY `payment_gateway_configs_bot_gateway_env_unique` (`bot_id`, `gateway`, `environment`),
    CONSTRAINT `payment_gateway_configs_bot_id_foreign` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: transactions
-- =====================================================
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bot_id` BIGINT UNSIGNED NOT NULL,
    `contact_id` BIGINT UNSIGNED NOT NULL,
    `payment_plan_id` BIGINT UNSIGNED NOT NULL,
    `payment_cycle_id` BIGINT UNSIGNED NOT NULL,
    `gateway` VARCHAR(50) NOT NULL,
    `gateway_transaction_id` VARCHAR(255) NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'BRL',
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
    `payment_method` VARCHAR(50) NULL,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `transactions_bot_id_index` (`bot_id`),
    KEY `transactions_contact_id_index` (`contact_id`),
    KEY `transactions_payment_plan_id_index` (`payment_plan_id`),
    KEY `transactions_payment_cycle_id_index` (`payment_cycle_id`),
    KEY `transactions_status_index` (`status`),
    KEY `transactions_gateway_transaction_id_index` (`gateway_transaction_id`),
    CONSTRAINT `transactions_bot_id_foreign` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE,
    CONSTRAINT `transactions_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `transactions_payment_plan_id_foreign` FOREIGN KEY (`payment_plan_id`) REFERENCES `payment_plans` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `transactions_payment_cycle_id_foreign` FOREIGN KEY (`payment_cycle_id`) REFERENCES `payment_cycles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: logs
-- =====================================================
CREATE TABLE IF NOT EXISTS `logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bot_id` BIGINT UNSIGNED NULL,
    `level` VARCHAR(50) NOT NULL,
    `message` TEXT NOT NULL,
    `context` JSON NULL,
    `details` TEXT NULL,
    `created_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `logs_bot_id_index` (`bot_id`),
    KEY `logs_level_index` (`level`),
    KEY `logs_created_at_index` (`created_at`),
    CONSTRAINT `logs_bot_id_foreign` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Nota: Para criar o usuário admin, execute:
-- docker-compose exec backend php fix_admin_password.php
-- =====================================================

-- =====================================================
-- Fim do script
-- =====================================================

