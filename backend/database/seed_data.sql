-- =====================================================
-- Script SQL para inserção de dados iniciais
-- EasyBot Telegram - Seed Data
-- =====================================================

USE bottelegram_db;

-- =====================================================
-- Inserir Ciclos de Pagamento Padrão
-- =====================================================
INSERT INTO `payment_cycles` (`name`, `days`, `description`, `is_active`, `created_at`, `updated_at`)
VALUES
    ('Mensal', 30, 'Ciclo de pagamento mensal (30 dias)', 1, NOW(), NOW()),
    ('Trimestral', 90, 'Ciclo de pagamento trimestral (90 dias)', 1, NOW(), NOW()),
    ('Semestral', 180, 'Ciclo de pagamento semestral (180 dias)', 1, NOW(), NOW()),
    ('Anual', 365, 'Ciclo de pagamento anual (365 dias)', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = `name`;

-- =====================================================
-- Nota: O usuário admin deve ser criado usando o script
-- fix_admin_password.php para garantir que a senha seja
-- hasheada corretamente com bcrypt
-- =====================================================

