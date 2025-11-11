const PaymentGatewayConfig = require('../models/PaymentGatewayConfig');

const paymentGatewayConfigService = {
  async getConfigsByBot(botId) {
    const configs = await PaymentGatewayConfig.findByBotId(botId);
    return configs;
  },

  async getConfig(botId, gateway, environment) {
    const config = await PaymentGatewayConfig.findActive(botId, gateway, environment);
    return config;
  },

  async createOrUpdateConfig(configData) {
    const {
      bot_id,
      gateway,
      environment,
      access_token,
      secret_key,
      webhook_secret,
      webhook_url,
      public_key,
      is_active
    } = configData;

    // Verifica se já existe configuração para este bot/gateway/ambiente
    const existing = await PaymentGatewayConfig.findByBotAndGateway(
      bot_id,
      gateway,
      environment
    );

    let config;

    if (existing) {
      // Atualiza configuração existente
      config = await PaymentGatewayConfig.update(existing.id, {
        access_token,
        secret_key,
        webhook_secret,
        webhook_url,
        public_key,
        is_active
      });

      // Se está ativando, desativa outras do mesmo gateway/ambiente
      if (is_active) {
        await PaymentGatewayConfig.deactivateOthers(bot_id, gateway, environment, existing.id);
      }
    } else {
      // Cria nova configuração
      config = await PaymentGatewayConfig.create({
        bot_id,
        gateway,
        environment,
        access_token,
        secret_key,
        webhook_secret,
        webhook_url,
        public_key,
        is_active
      });

      // Se está ativando, desativa outras do mesmo gateway/ambiente
      if (is_active) {
        await PaymentGatewayConfig.deactivateOthers(bot_id, gateway, environment, config.id);
      }
    }

    return config;
  },

  async updateConfig(id, updateData) {
    const config = await PaymentGatewayConfig.findById(id);
    
    if (!config) {
      throw new Error('Configuration not found');
    }

    const updated = await PaymentGatewayConfig.update(id, updateData);

    // Se está ativando, desativa outras do mesmo gateway/ambiente
    if (updateData.is_active === true) {
      await PaymentGatewayConfig.deactivateOthers(
        config.bot_id,
        config.gateway,
        config.environment,
        id
      );
      // Reativa esta configuração
      await PaymentGatewayConfig.update(id, { is_active: true });
    }

    return updated || config;
  },

  async deleteConfig(id) {
    const config = await PaymentGatewayConfig.findById(id);
    
    if (!config) {
      throw new Error('Configuration not found');
    }

    await PaymentGatewayConfig.delete(id);
    return { success: true };
  },

  // Retorna as credenciais ativas para uso nos serviços de pagamento
  async getActiveCredentials(botId, gateway, environment = 'production') {
    const config = await PaymentGatewayConfig.findActive(botId, gateway, environment);
    
    if (!config) {
      return null;
    }

    if (gateway === 'mercadopago') {
      return {
        accessToken: config.access_token,
        webhookUrl: config.webhook_url
      };
    } else if (gateway === 'stripe') {
      return {
        secretKey: config.secret_key,
        publicKey: config.public_key,
        webhookSecret: config.webhook_secret
      };
    }

    return null;
  }
};

module.exports = paymentGatewayConfigService;

