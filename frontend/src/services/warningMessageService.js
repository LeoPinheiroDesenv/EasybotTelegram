import api from './api';

const warningMessageService = {
  getWarningMessages: async (botId) => {
    const response = await api.get(`/bots/${botId}/warning-messages`);
    return response.data;
  },

  createWarningMessage: async (botId, messageData) => {
    const response = await api.post(`/bots/${botId}/warning-messages`, messageData);
    return response.data;
  },

  updateWarningMessage: async (botId, messageId, messageData) => {
    const response = await api.put(`/bots/${botId}/warning-messages/${messageId}`, messageData);
    return response.data;
  },

  deleteWarningMessage: async (botId, messageId) => {
    const response = await api.delete(`/bots/${botId}/warning-messages/${messageId}`);
    return response.data;
  },
};

export default warningMessageService;
