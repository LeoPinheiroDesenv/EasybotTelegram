const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function createTransactionsTable() {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');
    
    console.log('Creating transactions table...');
    
    const sqlFile = path.join(__dirname, 'createTransactionsTable.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await client.query(sql);
    
    await client.query('COMMIT');
    console.log('✓ Transactions table created successfully!');
    process.exit(0);
  } catch (error) {
    await client.query('ROLLBACK');
    console.error('✗ Error creating transactions table:', error);
    process.exit(1);
  } finally {
    client.release();
  }
}

createTransactionsTable();

