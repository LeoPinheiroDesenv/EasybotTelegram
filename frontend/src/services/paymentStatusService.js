import api from './api';

const paymentStatusService = {
  /**
   * Obtém status de pagamento de um contato específico
   */
  async getContactStatus(contactId) {
    const response = await api.get(`/payment-status/contact/${contactId}`);
    return response.data;
  },

  /**
   * Obtém status de pagamentos de todos os contatos de um bot
   */
  async getBotStatuses(botId, filters = {}) {
    const response = await api.get(`/payment-status/bot/${botId}`, {
      params: filters
    });
    return response.data;
  },

  /**
   * Verifica e processa pagamentos expirados
   */
  async checkExpiredPayments(botId = null) {
    const url = botId 
      ? `/payment-status/check-expired/${botId}`
      : '/payment-status/check-expired';
    const response = await api.post(url);
    return response.data;
  },

  /**
   * Verifica e notifica pagamentos próximos de expirar
   */
  async checkExpiringPayments(botId = null, days = 7) {
    const url = botId 
      ? `/payment-status/check-expiring/${botId}`
      : '/payment-status/check-expiring';
    const response = await api.post(url, null, {
      params: { days }
    });
    return response.data;
  },

  /**
   * Obtém detalhes completos da transação incluindo metadata do gateway
   */
  async getTransactionDetails(transactionId) {
    const response = await api.get(`/payment-status/transaction/${transactionId}`);
    return response.data;
  }
};

export default paymentStatusService;

