// Interação com o Banco de Dados (Schemas, ORM/ODM)
const pool = require('../config/db');

const Contact = {
  async findById(id, botId = null) {
    let query = 'SELECT * FROM contacts WHERE id = $1';
    const params = [id];
    
    if (botId) {
      query += ' AND bot_id = $2';
      params.push(botId);
    }
    
    const result = await pool.query(query, params);
    return result.rows[0];
  },

  async findByTelegramId(botId, telegramId) {
    const result = await pool.query(
      'SELECT * FROM contacts WHERE bot_id = $1 AND telegram_id = $2',
      [botId, telegramId]
    );
    return result.rows[0];
  },

  async findByBotId(botId, filters = {}) {
    let query = 'SELECT * FROM contacts WHERE bot_id = $1';
    const params = [botId];
    let paramCount = 2;

    if (filters.search) {
      query += ` AND (name ILIKE $${paramCount} OR telegram_id::text ILIKE $${paramCount} OR telegram_username ILIKE $${paramCount})`;
      params.push(`%${filters.search}%`);
      paramCount++;
    }

    if (filters.telegram_status) {
      query += ` AND telegram_status = $${paramCount}`;
      params.push(filters.telegram_status);
      paramCount++;
    }

    if (filters.active !== undefined) {
      query += ` AND active = $${paramCount}`;
      params.push(filters.active);
      paramCount++;
    }

    query += ' ORDER BY created_at DESC';
    
    if (filters.limit) {
      query += ` LIMIT $${paramCount}`;
      params.push(filters.limit);
      paramCount++;
    }

    if (filters.offset) {
      query += ` OFFSET $${paramCount}`;
      params.push(filters.offset);
    }

    const result = await pool.query(query, params);
    return result.rows;
  },

  async countByBotId(botId, filters = {}) {
    let query = 'SELECT COUNT(*) FROM contacts WHERE bot_id = $1';
    const params = [botId];
    let paramCount = 2;

    if (filters.search) {
      query += ` AND (name ILIKE $${paramCount} OR telegram_id::text ILIKE $${paramCount} OR telegram_username ILIKE $${paramCount})`;
      params.push(`%${filters.search}%`);
      paramCount++;
    }

    if (filters.telegram_status) {
      query += ` AND telegram_status = $${paramCount}`;
      params.push(filters.telegram_status);
      paramCount++;
    }

    if (filters.active !== undefined) {
      query += ` AND active = $${paramCount}`;
      params.push(filters.active);
      paramCount++;
    }

    const result = await pool.query(query, params);
    return parseInt(result.rows[0].count, 10);
  },

  async getStatsByBotId(botId) {
    const result = await pool.query(
      `SELECT 
        COUNT(*) FILTER (WHERE telegram_status = 'active' AND active = true) as active_count,
        COUNT(*) FILTER (WHERE telegram_status = 'inactive' AND active = true) as inactive_count,
        COUNT(*) as total_count
       FROM contacts 
       WHERE bot_id = $1`,
      [botId]
    );
    return result.rows[0];
  },

  async getLatest(botId, limit = 10) {
    const result = await pool.query(
      'SELECT id, name, telegram_username, created_at FROM contacts WHERE bot_id = $1 ORDER BY created_at DESC LIMIT $2',
      [botId, limit]
    );
    return result.rows;
  },

  async create(contactData) {
    const {
      bot_id,
      telegram_id,
      name,
      email,
      phone,
      expires_at,
      telegram_username,
      telegram_status = 'inactive',
      active = true,
      metadata
    } = contactData;

    const result = await pool.query(
      `INSERT INTO contacts (bot_id, telegram_id, name, email, phone, expires_at, telegram_username, telegram_status, active, metadata) 
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10) 
       RETURNING *`,
      [bot_id, telegram_id, name, email, phone, expires_at, telegram_username, telegram_status, active, metadata ? JSON.stringify(metadata) : null]
    );
    return result.rows[0];
  },

  async update(id, botId, contactData) {
    const updateFields = [];
    const values = [];
    let paramCount = 1;

    const fields = ['name', 'email', 'phone', 'expires_at', 'telegram_username', 'telegram_status', 'active', 'metadata'];

    fields.forEach(field => {
      if (contactData[field] !== undefined) {
        if (field === 'metadata' && contactData[field] !== null) {
          updateFields.push(`${field} = $${paramCount++}`);
          values.push(JSON.stringify(contactData[field]));
        } else {
          updateFields.push(`${field} = $${paramCount++}`);
          values.push(contactData[field]);
        }
      }
    });

    if (updateFields.length === 0) {
      return null;
    }

    updateFields.push(`updated_at = CURRENT_TIMESTAMP`);
    values.push(id, botId);

    const result = await pool.query(
      `UPDATE contacts SET ${updateFields.join(', ')} WHERE id = $${paramCount} AND bot_id = $${paramCount + 1} RETURNING *`,
      values
    );
    return result.rows[0];
  },

  async delete(id, botId) {
    const result = await pool.query(
      'DELETE FROM contacts WHERE id = $1 AND bot_id = $2 RETURNING id',
      [id, botId]
    );
    return result.rows[0];
  },

  async block(id, botId) {
    const result = await pool.query(
      'UPDATE contacts SET telegram_status = $1, active = $2, updated_at = CURRENT_TIMESTAMP WHERE id = $3 AND bot_id = $4 RETURNING *',
      ['blocked', false, id, botId]
    );
    return result.rows[0];
  }
};

module.exports = Contact;

