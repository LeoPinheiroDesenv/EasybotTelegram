const pool = require('../config/db');

class PaymentCycle {
  static async findAll() {
    const result = await pool.query(
      'SELECT * FROM payment_cycles ORDER BY days ASC, name ASC'
    );
    return result.rows;
  }

  static async findById(id) {
    const result = await pool.query(
      'SELECT * FROM payment_cycles WHERE id = $1',
      [id]
    );
    return result.rows[0];
  }

  static async findByName(name) {
    const result = await pool.query(
      'SELECT * FROM payment_cycles WHERE name = $1',
      [name]
    );
    return result.rows[0];
  }

  static async create(data) {
    const { name, days, description, is_active = true } = data;
    const result = await pool.query(
      `INSERT INTO payment_cycles (name, days, description, is_active) 
       VALUES ($1, $2, $3, $4) 
       RETURNING *`,
      [name, days, description, is_active]
    );
    return result.rows[0];
  }

  static async update(id, data) {
    const { name, days, description, is_active } = data;
    const result = await pool.query(
      `UPDATE payment_cycles 
       SET name = $1, days = $2, description = $3, is_active = $4, updated_at = CURRENT_TIMESTAMP
       WHERE id = $5 
       RETURNING *`,
      [name, days, description, is_active, id]
    );
    return result.rows[0];
  }

  static async delete(id) {
    const result = await pool.query(
      'DELETE FROM payment_cycles WHERE id = $1 RETURNING *',
      [id]
    );
    return result.rows[0];
  }

  static async findActive() {
    const result = await pool.query(
      'SELECT * FROM payment_cycles WHERE is_active = true ORDER BY days ASC, name ASC'
    );
    return result.rows;
  }
}

module.exports = PaymentCycle;

