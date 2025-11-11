// Serviço para integração com Mercado Pago (PIX)
const { MercadoPagoConfig, Payment } = require('mercadopago');

class MercadoPagoService {
  constructor() {
    this.client = null;
    this.payment = null;
    this._initialize();
  }

  _initialize() {
    const accessToken = process.env.MERCADOPAGO_ACCESS_TOKEN;
    
    if (!accessToken) {
      console.warn('MERCADOPAGO_ACCESS_TOKEN não configurado - funcionalidades do Mercado Pago estarão desabilitadas');
      return;
    }

    try {
      this.client = new MercadoPagoConfig({
        accessToken: accessToken,
        options: {
          timeout: 5000,
          idempotencyKey: 'abc'
        }
      });
      
      this.payment = new Payment(this.client);
    } catch (error) {
      console.error('Erro ao inicializar Mercado Pago:', error);
      this.client = null;
      this.payment = null;
    }
  }

  _checkInitialized() {
    if (!this.payment || !this.client) {
      throw new Error('Mercado Pago não está configurado. Configure MERCADOPAGO_ACCESS_TOKEN nas variáveis de ambiente.');
    }
  }

  /**
   * Cria uma instância temporária do serviço com credenciais específicas
   */
  _createInstanceWithCredentials(accessToken) {
    if (!accessToken) {
      throw new Error('Access token is required');
    }
    
    const client = new MercadoPagoConfig({
      accessToken: accessToken,
      options: {
        timeout: 5000,
        idempotencyKey: 'abc'
      }
    });
    
    return new Payment(client);
  }

  /**
   * Cria um pagamento PIX
   * @param {Object} paymentData - Dados do pagamento
   * @param {number} paymentData.amount - Valor do pagamento
   * @param {string} paymentData.description - Descrição do pagamento
   * @param {string} paymentData.external_reference - Referência externa (ID da transação)
   * @param {Object} paymentData.payer - Dados do pagador
   * @param {string} paymentData.accessToken - Token de acesso (opcional, usa o padrão se não fornecido)
   * @returns {Promise<Object>} Dados do pagamento criado
   */
  async createPixPayment(paymentData) {
    const { accessToken, ...restData } = paymentData;
    
    let paymentInstance;
    if (accessToken) {
      // Usa credenciais fornecidas
      paymentInstance = this._createInstanceWithCredentials(accessToken);
    } else {
      // Usa instância padrão
      this._checkInitialized();
      paymentInstance = this.payment;
    }
    
    try {
      const { amount, description, external_reference, payer, webhookUrl } = restData;
      
      const paymentRequest = {
        transaction_amount: parseFloat(amount),
        description: description || 'Pagamento via PIX',
        payment_method_id: 'pix',
        payer: {
          email: payer.email,
          first_name: payer.first_name || '',
          last_name: payer.last_name || '',
          identification: payer.identification || {}
        },
        external_reference: external_reference,
        notification_url: webhookUrl || process.env.MERCADOPAGO_WEBHOOK_URL || `${process.env.API_URL}/api/payments/webhook/mercadopago`,
        statement_descriptor: 'Easy Bot Telegram'
      };

      const payment = await paymentInstance.create({ body: paymentRequest });

      return {
        id: payment.id,
        status: payment.status,
        status_detail: payment.status_detail,
        transaction_amount: payment.transaction_amount,
        point_of_interaction: payment.point_of_interaction,
        external_reference: payment.external_reference
      };
    } catch (error) {
      console.error('Erro ao criar pagamento PIX no Mercado Pago:', error);
      throw new Error(`Erro ao processar pagamento PIX: ${error.message}`);
    }
  }

  /**
   * Busca informações de um pagamento
   * @param {string} paymentId - ID do pagamento no Mercado Pago
   * @returns {Promise<Object>} Dados do pagamento
   */
  async getPayment(paymentId) {
    this._checkInitialized();
    try {
      const payment = await this.payment.get({ id: paymentId });
      return payment;
    } catch (error) {
      console.error('Erro ao buscar pagamento no Mercado Pago:', error);
      throw new Error(`Erro ao buscar pagamento: ${error.message}`);
    }
  }

  /**
   * Processa webhook do Mercado Pago
   * @param {Object} webhookData - Dados do webhook
   * @returns {Promise<Object>} Dados processados
   */
  async processWebhook(webhookData) {
    this._checkInitialized();
    try {
      const { type, data } = webhookData;

      if (type === 'payment') {
        const paymentId = data.id;
        const payment = await this.getPayment(paymentId);
        
        return {
          payment_id: paymentId,
          status: payment.status,
          status_detail: payment.status_detail,
          external_reference: payment.external_reference,
          transaction_amount: payment.transaction_amount
        };
      }

      return null;
    } catch (error) {
      console.error('Erro ao processar webhook do Mercado Pago:', error);
      throw error;
    }
  }

  /**
   * Mapeia status do Mercado Pago para status interno
   * @param {string} mpStatus - Status do Mercado Pago
   * @returns {string} Status interno
   */
  mapStatus(mpStatus) {
    const statusMap = {
      'pending': 'pending',
      'approved': 'approved',
      'authorized': 'approved',
      'in_process': 'processing',
      'in_mediation': 'processing',
      'rejected': 'rejected',
      'cancelled': 'cancelled',
      'refunded': 'refunded',
      'charged_back': 'refunded'
    };

    return statusMap[mpStatus] || 'pending';
  }
}

module.exports = new MercadoPagoService();

