import api from './api';

const contactService = {
  getAllContacts: async (botId) => {
    const response = await api.get('/contacts', {
      params: {
        botId,
      },
    });
    return response.data.contacts;
  },

  getContactById: async (id, botId) => {
    const response = await api.get(`/contacts/${id}`, {
      params: {
        botId,
      },
    });
    return response.data.contact;
  },

  blockContact: async (id, botId) => {
    const response = await api.post(`/contacts/${id}/block`, { botId });
    return response.data;
  },

  sendExpirationReminder: async (id, botId) => {
    const response = await api.post(`/contacts/${id}/send-reminder`, { botId });
    return response.data;
  },

  sendGroupExpirationReminder: async (botId) => {
    const response = await api.post('/contacts/send-group-reminder', { botId });
    return response.data;
  },
};

export default contactService;