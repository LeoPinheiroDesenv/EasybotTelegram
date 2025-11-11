const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function createBotsTable() {
  try {
    console.log('Creating bots table...');
    
    const sqlFile = path.join(__dirname, 'createBotsTable.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await pool.query(sql);
    
    console.log('✓ Bots table created successfully!');
    process.exit(0);
  } catch (error) {
    console.error('✗ Error creating bots table:', error);
    process.exit(1);
  }
}

createBotsTable();

