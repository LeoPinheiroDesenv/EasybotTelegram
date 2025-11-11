const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function addBotSettings() {
  try {
    console.log('Adding bot settings columns...');
    
    const sqlFile = path.join(__dirname, 'addBotSettings.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await pool.query(sql);
    
    console.log('✓ Bot settings columns added successfully!');
    process.exit(0);
  } catch (error) {
    console.error('✗ Error adding bot settings:', error);
    process.exit(1);
  }
}

addBotSettings();

