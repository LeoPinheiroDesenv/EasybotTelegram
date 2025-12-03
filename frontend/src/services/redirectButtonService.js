import api from './api';

const redirectButtonService = {
  getRedirectButtons: async (botId) => {
    const response = await api.get(`/bots/${botId}/redirect-buttons`);
    return response.data.buttons;
  },

  createRedirectButton: async (botId, buttonData) => {
    const response = await api.post(`/bots/${botId}/redirect-buttons`, buttonData);
    return response.data.button;
  },

  updateRedirectButton: async (botId, buttonId, buttonData) => {
    const response = await api.put(`/bots/${botId}/redirect-buttons/${buttonId}`, buttonData);
    return response.data.button;
  },

  deleteRedirectButton: async (botId, buttonId) => {
    const response = await api.delete(`/bots/${botId}/redirect-buttons/${buttonId}`);
    return response.data;
  }
};

export default redirectButtonService;

