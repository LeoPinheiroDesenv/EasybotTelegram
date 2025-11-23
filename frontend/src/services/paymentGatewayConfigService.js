import api from './api';

const paymentGatewayConfigService = {
  async getConfigs(botId) {
    const response = await api.get('/payment-gateway-configs', {
      params: { botId }
    });
    return response.data.configs;
  },

  async getConfig(botId, gateway, environment) {
    const response = await api.get('/payment-gateway-configs/config', {
      params: { botId, gateway, environment }
    });
    return response.data.config;
  },

  async createOrUpdateConfig(configData) {
    const response = await api.post('/payment-gateway-configs', configData);
    return response.data.config;
  },

  async updateConfig(id, configData) {
    const response = await api.put(`/payment-gateway-configs/${id}`, configData);
    return response.data.config;
  },

  async deleteConfig(id) {
    const response = await api.delete(`/payment-gateway-configs/${id}`);
    return response.data;
  }
};

export default paymentGatewayConfigService;

