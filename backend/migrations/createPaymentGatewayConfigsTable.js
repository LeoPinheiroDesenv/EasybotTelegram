const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function createPaymentGatewayConfigsTable() {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');
    
    console.log('Creating payment_gateway_configs table...');
    
    const sqlFile = path.join(__dirname, 'createPaymentGatewayConfigsTable.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await client.query(sql);
    
    await client.query('COMMIT');
    console.log('✓ Payment gateway configs table created successfully!');
    process.exit(0);
  } catch (error) {
    await client.query('ROLLBACK');
    console.error('✗ Error creating payment gateway configs table:', error);
    process.exit(1);
  } finally {
    client.release();
  }
}

createPaymentGatewayConfigsTable();

