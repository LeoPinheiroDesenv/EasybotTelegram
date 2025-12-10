-- Migration: Adicionar campo created_by à tabela user_groups
-- Versão simplificada (sem verificação de existência)
-- Data: 2025-12-10

-- Adiciona a coluna created_by
ALTER TABLE `user_groups` 
ADD COLUMN `created_by` BIGINT UNSIGNED NULL AFTER `active`;

-- Adiciona a chave estrangeira
ALTER TABLE `user_groups` 
ADD CONSTRAINT `user_groups_created_by_foreign` 
FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
