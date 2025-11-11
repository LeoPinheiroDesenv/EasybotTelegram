// Serviço principal para processamento de pagamentos
const Transaction = require('../models/Transaction');
const PaymentPlan = require('../models/PaymentPlan');
const PaymentGatewayConfig = require('../models/PaymentGatewayConfig');
const mercadopagoService = require('./mercadopagoService');
const stripeService = require('./stripeService');

const paymentService = {
  /**
   * Busca credenciais ativas do gateway
   */
  async _getGatewayCredentials(botId, gateway, environment = 'production') {
    // Primeiro tenta buscar do banco de dados
    const config = await PaymentGatewayConfig.findActive(botId, gateway, environment);
    
    if (config) {
      if (gateway === 'mercadopago') {
        return { accessToken: config.access_token, webhookUrl: config.webhook_url };
      } else if (gateway === 'stripe') {
        return { 
          secretKey: config.secret_key, 
          publicKey: config.public_key,
          webhookSecret: config.webhook_secret 
        };
      }
    }
    
    // Fallback para variáveis de ambiente
    if (gateway === 'mercadopago') {
      return { 
        accessToken: process.env.MERCADOPAGO_ACCESS_TOKEN,
        webhookUrl: process.env.MERCADOPAGO_WEBHOOK_URL
      };
    } else if (gateway === 'stripe') {
      return {
        secretKey: process.env.STRIPE_SECRET_KEY,
        publicKey: process.env.STRIPE_PUBLIC_KEY,
        webhookSecret: process.env.STRIPE_WEBHOOK_SECRET
      };
    }
    
    return null;
  },

  /**
   * Processa pagamento PIX via Mercado Pago
   */
  async processPixPayment(paymentData) {
    const {
      payment_plan_id,
      bot_id,
      contact_id,
      payer
    } = paymentData;

    // Busca o plano de pagamento
    const paymentPlan = await PaymentPlan.findById(payment_plan_id);
    if (!paymentPlan) {
      throw new Error('Payment plan not found');
    }

    if (!paymentPlan.active) {
      throw new Error('Payment plan is not active');
    }

    // Busca credenciais do gateway
    const credentials = await this._getGatewayCredentials(bot_id, 'mercadopago', 'production');
    if (!credentials || !credentials.accessToken) {
      throw new Error('Mercado Pago não está configurado para este bot');
    }

    // Cria transação no banco
    const transaction = await Transaction.create({
      contact_id: contact_id || null,
      payment_plan_id,
      bot_id,
      amount: parseFloat(paymentPlan.price),
      currency: 'BRL',
      payment_method: 'pix',
      gateway: 'mercadopago',
      status: 'pending'
    });

    try {
      // Cria pagamento no Mercado Pago com credenciais específicas
      const mpPayment = await mercadopagoService.createPixPayment({
        amount: paymentPlan.price,
        description: paymentPlan.title,
        external_reference: transaction.id.toString(),
        payer: {
          email: payer.email,
          first_name: payer.first_name || '',
          last_name: payer.last_name || '',
          identification: payer.identification || {}
        },
        accessToken: credentials.accessToken,
        webhookUrl: credentials.webhookUrl
      });

      // Atualiza transação com dados do gateway
      const pointOfInteraction = mpPayment.point_of_interaction?.transaction_data;
      
      await Transaction.update(transaction.id, {
        gateway_transaction_id: mpPayment.id.toString(),
        gateway_payment_id: mpPayment.id.toString(),
        gateway_status: mpPayment.status,
        status: mercadopagoService.mapStatus(mpPayment.status),
        pix_qr_code: pointOfInteraction?.qr_code || null,
        pix_qr_code_base64: pointOfInteraction?.qr_code_base64 || null,
        pix_ticket_url: pointOfInteraction?.ticket_url || null,
        pix_expiration_date: pointOfInteraction?.expiration_date 
          ? new Date(pointOfInteraction.expiration_date) 
          : null,
        metadata: {
          mp_payment_id: mpPayment.id,
          mp_status: mpPayment.status,
          mp_status_detail: mpPayment.status_detail
        }
      });

      // Busca transação atualizada
      const updatedTransaction = await Transaction.findById(transaction.id);

      return {
        transaction: updatedTransaction,
        pix_data: {
          qr_code: pointOfInteraction?.qr_code,
          qr_code_base64: pointOfInteraction?.qr_code_base64,
          ticket_url: pointOfInteraction?.ticket_url,
          expiration_date: pointOfInteraction?.expiration_date
        }
      };
    } catch (error) {
      // Atualiza transação com erro
      await Transaction.update(transaction.id, {
        status: 'rejected',
        gateway_status: 'error',
        metadata: { error: error.message }
      });

      throw error;
    }
  },

  /**
   * Processa pagamento com cartão de crédito via Stripe
   */
  async processCreditCardPayment(paymentData) {
    const {
      payment_plan_id,
      bot_id,
      contact_id,
      payment_method_id,
      card_data
    } = paymentData;

    // Busca o plano de pagamento
    const paymentPlan = await PaymentPlan.findById(payment_plan_id);
    if (!paymentPlan) {
      throw new Error('Payment plan not found');
    }

    if (!paymentPlan.active) {
      throw new Error('Payment plan is not active');
    }

    // Busca credenciais do gateway
    const credentials = await this._getGatewayCredentials(bot_id, 'stripe', 'production');
    if (!credentials || !credentials.secretKey) {
      throw new Error('Stripe não está configurado para este bot');
    }

    // Cria transação no banco
    const transaction = await Transaction.create({
      contact_id: contact_id || null,
      payment_plan_id,
      bot_id,
      amount: parseFloat(paymentPlan.price),
      currency: 'BRL',
      payment_method: 'credit_card',
      gateway: 'stripe',
      status: 'pending'
    });

    try {
      let paymentMethodId = payment_method_id;

      // Se não forneceu payment_method_id, cria um novo
      if (!paymentMethodId && card_data) {
        const paymentMethod = await stripeService.createPaymentMethod(card_data, credentials.secretKey);
        paymentMethodId = paymentMethod.id;
      }

      if (!paymentMethodId) {
        throw new Error('Payment method is required');
      }

      // Cria pagamento no Stripe com credenciais específicas
      const stripePayment = await stripeService.createPaymentIntent({
        amount: paymentPlan.price,
        currency: 'brl',
        payment_method_id: paymentMethodId,
        description: paymentPlan.title,
        metadata: {
          external_reference: transaction.id.toString(),
          payment_plan_id: payment_plan_id.toString(),
          bot_id: bot_id.toString()
        },
        secretKey: credentials.secretKey
      });

      // Atualiza transação com dados do gateway
      await Transaction.update(transaction.id, {
        gateway_transaction_id: stripePayment.id,
        gateway_payment_id: stripePayment.id,
        gateway_status: stripePayment.status,
        status: stripeService.mapStatus(stripePayment.status),
        metadata: {
          stripe_payment_intent_id: stripePayment.id,
          stripe_status: stripePayment.status,
          client_secret: stripePayment.client_secret
        }
      });

      // Busca transação atualizada
      const updatedTransaction = await Transaction.findById(transaction.id);

      return {
        transaction: updatedTransaction,
        client_secret: stripePayment.client_secret
      };
    } catch (error) {
      // Atualiza transação com erro
      await Transaction.update(transaction.id, {
        status: 'rejected',
        gateway_status: 'error',
        metadata: { error: error.message }
      });

      throw error;
    }
  },

  /**
   * Processa webhook do Mercado Pago
   */
  async processMercadoPagoWebhook(webhookData) {
    try {
      const webhookResult = await mercadopagoService.processWebhook(webhookData);
      
      if (!webhookResult) {
        return null;
      }

      // Busca transação pela referência externa
      const transaction = await Transaction.findById(parseInt(webhookResult.external_reference));
      
      if (!transaction) {
        console.error('Transação não encontrada:', webhookResult.external_reference);
        return null;
      }

      // Atualiza status da transação
      await Transaction.update(transaction.id, {
        status: mercadopagoService.mapStatus(webhookResult.status),
        gateway_status: webhookResult.status,
        gateway_payment_id: webhookResult.payment_id.toString(),
        metadata: {
          ...(transaction.metadata || {}),
          webhook_status: webhookResult.status,
          webhook_status_detail: webhookResult.status_detail
        }
      });

      return await Transaction.findById(transaction.id);
    } catch (error) {
      console.error('Erro ao processar webhook do Mercado Pago:', error);
      throw error;
    }
  },

  /**
   * Processa webhook do Stripe
   */
  async processStripeWebhook(event, signature) {
    try {
      const webhookResult = await stripeService.processWebhook(event, signature);
      
      if (!webhookResult) {
        return null;
      }

      // Busca transação pela referência externa no metadata
      const paymentIntent = await stripeService.getPaymentIntent(webhookResult.payment_id);
      const externalReference = paymentIntent.metadata?.external_reference;
      
      if (!externalReference) {
        console.error('Referência externa não encontrada no webhook do Stripe');
        return null;
      }

      const transaction = await Transaction.findById(parseInt(externalReference));
      
      if (!transaction) {
        console.error('Transação não encontrada:', externalReference);
        return null;
      }

      // Atualiza status da transação
      await Transaction.update(transaction.id, {
        status: stripeService.mapStatus(webhookResult.status),
        gateway_status: webhookResult.status,
        gateway_payment_id: webhookResult.payment_id,
        metadata: {
          ...(transaction.metadata || {}),
          webhook_status: webhookResult.status
        }
      });

      return await Transaction.findById(transaction.id);
    } catch (error) {
      console.error('Erro ao processar webhook do Stripe:', error);
      throw error;
    }
  },

  /**
   * Busca transação por ID
   */
  async getTransactionById(id) {
    return await Transaction.findById(id);
  },

  /**
   * Lista transações
   */
  async getTransactions(filters) {
    if (filters.bot_id) {
      return await Transaction.findByBotId(filters.bot_id, filters);
    }
    if (filters.contact_id) {
      return await Transaction.findByContactId(filters.contact_id);
    }
    if (filters.payment_plan_id) {
      return await Transaction.findByPaymentPlanId(filters.payment_plan_id);
    }
    return [];
  },

  /**
   * Busca estatísticas de pagamentos
   */
  async getPaymentStats(botId) {
    return await Transaction.getStats(botId);
  }
};

module.exports = paymentService;

