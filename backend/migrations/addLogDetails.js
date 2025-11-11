const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');

async function addLogDetails() {
  try {
    console.log('Adding details column to logs table...');
    
    await pool.query(`
      ALTER TABLE logs 
      ADD COLUMN IF NOT EXISTS details JSONB;
    `);
    
    console.log('✓ Details column added successfully!');
    process.exit(0);
  } catch (error) {
    console.error('✗ Error adding details column:', error);
    process.exit(1);
  }
}

addLogDetails();

