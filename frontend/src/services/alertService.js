import api from './api';

const alertService = {
  /**
   * Lista todos os alertas
   */
  async getAlerts(botId = null) {
    const params = botId ? { botId } : {};
    const response = await api.get('/alerts', { params });
    return response.data.alerts || [];
  },

  /**
   * Cria um novo alerta
   */
  async createAlert(alertData) {
    const response = await api.post('/alerts', alertData);
    return response.data.alert;
  },

  /**
   * Atualiza um alerta existente
   */
  async updateAlert(alertId, alertData) {
    const response = await api.put(`/alerts/${alertId}`, alertData);
    return response.data.alert;
  },

  /**
   * Remove um alerta
   */
  async deleteAlert(alertId) {
    const response = await api.delete(`/alerts/${alertId}`);
    return response.data;
  },

  /**
   * Obtém um alerta específico
   */
  async getAlert(alertId) {
    const response = await api.get(`/alerts/${alertId}`);
    return response.data.alert;
  },

  /**
   * Processa alertas que estão prontos para serem enviados
   */
  async processAlerts(botId = null) {
    const data = botId ? { bot_id: botId } : {};
    const response = await api.post('/alerts/process', data);
    return response.data;
  }
};

export default alertService;

