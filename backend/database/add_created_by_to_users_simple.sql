-- =====================================================
-- Script SQL SIMPLES para adicionar campo created_by à tabela users
-- Execute este script se tiver certeza que a coluna não existe
-- =====================================================

-- Adicionar coluna created_by
ALTER TABLE `users` 
ADD COLUMN `created_by` BIGINT UNSIGNED NULL 
AFTER `user_group_id`;

-- Adicionar foreign key
ALTER TABLE `users` 
ADD CONSTRAINT `users_created_by_foreign` 
FOREIGN KEY (`created_by`) 
REFERENCES `users` (`id`) 
ON DELETE SET NULL;

-- Verificar se foi criado corretamente
DESCRIBE `users`;

