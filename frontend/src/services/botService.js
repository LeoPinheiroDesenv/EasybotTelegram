import api from './api';

const botService = {
  getAllBots: async () => {
    const response = await api.get('/bots');
    return response.data.bots;
  },

  getBotById: async (id) => {
    const response = await api.get(`/bots/${id}`);
    return response.data.bot;
  },

  createBot: async (botData) => {
    const response = await api.post('/bots', botData);
    return response.data.bot;
  },

  updateBot: async (id, botData) => {
    const response = await api.put(`/bots/${id}`, botData);
    return response.data.bot;
  },

  deleteBot: async (id) => {
    const response = await api.delete(`/bots/${id}`);
    return response.data;
  },

  validateBot: async (token) => {
    const response = await api.post('/bots/validate', { token });
    return response.data;
  },

  validateTokenAndGroup: async (token, telegramGroupId) => {
    const response = await api.post('/bots/validate-token-and-group', { 
      token, 
      telegram_group_id: telegramGroupId 
    });
    return response.data;
  }
};

export default botService;

