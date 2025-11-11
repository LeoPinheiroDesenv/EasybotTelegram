-- Criar tabela de ciclos de pagamento
CREATE TABLE IF NOT EXISTS payment_cycles (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  days INTEGER NOT NULL,
  description TEXT,
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Criar índice para busca por nome
CREATE INDEX IF NOT EXISTS idx_payment_cycles_name ON payment_cycles(name);

-- Criar índice para busca por status ativo
CREATE INDEX IF NOT EXISTS idx_payment_cycles_active ON payment_cycles(is_active);

