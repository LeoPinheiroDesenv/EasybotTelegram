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
  },

  initializeBot: async (id) => {
    const response = await api.post(`/bots/${id}/initialize`);
    return response.data;
  },

  stopBot: async (id) => {
    const response = await api.post(`/bots/${id}/stop`);
    return response.data;
  },

  getBotStatus: async (id) => {
    const response = await api.get(`/bots/${id}/status`);
    return response.data;
  },

  validateAndActivate: async (id) => {
    const response = await api.post(`/bots/${id}/validate-and-activate`);
    return response.data;
  },

  uploadMedia: async (id, file, mediaNumber) => {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('media_number', mediaNumber);

    const response = await api.post(`/bots/${id}/media/upload`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    return response.data;
  },

  deleteMedia: async (id, mediaNumber) => {
    const response = await api.delete(`/bots/${id}/media`, {
      data: { media_number: mediaNumber }
    });
    return response.data;
  },

  setWebhook: async (botId, webhookUrl = null) => {
    const data = webhookUrl ? { url: webhookUrl } : {};
    const response = await api.post(`/telegram/webhook/${botId}/set`, data);
    return response.data;
  },

  updateInviteLink: async (id) => {
    const response = await api.post(`/bots/${id}/update-invite-link`);
    return response.data;
  }
};

export default botService;

