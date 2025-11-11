const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function createLogsTable() {
  try {
    console.log('Creating logs table...');
    
    const sqlFile = path.join(__dirname, 'createLogsTable.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await pool.query(sql);
    
    console.log('✓ Logs table created successfully!');
    process.exit(0);
  } catch (error) {
    console.error('✗ Error creating logs table:', error);
    process.exit(1);
  }
}

createLogsTable();

