const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function addWelcomeMessage() {
  try {
    console.log('Adding welcome message columns...');
    
    const sqlFile = path.join(__dirname, 'addWelcomeMessage.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await pool.query(sql);
    
    console.log('✓ Welcome message columns added successfully!');
    process.exit(0);
  } catch (error) {
    console.error('✗ Error adding welcome message columns:', error);
    process.exit(1);
  }
}

addWelcomeMessage();

