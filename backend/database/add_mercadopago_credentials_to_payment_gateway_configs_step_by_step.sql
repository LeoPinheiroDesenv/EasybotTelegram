-- Migration: add_mercadopago_credentials_to_payment_gateway_configs_table
-- Data: 2025-12-10
-- Descrição: Adiciona campos para credenciais completas do Mercado Pago (public_key, client_id, client_secret)
-- Versão passo a passo (executar um comando por vez)

-- PASSO 1: Verificar se a tabela existe
SELECT 
    TABLE_NAME,
    TABLE_SCHEMA
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'payment_gateway_configs';

-- PASSO 2: Verificar estrutura atual da tabela
DESCRIBE `payment_gateway_configs`;

-- PASSO 3: Verificar se os campos já existem
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'payment_gateway_configs' 
AND COLUMN_NAME IN ('public_key', 'client_id', 'client_secret');

-- PASSO 4: Adicionar public_key (executar apenas se não existir)
-- ALTER TABLE `payment_gateway_configs` 
-- ADD COLUMN `public_key` VARCHAR(255) NULL AFTER `api_key`;

-- PASSO 5: Adicionar client_id (executar apenas se não existir)
-- ALTER TABLE `payment_gateway_configs` 
-- ADD COLUMN `client_id` VARCHAR(255) NULL AFTER `public_key`;

-- PASSO 6: Adicionar client_secret (executar apenas se não existir)
-- ALTER TABLE `payment_gateway_configs` 
-- ADD COLUMN `client_secret` VARCHAR(255) NULL AFTER `client_id`;

-- PASSO 7: Verificar estrutura final da tabela
DESCRIBE `payment_gateway_configs`;
