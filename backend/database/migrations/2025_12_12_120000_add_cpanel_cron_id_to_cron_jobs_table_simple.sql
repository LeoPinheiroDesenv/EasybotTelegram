-- Migration: Adiciona campo cpanel_cron_id à tabela cron_jobs
-- Versão simplificada (sem verificações)
-- Execute este arquivo se você tem certeza que a tabela cron_jobs existe

ALTER TABLE `cron_jobs` 
ADD COLUMN `cpanel_cron_id` INT(11) NULL DEFAULT NULL 
COMMENT 'ID do cron job no cPanel' 
AFTER `is_system`;
