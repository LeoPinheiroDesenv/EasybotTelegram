const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function addTwoFactorAuth() {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');
    
    console.log('Adding two-factor authentication columns to users table...');
    
    const sqlFile = path.join(__dirname, 'addTwoFactorAuth.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await client.query(sql);
    
    await client.query('COMMIT');
    console.log('✓ Two-factor authentication columns added successfully!');
    process.exit(0);
  } catch (error) {
    await client.query('ROLLBACK');
    console.error('✗ Error adding two-factor authentication columns:', error);
    process.exit(1);
  } finally {
    client.release();
  }
}

addTwoFactorAuth();

