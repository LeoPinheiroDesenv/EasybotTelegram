// Serviço para integração com Stripe (Cartão de Crédito)
const Stripe = require('stripe');

class StripeService {
  constructor() {
    this.stripe = null;
    this._initialize();
  }

  _initialize() {
    const secretKey = process.env.STRIPE_SECRET_KEY;
    
    if (!secretKey) {
      console.warn('STRIPE_SECRET_KEY não configurado - funcionalidades do Stripe estarão desabilitadas');
      return;
    }

    try {
      this.stripe = new Stripe(secretKey, {
        apiVersion: '2024-11-20.acacia',
      });
    } catch (error) {
      console.error('Erro ao inicializar Stripe:', error);
      this.stripe = null;
    }
  }

  _checkInitialized() {
    if (!this.stripe) {
      throw new Error('Stripe não está configurado. Configure STRIPE_SECRET_KEY nas variáveis de ambiente.');
    }
  }

  /**
   * Cria uma instância temporária do Stripe com credenciais específicas
   */
  _createInstanceWithCredentials(secretKey) {
    if (!secretKey) {
      throw new Error('Secret key is required');
    }
    
    return new Stripe(secretKey, {
      apiVersion: '2024-11-20.acacia',
    });
  }

  /**
   * Cria um pagamento com cartão de crédito
   * @param {Object} paymentData - Dados do pagamento
   * @param {number} paymentData.amount - Valor em centavos (ex: 2990 = R$ 29,90)
   * @param {string} paymentData.currency - Moeda (BRL)
   * @param {string} paymentData.payment_method_id - ID do método de pagamento do Stripe
   * @param {string} paymentData.description - Descrição do pagamento
   * @param {string} paymentData.metadata.external_reference - Referência externa
   * @param {string} paymentData.secretKey - Chave secreta do Stripe (opcional, usa a padrão se não fornecido)
   * @returns {Promise<Object>} Dados do pagamento criado
   */
  async createPaymentIntent(paymentData) {
    const { secretKey, ...restData } = paymentData;
    
    let stripeInstance;
    if (secretKey) {
      // Usa credenciais fornecidas
      stripeInstance = this._createInstanceWithCredentials(secretKey);
    } else {
      // Usa instância padrão
      this._checkInitialized();
      stripeInstance = this.stripe;
    }
    
    try {
      const {
        amount,
        currency = 'brl',
        payment_method_id,
        description,
        metadata = {}
      } = restData;

      const paymentIntent = await stripeInstance.paymentIntents.create({
        amount: Math.round(amount * 100), // Converte para centavos
        currency: currency.toLowerCase(),
        payment_method: payment_method_id,
        description: description || 'Pagamento via Cartão de Crédito',
        confirm: true,
        return_url: process.env.STRIPE_RETURN_URL || `${process.env.FRONTEND_URL}/payment/success`,
        metadata: {
          ...metadata,
          integration: 'easy-bot-telegram'
        }
      });

      return {
        id: paymentIntent.id,
        client_secret: paymentIntent.client_secret,
        status: paymentIntent.status,
        amount: paymentIntent.amount / 100, // Converte de volta para reais
        currency: paymentIntent.currency,
        metadata: paymentIntent.metadata
      };
    } catch (error) {
      console.error('Erro ao criar pagamento no Stripe:', error);
      throw new Error(`Erro ao processar pagamento: ${error.message}`);
    }
  }

  /**
   * Cria um Payment Method (método de pagamento)
   * @param {Object} cardData - Dados do cartão
   * @param {string} cardData.number - Número do cartão
   * @param {number} cardData.exp_month - Mês de expiração
   * @param {number} cardData.exp_year - Ano de expiração
   * @param {string} cardData.cvc - Código de segurança
   * @returns {Promise<Object>} Payment Method criado
   */
  async createPaymentMethod(cardData, secretKey = null) {
    let stripeInstance;
    if (secretKey) {
      stripeInstance = this._createInstanceWithCredentials(secretKey);
    } else {
      this._checkInitialized();
      stripeInstance = this.stripe;
    }
    
    try {
      const paymentMethod = await stripeInstance.paymentMethods.create({
        type: 'card',
        card: {
          number: cardData.number,
          exp_month: cardData.exp_month,
          exp_year: cardData.exp_year,
          cvc: cardData.cvc
        },
        billing_details: cardData.billing_details || {}
      });

      return {
        id: paymentMethod.id,
        type: paymentMethod.type,
        card: paymentMethod.card
      };
    } catch (error) {
      console.error('Erro ao criar método de pagamento no Stripe:', error);
      throw new Error(`Erro ao processar cartão: ${error.message}`);
    }
  }

  /**
   * Busca informações de um Payment Intent
   * @param {string} paymentIntentId - ID do Payment Intent
   * @returns {Promise<Object>} Dados do pagamento
   */
  async getPaymentIntent(paymentIntentId) {
    this._checkInitialized();
    try {
      const paymentIntent = await this.stripe.paymentIntents.retrieve(paymentIntentId);
      return paymentIntent;
    } catch (error) {
      console.error('Erro ao buscar pagamento no Stripe:', error);
      throw new Error(`Erro ao buscar pagamento: ${error.message}`);
    }
  }

  /**
   * Processa webhook do Stripe
   * @param {Object} event - Evento do webhook
   * @param {string} signature - Assinatura do webhook
   * @returns {Promise<Object>} Dados processados
   */
  async processWebhook(event, signature) {
    this._checkInitialized();
    try {
      const webhookSecret = process.env.STRIPE_WEBHOOK_SECRET;
      
      if (webhookSecret) {
        // Verifica a assinatura do webhook
        event = this.stripe.webhooks.constructEvent(
          event,
          signature,
          webhookSecret
        );
      }

      if (event.type === 'payment_intent.succeeded') {
        const paymentIntent = event.data.object;
        return {
          payment_id: paymentIntent.id,
          status: 'succeeded',
          amount: paymentIntent.amount / 100,
          currency: paymentIntent.currency,
          metadata: paymentIntent.metadata
        };
      }

      if (event.type === 'payment_intent.payment_failed') {
        const paymentIntent = event.data.object;
        return {
          payment_id: paymentIntent.id,
          status: 'failed',
          amount: paymentIntent.amount / 100,
          currency: paymentIntent.currency,
          metadata: paymentIntent.metadata
        };
      }

      return null;
    } catch (error) {
      console.error('Erro ao processar webhook do Stripe:', error);
      throw error;
    }
  }

  /**
   * Mapeia status do Stripe para status interno
   * @param {string} stripeStatus - Status do Stripe
   * @returns {string} Status interno
   */
  mapStatus(stripeStatus) {
    const statusMap = {
      'requires_payment_method': 'pending',
      'requires_confirmation': 'pending',
      'requires_action': 'processing',
      'processing': 'processing',
      'requires_capture': 'approved',
      'succeeded': 'approved',
      'canceled': 'cancelled'
    };

    return statusMap[stripeStatus] || 'pending';
  }
}

module.exports = new StripeService();

