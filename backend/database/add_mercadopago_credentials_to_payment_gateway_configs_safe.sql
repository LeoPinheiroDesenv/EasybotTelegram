-- Migration: add_mercadopago_credentials_to_payment_gateway_configs_table
-- Data: 2025-12-10
-- Descrição: Adiciona campos para credenciais completas do Mercado Pago (public_key, client_id, client_secret)
-- Versão segura (com verificações de existência)

-- Adiciona public_key se não existir
ALTER TABLE `payment_gateway_configs` 
ADD COLUMN IF NOT EXISTS `public_key` VARCHAR(255) NULL AFTER `api_key`;

-- Adiciona client_id se não existir
ALTER TABLE `payment_gateway_configs` 
ADD COLUMN IF NOT EXISTS `client_id` VARCHAR(255) NULL AFTER `public_key`;

-- Adiciona client_secret se não existir
ALTER TABLE `payment_gateway_configs` 
ADD COLUMN IF NOT EXISTS `client_secret` VARCHAR(255) NULL AFTER `client_id`;
