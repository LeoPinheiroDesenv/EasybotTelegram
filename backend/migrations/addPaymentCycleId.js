const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function addPaymentCycleId() {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');
    
    console.log('Adding payment_cycle_id column to payment_plans table...');
    
    const sqlFile = path.join(__dirname, 'addPaymentCycleId.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await client.query(sql);
    
    await client.query('COMMIT');
    console.log('✓ Payment cycle ID column added successfully!');
    process.exit(0);
  } catch (error) {
    await client.query('ROLLBACK');
    console.error('✗ Error adding payment cycle ID column:', error);
    process.exit(1);
  } finally {
    client.release();
  }
}

addPaymentCycleId();

