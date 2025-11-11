const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function runMigrations() {
  try {
    console.log('Running database migrations...');
    
    const sqlFile = path.join(__dirname, 'createTables.sql');
    const sql = fs.readFileSync(sqlFile, 'utf8');
    
    await pool.query(sql);
    
    console.log('✓ Migrations completed successfully!');
    process.exit(0);
  } catch (error) {
    console.error('✗ Migration error:', error);
    process.exit(1);
  }
}

runMigrations();

