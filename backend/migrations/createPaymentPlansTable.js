const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function createPaymentPlansTable() {
  try {
    console.log('Creating payment_plans table...');
    
    const sqlFile = path.join(__dirname, 'createPaymentPlansTable.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await pool.query(sql);
    
    console.log('✓ Payment plans table created successfully!');
    process.exit(0);
  } catch (error) {
    console.error('✗ Error creating payment plans table:', error);
    process.exit(1);
  }
}

createPaymentPlansTable();

