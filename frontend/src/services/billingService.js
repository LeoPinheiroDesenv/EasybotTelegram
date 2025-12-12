import api from './api';

const billingService = {
  /**
   * Obtém faturamento mensal atual
   */
  async getMonthlyBilling() {
    const response = await api.get('/billing/monthly');
    return response.data;
  },

  /**
   * Obtém faturamento com filtros
   */
  async getBilling(filters = {}) {
    const response = await api.get('/billing', { params: filters });
    return response.data;
  },

  /**
   * Obtém dados para gráfico mensal
   */
  async getChartData(months = 12) {
    const response = await api.get('/billing/chart', { params: { months } });
    return response.data;
  },

  /**
   * Obtém faturamento total
   */
  async getTotalBilling() {
    const response = await api.get('/billing/total');
    return response.data;
  },

  /**
   * Obtém estatísticas do dashboard
   */
  async getDashboardStatistics() {
    const response = await api.get('/billing/dashboard-stats');
    return response.data;
  },

  /**
   * Reenvia o link do grupo para o usuário de uma transação
   */
  async resendGroupLink(transactionId) {
    const response = await api.post(`/payments/${transactionId}/resend-group-link`);
    return response.data;
  }
};

export default billingService;

