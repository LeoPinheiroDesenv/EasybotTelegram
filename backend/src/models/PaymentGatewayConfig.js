const pool = require('../config/db');

const PaymentGatewayConfig = {
  async findById(id) {
    const result = await pool.query(
      'SELECT * FROM payment_gateway_configs WHERE id = $1',
      [id]
    );
    return result.rows[0];
  },

  async findByBotId(botId) {
    const result = await pool.query(
      'SELECT * FROM payment_gateway_configs WHERE bot_id = $1 ORDER BY gateway, environment',
      [botId]
    );
    return result.rows;
  },

  async findByBotAndGateway(botId, gateway, environment = null) {
    let query = 'SELECT * FROM payment_gateway_configs WHERE bot_id = $1 AND gateway = $2';
    const params = [botId, gateway];
    
    if (environment) {
      query += ' AND environment = $3';
      params.push(environment);
    }
    
    query += ' ORDER BY environment';
    
    const result = await pool.query(query, params);
    return environment ? result.rows[0] : result.rows;
  },

  async findActive(botId, gateway, environment) {
    const result = await pool.query(
      'SELECT * FROM payment_gateway_configs WHERE bot_id = $1 AND gateway = $2 AND environment = $3 AND is_active = true',
      [botId, gateway, environment]
    );
    return result.rows[0];
  },

  async create(configData) {
    const {
      bot_id,
      gateway,
      environment,
      access_token,
      secret_key,
      webhook_secret,
      webhook_url,
      public_key,
      is_active = false,
      metadata
    } = configData;

    const result = await pool.query(
      `INSERT INTO payment_gateway_configs (
        bot_id, gateway, environment, access_token, secret_key, webhook_secret,
        webhook_url, public_key, is_active, metadata
      ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10) 
      RETURNING *`,
      [
        bot_id,
        gateway,
        environment,
        access_token || null,
        secret_key || null,
        webhook_secret || null,
        webhook_url || null,
        public_key || null,
        is_active,
        metadata ? JSON.stringify(metadata) : null
      ]
    );
    return result.rows[0];
  },

  async update(id, updateData) {
    const updateFields = [];
    const values = [];
    let paramCount = 1;

    const fields = [
      'access_token', 'secret_key', 'webhook_secret', 'webhook_url',
      'public_key', 'is_active', 'metadata'
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
      `UPDATE payment_gateway_configs SET ${updateFields.join(', ')} WHERE id = $${paramCount} RETURNING *`,
      values
    );
    return result.rows[0];
  },

  async delete(id) {
    const result = await pool.query(
      'DELETE FROM payment_gateway_configs WHERE id = $1 RETURNING id',
      [id]
    );
    return result.rows[0];
  },

  // Desativa todas as configurações de um gateway/ambiente para um bot
  async deactivateOthers(botId, gateway, environment, excludeId) {
    const result = await pool.query(
      `UPDATE payment_gateway_configs 
       SET is_active = false, updated_at = CURRENT_TIMESTAMP
       WHERE bot_id = $1 AND gateway = $2 AND environment = $3 AND id != $4`,
      [botId, gateway, environment, excludeId]
    );
    return result.rows;
  }
};

module.exports = PaymentGatewayConfig;

