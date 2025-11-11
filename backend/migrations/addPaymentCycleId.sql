-- Add payment_cycle_id column to payment_plans table
ALTER TABLE payment_plans 
ADD COLUMN IF NOT EXISTS payment_cycle_id INTEGER;

-- Add foreign key constraint
ALTER TABLE payment_plans
ADD CONSTRAINT fk_payment_plans_cycle 
FOREIGN KEY (payment_cycle_id) 
REFERENCES payment_cycles(id) 
ON DELETE SET NULL;

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_payment_plans_cycle_id ON payment_plans(payment_cycle_id);

