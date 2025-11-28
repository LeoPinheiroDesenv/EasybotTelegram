import api from './api';

const telegramGroupService = {
  getAll: async (botId) => {
    const response = await api.get('/telegram-groups', {
      params: { bot_id: botId }
    });
    return response.data.groups;
  },

  getById: async (id) => {
    const response = await api.get(`/telegram-groups/${id}`);
    return response.data.group;
  },

  create: async (data) => {
    const response = await api.post('/telegram-groups', data);
    return response.data.group;
  },

  update: async (id, data) => {
    const response = await api.put(`/telegram-groups/${id}`, data);
    return response.data.group;
  },

  delete: async (id) => {
    const response = await api.delete(`/telegram-groups/${id}`);
    return response.data;
  },

  updateInviteLink: async (id) => {
    const response = await api.put(`/telegram-groups/${id}`, {
      update_invite_link: true
    });
    return response.data.group;
  },

  copyInviteLink: async (link) => {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(link);
      return true;
    }
    return false;
  }
};

export default telegramGroupService;

