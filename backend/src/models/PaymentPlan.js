// Interação com o Banco de Dados (Schemas, ORM/ODM)
const pool = require('../config/db');

const PaymentPlan = {
  async findById(id, botId = null) {
    let query = 'SELECT * FROM payment_plans WHERE id = $1';
    const params = [id];
    
    if (botId) {
      query += ' AND bot_id = $2';
      params.push(botId);
    }
    
    const result = await pool.query(query, params);
    return result.rows[0];
  },

  async findByBotId(botId, activeOnly = false) {
    let query = 'SELECT * FROM payment_plans WHERE bot_id = $1';
    const params = [botId];
    
    if (activeOnly) {
      query += ' AND active = true';
    }
    
    query += ' ORDER BY created_at DESC';
    
    const result = await pool.query(query, params);
    return result.rows;
  },

  async findByBotIdAndTitle(botId, title, excludeId = null) {
    let query = 'SELECT id FROM payment_plans WHERE bot_id = $1 AND title = $2';
    const params = [botId, title];
    
    if (excludeId) {
      query += ' AND id != $3';
      params.push(excludeId);
    }
    
    const result = await pool.query(query, params);
    return result.rows[0];
  },

  async create(paymentPlanData) {
    const {
      bot_id,
      title,
      price,
      charge_period,
      cycle = 1,
      payment_cycle_id,
      message,
      pix_message,
      active = true
    } = paymentPlanData;

    const result = await pool.query(
      `INSERT INTO payment_plans (bot_id, title, price, charge_period, cycle, payment_cycle_id, message, pix_message, active) 
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9) 
       RETURNING *`,
      [bot_id, title, price, charge_period, cycle, payment_cycle_id || null, message, pix_message, active]
    );
    return result.rows[0];
  },

  async update(id, botId, paymentPlanData) {
    const updateFields = [];
    const values = [];
    let paramCount = 1;

    const fields = ['title', 'price', 'charge_period', 'cycle', 'payment_cycle_id', 'message', 'pix_message', 'active'];

    fields.forEach(field => {
      if (paymentPlanData[field] !== undefined) {
        updateFields.push(`${field} = $${paramCount++}`);
        values.push(paymentPlanData[field]);
      }
    });

    if (updateFields.length === 0) {
      return null;
    }

    updateFields.push(`updated_at = CURRENT_TIMESTAMP`);
    values.push(id, botId);

    const result = await pool.query(
      `UPDATE payment_plans SET ${updateFields.join(', ')} WHERE id = $${paramCount} AND bot_id = $${paramCount + 1} RETURNING *`,
      values
    );
    return result.rows[0];
  },

  async delete(id, botId) {
    const result = await pool.query(
      'DELETE FROM payment_plans WHERE id = $1 AND bot_id = $2 RETURNING id',
      [id, botId]
    );
    return result.rows[0];
  }
};

module.exports = PaymentPlan;

