// Interação com o Banco de Dados (Schemas, ORM/ODM)
const pool = require('../config/db');

const Bot = {
  async findById(id, userId = null) {
    let query = 'SELECT * FROM bots WHERE id = $1';
    const params = [id];
    
    if (userId) {
      query += ' AND user_id = $2';
      params.push(userId);
    }
    
    const result = await pool.query(query, params);
    return result.rows[0];
  },

  async findByUserId(userId) {
    const result = await pool.query(
      'SELECT * FROM bots WHERE user_id = $1 ORDER BY created_at DESC',
      [userId]
    );
    return result.rows;
  },

  async findAll() {
    const result = await pool.query(
      'SELECT * FROM bots WHERE active = true ORDER BY created_at DESC'
    );
    return result.rows;
  },

  async findByToken(token, userId) {
    const result = await pool.query(
      'SELECT id FROM bots WHERE token = $1 AND user_id = $2',
      [token, userId]
    );
    return result.rows[0];
  },

  async create(botData) {
    const {
      user_id,
      name,
      token,
      telegram_group_id,
      active = true,
      request_email = false,
      request_phone = false,
      request_language = false,
      payment_method = 'credit_card',
      initial_message,
      top_message,
      button_message,
      activate_cta = false,
      media_1_url,
      media_2_url,
      media_3_url
    } = botData;

    const result = await pool.query(
      `INSERT INTO bots (user_id, name, token, telegram_group_id, active, request_email, request_phone, request_language, payment_method, initial_message, top_message, button_message, activate_cta, media_1_url, media_2_url, media_3_url) 
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16) 
       RETURNING *`,
      [user_id, name, token, telegram_group_id, active, request_email, request_phone, request_language, payment_method, initial_message, top_message, button_message, activate_cta, media_1_url, media_2_url, media_3_url]
    );
    return result.rows[0];
  },

  async update(id, userId, botData) {
    const updateFields = [];
    const values = [];
    let paramCount = 1;

    const fields = ['name', 'token', 'telegram_group_id', 'active', 'request_email', 'request_phone', 
                    'request_language', 'payment_method', 'activated', 'initial_message', 'top_message', 
                    'button_message', 'activate_cta', 'media_1_url', 'media_2_url', 'media_3_url'];

    fields.forEach(field => {
      if (botData[field] !== undefined) {
        updateFields.push(`${field} = $${paramCount++}`);
        values.push(botData[field]);
      }
    });

    if (updateFields.length === 0) {
      return null;
    }

    updateFields.push(`updated_at = CURRENT_TIMESTAMP`);
    values.push(id, userId);

    const result = await pool.query(
      `UPDATE bots SET ${updateFields.join(', ')} WHERE id = $${paramCount} AND user_id = $${paramCount + 1} RETURNING *`,
      values
    );
    return result.rows[0];
  },

  async delete(id, userId) {
    const result = await pool.query(
      'DELETE FROM bots WHERE id = $1 AND user_id = $2 RETURNING id',
      [id, userId]
    );
    return result.rows[0];
  },

  async tokenExists(token, userId, excludeId = null) {
    let query = 'SELECT id FROM bots WHERE token = $1 AND user_id = $2';
    const params = [token, userId];
    
    if (excludeId) {
      query += ' AND id != $3';
      params.push(excludeId);
    }
    
    const result = await pool.query(query, params);
    return result.rows.length > 0;
  }
};

module.exports = Bot;

