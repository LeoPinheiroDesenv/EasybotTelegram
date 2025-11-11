const bcrypt = require('bcryptjs');
const pool = require('../src/config/db');

async function createDefaultAdmin() {
  try {
    console.log('Creating default admin user...');
    
    const defaultPassword = 'admin123';
    const hashedPassword = await bcrypt.hash(defaultPassword, 10);
    
    const result = await pool.query(
      `INSERT INTO users (name, email, password, role, active) 
       VALUES ($1, $2, $3, $4, $5)
       ON CONFLICT (email) DO NOTHING
       RETURNING id, email`,
      ['Administrador', 'admin@admin.com', hashedPassword, 'admin', true]
    );
    
    if (result.rows.length > 0) {
      console.log('✓ Default admin user created successfully!');
      console.log('  Email: admin@admin.com');
      console.log('  Password: admin123');
    } else {
      console.log('✓ Default admin user already exists.');
    }
    
    process.exit(0);
  } catch (error) {
    console.error('Error creating default admin:', error);
    process.exit(1);
  }
}

createDefaultAdmin();

