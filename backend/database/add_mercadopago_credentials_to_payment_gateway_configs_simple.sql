-- Migration: add_mercadopago_credentials_to_payment_gateway_configs_table
-- Data: 2025-12-10
-- Descrição: Adiciona campos para credenciais completas do Mercado Pago (public_key, client_id, client_secret)
-- Versão simplificada (sem verificações)

-- Adiciona public_key após api_key
ALTER TABLE `payment_gateway_configs` 
ADD COLUMN `public_key` VARCHAR(255) NULL AFTER `api_key`;

-- Adiciona client_id após public_key
ALTER TABLE `payment_gateway_configs` 
ADD COLUMN `client_id` VARCHAR(255) NULL AFTER `public_key`;

-- Adiciona client_secret após client_id
ALTER TABLE `payment_gateway_configs` 
ADD COLUMN `client_secret` VARCHAR(255) NULL AFTER `client_id`;
