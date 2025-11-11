-- Add privacy and payment settings to bots table
ALTER TABLE bots ADD COLUMN IF NOT EXISTS request_email BOOLEAN DEFAULT false;
ALTER TABLE bots ADD COLUMN IF NOT EXISTS request_phone BOOLEAN DEFAULT false;
ALTER TABLE bots ADD COLUMN IF NOT EXISTS request_language BOOLEAN DEFAULT false;
ALTER TABLE bots ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'credit_card';

-- Add activation status
ALTER TABLE bots ADD COLUMN IF NOT EXISTS activated BOOLEAN DEFAULT false;

