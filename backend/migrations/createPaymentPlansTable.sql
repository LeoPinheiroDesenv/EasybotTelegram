-- Create payment_plans table
CREATE TABLE IF NOT EXISTS payment_plans (
    id SERIAL PRIMARY KEY,
    bot_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    charge_period VARCHAR(50) NOT NULL, -- 'day', 'month', 'year'
    cycle INTEGER NOT NULL DEFAULT 1, -- Number of periods in the cycle
    message TEXT,
    pix_message TEXT,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
    CONSTRAINT unique_bot_title UNIQUE (bot_id, title)
);

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_payment_plans_bot_id ON payment_plans(bot_id);
CREATE INDEX IF NOT EXISTS idx_payment_plans_active ON payment_plans(active);

