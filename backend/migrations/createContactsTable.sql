-- Create contacts table
CREATE TABLE IF NOT EXISTS contacts (
    id SERIAL PRIMARY KEY,
    bot_id INTEGER NOT NULL,
    telegram_id BIGINT NOT NULL,
    name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    expires_at TIMESTAMP,
    telegram_username VARCHAR(255),
    telegram_status VARCHAR(50) DEFAULT 'inactive', -- active, inactive, blocked
    active BOOLEAN DEFAULT true,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
    CONSTRAINT unique_bot_telegram_id UNIQUE (bot_id, telegram_id)
);

-- Create indexes for faster queries
CREATE INDEX IF NOT EXISTS idx_contacts_bot_id ON contacts(bot_id);
CREATE INDEX IF NOT EXISTS idx_contacts_telegram_id ON contacts(telegram_id);
CREATE INDEX IF NOT EXISTS idx_contacts_telegram_status ON contacts(telegram_status);
CREATE INDEX IF NOT EXISTS idx_contacts_active ON contacts(active);
CREATE INDEX IF NOT EXISTS idx_contacts_name ON contacts(name);

