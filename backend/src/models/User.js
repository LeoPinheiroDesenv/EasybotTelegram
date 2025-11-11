// Interação com o Banco de Dados (Schemas, ORM/ODM)
const pool = require('../config/db');

const User = {
  async findById(id) {
    const result = await pool.query(
      'SELECT id, name, email, password, role, active, two_factor_secret, two_factor_enabled, created_at, updated_at FROM users WHERE id = $1',
      [id]
    );
    return result.rows[0];
  },

  async findByEmail(email) {
    const result = await pool.query(
      'SELECT id, name, email, password, role, active, two_factor_secret, two_factor_enabled FROM users WHERE email = $1',
      [email]
    );
    return result.rows[0];
  },

  async findAll() {
    const result = await pool.query(
      'SELECT id, name, email, role, active, created_at, updated_at FROM users ORDER BY created_at DESC'
    );
    return result.rows;
  },

  async create(userData) {
    const { name, email, password, role = 'user', active = true } = userData;
    const result = await pool.query(
      'INSERT INTO users (name, email, password, role, active) VALUES ($1, $2, $3, $4, $5) RETURNING id, name, email, role, active, created_at, updated_at',
      [name, email, password, role, active]
    );
    return result.rows[0];
  },

  async update(id, userData) {
    const updateFields = [];
    const values = [];
    let paramCount = 1;

    if (userData.name) {
      updateFields.push(`name = $${paramCount++}`);
      values.push(userData.name);
    }
    if (userData.email) {
      updateFields.push(`email = $${paramCount++}`);
      values.push(userData.email);
    }
    if (userData.password) {
      updateFields.push(`password = $${paramCount++}`);
      values.push(userData.password);
    }
    if (userData.role !== undefined) {
      updateFields.push(`role = $${paramCount++}`);
      values.push(userData.role);
    }
    if (userData.active !== undefined) {
      updateFields.push(`active = $${paramCount++}`);
      values.push(userData.active);
    }
    if (userData.two_factor_secret !== undefined) {
      updateFields.push(`two_factor_secret = $${paramCount++}`);
      values.push(userData.two_factor_secret);
    }
    if (userData.two_factor_enabled !== undefined) {
      updateFields.push(`two_factor_enabled = $${paramCount++}`);
      values.push(userData.two_factor_enabled);
    }

    if (updateFields.length === 0) {
      return null;
    }

    updateFields.push(`updated_at = CURRENT_TIMESTAMP`);
    values.push(id);

    const result = await pool.query(
      `UPDATE users SET ${updateFields.join(', ')} WHERE id = $${paramCount} RETURNING id, name, email, role, active, two_factor_enabled, created_at, updated_at`,
      values
    );
    return result.rows[0];
  },

  async delete(id) {
    const result = await pool.query(
      'DELETE FROM users WHERE id = $1 RETURNING id',
      [id]
    );
    return result.rows[0];
  },

  async emailExists(email, excludeId = null) {
    let query = 'SELECT id FROM users WHERE email = $1';
    const params = [email];
    
    if (excludeId) {
      query += ' AND id != $2';
      params.push(excludeId);
    }
    
    const result = await pool.query(query, params);
    return result.rows.length > 0;
  }
};

module.exports = User;

