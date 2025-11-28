import api from './api';

const botAdministratorService = {
  getAll: async (botId) => {
    const response = await api.get('/bot-administrators', {
      params: { bot_id: botId }
    });
    return response.data.administrators;
  },

  getById: async (id) => {
    const response = await api.get(`/bot-administrators/${id}`);
    return response.data.administrator;
  },

  create: async (data) => {
    const response = await api.post('/bot-administrators', data);
    return response.data.administrator;
  },

  update: async (id, data) => {
    const response = await api.put(`/bot-administrators/${id}`, data);
    return response.data.administrator;
  },

  delete: async (id) => {
    const response = await api.delete(`/bot-administrators/${id}`);
    return response.data;
  }
};

export default botAdministratorService;

