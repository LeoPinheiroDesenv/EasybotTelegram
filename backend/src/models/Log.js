// Interação com o Banco de Dados (Schemas, ORM/ODM)
const pool = require('../config/db');

const Log = {
  async create(logData) {
    const { level, message, context, user_id, ip_address, user_agent, details } = logData;
    // Se context é uma string JSON, converte para objeto; se já é objeto, mantém
    let contextValue = null;
    if (context) {
      if (typeof context === 'string') {
        try {
          contextValue = JSON.parse(context);
        } catch (e) {
          contextValue = context;
        }
      } else {
        contextValue = context;
      }
    }
    
    // Processa details da mesma forma
    let detailsValue = null;
    if (details) {
      if (typeof details === 'string') {
        try {
          detailsValue = JSON.parse(details);
        } catch (e) {
          detailsValue = details;
        }
      } else {
        detailsValue = details;
      }
    }
    
    const result = await pool.query(
      `INSERT INTO logs (level, message, context, user_id, ip_address, user_agent, details) 
       VALUES ($1, $2, $3, $4, $5, $6, $7) 
       RETURNING id, level, message, context, user_id, ip_address, user_agent, details, created_at`,
      [
        level, 
        message, 
        contextValue ? JSON.stringify(contextValue) : null, 
        user_id || null, 
        ip_address || null, 
        user_agent || null,
        detailsValue ? JSON.stringify(detailsValue) : null
      ]
    );
    return result.rows[0];
  },

  async findAll(filters = {}) {
    const { level, limit = 100, offset = 0, startDate, endDate } = filters;
    let query = 'SELECT l.*, u.email as user_email FROM logs l LEFT JOIN users u ON l.user_id = u.id WHERE 1=1';
    const params = [];
    let paramCount = 1;

    if (level) {
      query += ` AND l.level = $${paramCount++}`;
      params.push(level);
    }

    if (startDate) {
      query += ` AND l.created_at >= $${paramCount++}`;
      params.push(startDate);
    }

    if (endDate) {
      query += ` AND l.created_at <= $${paramCount++}`;
      params.push(endDate);
    }

    query += ' ORDER BY l.created_at DESC';
    query += ` LIMIT $${paramCount++} OFFSET $${paramCount++}`;
    params.push(limit, offset);

    const result = await pool.query(query, params);
    return result.rows;
  },

  async count(filters = {}) {
    const { level, startDate, endDate } = filters;
    let query = 'SELECT COUNT(*) as total FROM logs WHERE 1=1';
    const params = [];
    let paramCount = 1;

    if (level) {
      query += ` AND level = $${paramCount++}`;
      params.push(level);
    }

    if (startDate) {
      query += ` AND created_at >= $${paramCount++}`;
      params.push(startDate);
    }

    if (endDate) {
      query += ` AND created_at <= $${paramCount++}`;
      params.push(endDate);
    }

    const result = await pool.query(query, params);
    return parseInt(result.rows[0].total);
  },

  async findById(id) {
    const result = await pool.query(
      'SELECT l.*, u.email as user_email FROM logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.id = $1',
      [id]
    );
    return result.rows[0];
  }
};

module.exports = Log;

