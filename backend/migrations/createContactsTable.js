const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function createContactsTable() {
  try {
    console.log('Creating contacts table...');
    
    const sqlFile = path.join(__dirname, 'createContactsTable.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await pool.query(sql);
    
    console.log('✓ Contacts table created successfully!');
    process.exit(0);
  } catch (error) {
    console.error('✗ Error creating contacts table:', error);
    process.exit(1);
  }
}

createContactsTable();

