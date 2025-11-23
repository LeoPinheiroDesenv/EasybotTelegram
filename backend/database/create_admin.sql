-- =====================================================
-- Script SQL para criação do usuário admin
-- EasyBot Telegram - Admin User Creation
-- =====================================================

USE bottelegram_db;

-- =====================================================
-- Criar usuário admin
-- =====================================================
-- NOTA: Este script cria o usuário, mas a senha precisa ser
-- atualizada usando o script PHP fix_admin_password.php
-- para garantir que seja hasheada corretamente com bcrypt
-- =====================================================

-- Remover usuário admin se já existir (para recriar)
DELETE FROM `users` WHERE `email` = 'admin@admin.com';

-- Inserir usuário admin
-- A senha será atualizada pelo script PHP
INSERT INTO `users` (
    `name`,
    `email`,
    `password`,
    `role`,
    `active`,
    `two_factor_enabled`,
    `created_at`,
    `updated_at`
) VALUES (
    'Administrator',
    'admin@admin.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Senha temporária (será atualizada)
    'admin',
    1,
    0,
    NOW(),
    NOW()
);

-- Verificar se foi criado
SELECT 
    id,
    name,
    email,
    role,
    active,
    two_factor_enabled,
    created_at
FROM `users`
WHERE `email` = 'admin@admin.com';

-- =====================================================
-- IMPORTANTE: Execute o script PHP para atualizar a senha:
-- docker-compose exec backend php fix_admin_password.php
-- =====================================================

