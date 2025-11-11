-- Create payment_gateway_configs table
CREATE TABLE IF NOT EXISTS payment_gateway_configs (
    id SERIAL PRIMARY KEY,
    bot_id INTEGER NOT NULL,
    gateway VARCHAR(50) NOT NULL, -- 'mercadopago', 'stripe'
    environment VARCHAR(20) NOT NULL, -- 'test', 'production'
    access_token TEXT,
    secret_key TEXT,
    webhook_secret TEXT,
    webhook_url TEXT,
    public_key TEXT, -- Para Stripe (publishable key)
    is_active BOOLEAN DEFAULT false,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
    CONSTRAINT unique_bot_gateway_environment UNIQUE (bot_id, gateway, environment)
);

-- Create indexes for faster queries
CREATE INDEX IF NOT EXISTS idx_payment_gateway_configs_bot_id ON payment_gateway_configs(bot_id);
CREATE INDEX IF NOT EXISTS idx_payment_gateway_configs_gateway ON payment_gateway_configs(gateway);
CREATE INDEX IF NOT EXISTS idx_payment_gateway_configs_environment ON payment_gateway_configs(environment);
CREATE INDEX IF NOT EXISTS idx_payment_gateway_configs_active ON payment_gateway_configs(is_active);

