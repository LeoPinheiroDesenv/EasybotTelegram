const pool = require('../config/db');

const Transaction = {
  async findById(id) {
    const result = await pool.query(
      'SELECT * FROM transactions WHERE id = $1',
      [id]
    );
    return result.rows[0];
  },

  async findByGatewayTransactionId(gatewayTransactionId) {
    const result = await pool.query(
      'SELECT * FROM transactions WHERE gateway_transaction_id = $1',
      [gatewayTransactionId]
    );
    return result.rows[0];
  },

  async findByContactId(contactId) {
    const result = await pool.query(
      'SELECT * FROM transactions WHERE contact_id = $1 ORDER BY created_at DESC',
      [contactId]
    );
    return result.rows;
  },

  async findByPaymentPlanId(paymentPlanId) {
    const result = await pool.query(
      'SELECT * FROM transactions WHERE payment_plan_id = $1 ORDER BY created_at DESC',
      [paymentPlanId]
    );
    return result.rows;
  },

  async findByBotId(botId, filters = {}) {
    let query = 'SELECT * FROM transactions WHERE bot_id = $1';
    const params = [botId];
    let paramCount = 2;

    if (filters.status) {
      query += ` AND status = $${paramCount++}`;
      params.push(filters.status);
    }

    if (filters.payment_method) {
      query += ` AND payment_method = $${paramCount++}`;
      params.push(filters.payment_method);
    }

    query += ' ORDER BY created_at DESC';

    if (filters.limit) {
      query += ` LIMIT $${paramCount++}`;
      params.push(filters.limit);
    }

    if (filters.offset) {
      query += ` OFFSET $${paramCount++}`;
      params.push(filters.offset);
    }

    const result = await pool.query(query, params);
    return result.rows;
  },

  async create(transactionData) {
    const {
      contact_id,
      payment_plan_id,
      bot_id,
      amount,
      currency = 'BRL',
      payment_method,
      gateway,
      gateway_transaction_id,
      gateway_payment_id,
      status = 'pending',
      gateway_status,
      metadata,
      pix_qr_code,
      pix_qr_code_base64,
      pix_ticket_url,
      pix_expiration_date
    } = transactionData;

    const result = await pool.query(
      `INSERT INTO transactions (
        contact_id, payment_plan_id, bot_id, amount, currency, payment_method, 
        gateway, gateway_transaction_id, gateway_payment_id, status, gateway_status,
        metadata, pix_qr_code, pix_qr_code_base64, pix_ticket_url, pix_expiration_date
      ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16) 
      RETURNING *`,
      [
        contact_id || null,
        payment_plan_id,
        bot_id,
        amount,
        currency,
        payment_method,
        gateway,
        gateway_transaction_id || null,
        gateway_payment_id || null,
        status,
        gateway_status || null,
        metadata ? JSON.stringify(metadata) : null,
        pix_qr_code || null,
        pix_qr_code_base64 || null,
        pix_ticket_url || null,
        pix_expiration_date || null
      ]
    );
    return result.rows[0];
  },

  async update(id, updateData) {
    const updateFields = [];
    const values = [];
    let paramCount = 1;

    const fields = [
      'status', 'gateway_status', 'gateway_transaction_id', 'gateway_payment_id',
      'metadata', 'pix_qr_code', 'pix_qr_code_base64', 'pix_ticket_url', 'pix_expiration_date'
    ];

    fields.forEach(field => {
      if (updateData[field] !== undefined) {
        if (field === 'metadata' && updateData[field] !== null) {
          updateFields.push(`${field} = $${paramCount++}`);
          values.push(JSON.stringify(updateData[field]));
        } else {
          updateFields.push(`${field} = $${paramCount++}`);
          values.push(updateData[field]);
        }
      }
    });

    if (updateFields.length === 0) {
      return null;
    }

    updateFields.push(`updated_at = CURRENT_TIMESTAMP`);
    values.push(id);

    const result = await pool.query(
      `UPDATE transactions SET ${updateFields.join(', ')} WHERE id = $${paramCount} RETURNING *`,
      values
    );
    return result.rows[0];
  },

  async getStats(botId) {
    const result = await pool.query(
      `SELECT 
        COUNT(*) as total_transactions,
        COUNT(*) FILTER (WHERE status = 'approved') as approved_transactions,
        SUM(amount) FILTER (WHERE status = 'approved') as total_revenue,
        COUNT(*) FILTER (WHERE payment_method = 'pix') as pix_transactions,
        COUNT(*) FILTER (WHERE payment_method = 'credit_card') as credit_card_transactions
      FROM transactions 
      WHERE bot_id = $1`,
      [botId]
    );
    return result.rows[0];
  }
};

module.exports = Transaction;

