-- Create transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id SERIAL PRIMARY KEY,
    contact_id INTEGER,
    payment_plan_id INTEGER NOT NULL,
    bot_id INTEGER NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'BRL',
    payment_method VARCHAR(50) NOT NULL, -- 'pix', 'credit_card'
    gateway VARCHAR(50) NOT NULL, -- 'mercadopago', 'stripe'
    gateway_transaction_id VARCHAR(255),
    gateway_payment_id VARCHAR(255),
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- 'pending', 'processing', 'approved', 'rejected', 'cancelled', 'refunded'
    gateway_status VARCHAR(100),
    metadata JSONB,
    pix_qr_code TEXT,
    pix_qr_code_base64 TEXT,
    pix_ticket_url TEXT,
    pix_expiration_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_plan_id) REFERENCES payment_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
);

-- Create indexes for faster queries
CREATE INDEX IF NOT EXISTS idx_transactions_contact_id ON transactions(contact_id);
CREATE INDEX IF NOT EXISTS idx_transactions_payment_plan_id ON transactions(payment_plan_id);
CREATE INDEX IF NOT EXISTS idx_transactions_bot_id ON transactions(bot_id);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_transactions_gateway_transaction_id ON transactions(gateway_transaction_id);
CREATE INDEX IF NOT EXISTS idx_transactions_created_at ON transactions(created_at);

