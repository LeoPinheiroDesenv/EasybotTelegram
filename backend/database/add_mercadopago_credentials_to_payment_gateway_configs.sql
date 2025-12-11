-- Migration: add_mercadopago_credentials_to_payment_gateway_configs_table
-- Data: 2025-12-10
-- Descrição: Adiciona campos para credenciais completas do Mercado Pago (public_key, client_id, client_secret)

-- Verifica se a tabela existe
SET @table_exists = (
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'payment_gateway_configs'
);

-- Verifica se os campos já existem
SET @public_key_exists = (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'payment_gateway_configs' 
    AND column_name = 'public_key'
);

SET @client_id_exists = (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'payment_gateway_configs' 
    AND column_name = 'client_id'
);

SET @client_secret_exists = (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'payment_gateway_configs' 
    AND column_name = 'client_secret'
);

-- Adiciona public_key se a tabela existir e o campo não existir
SET @sql_public_key = IF(
    @table_exists > 0 AND @public_key_exists = 0,
    'ALTER TABLE `payment_gateway_configs` ADD COLUMN `public_key` VARCHAR(255) NULL AFTER `api_key`;',
    'SELECT "Campo public_key já existe ou tabela não encontrada" AS message;'
);
PREPARE stmt FROM @sql_public_key;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona client_id se a tabela existir e o campo não existir
SET @sql_client_id = IF(
    @table_exists > 0 AND @client_id_exists = 0,
    'ALTER TABLE `payment_gateway_configs` ADD COLUMN `client_id` VARCHAR(255) NULL AFTER `public_key`;',
    'SELECT "Campo client_id já existe ou tabela não encontrada" AS message;'
);
PREPARE stmt FROM @sql_client_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona client_secret se a tabela existir e o campo não existir
SET @sql_client_secret = IF(
    @table_exists > 0 AND @client_secret_exists = 0,
    'ALTER TABLE `payment_gateway_configs` ADD COLUMN `client_secret` VARCHAR(255) NULL AFTER `client_id`;',
    'SELECT "Campo client_secret já existe ou tabela não encontrada" AS message;'
);
PREPARE stmt FROM @sql_client_secret;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica o resultado final
SELECT 
    'Migration concluída' AS status,
    CASE WHEN @public_key_exists > 0 THEN 'public_key: já existia' ELSE 'public_key: adicionado' END AS public_key_status,
    CASE WHEN @client_id_exists > 0 THEN 'client_id: já existia' ELSE 'client_id: adicionado' END AS client_id_status,
    CASE WHEN @client_secret_exists > 0 THEN 'client_secret: já existia' ELSE 'client_secret: adicionado' END AS client_secret_status;
