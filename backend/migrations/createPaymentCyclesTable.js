const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function createPaymentCyclesTable() {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');
    
    console.log('Creating payment_cycles table...');
    
    const sqlFile = path.join(__dirname, 'createPaymentCyclesTable.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await client.query(sql);
    
    // Popular com os dados iniciais
    console.log('Populating payment_cycles table...');
    
    const cycles = [
      { name: 'Diário', days: 1, description: 'Ciclo de pagamento diário' },
      { name: 'Semanal', days: 7, description: 'Ciclo de pagamento semanal' },
      { name: 'Mensal', days: 30, description: 'Ciclo de pagamento mensal' },
      { name: 'Anual', days: 365, description: 'Ciclo de pagamento anual' },
      { name: 'Vitalício', days: 0, description: 'Pagamento único vitalício' }
    ];
    
    for (const cycle of cycles) {
      await client.query(
        'INSERT INTO payment_cycles (name, days, description) VALUES ($1, $2, $3) ON CONFLICT (name) DO NOTHING',
        [cycle.name, cycle.days, cycle.description]
      );
    }
    
    await client.query('COMMIT');
    console.log('✓ Payment cycles table created and populated successfully!');
    process.exit(0);
  } catch (error) {
    await client.query('ROLLBACK');
    console.error('✗ Error creating payment cycles table:', error);
    process.exit(1);
  } finally {
    client.release();
  }
}

createPaymentCyclesTable();

