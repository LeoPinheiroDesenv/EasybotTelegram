import api from './api';

const contactService = {
  async getAllContacts(botId, filters = {}, pagination = {}) {
    const response = await api.get('/contacts', {
      params: {
        botId,
        ...filters,
        ...pagination
      }
    });
    return response.data;
  },

  async getContactById(id, botId) {
    const response = await api.get(`/contacts/${id}`, {
      params: { botId }
    });
    return response.data.contact;
  },

  async createContact(contactData) {
    const response = await api.post('/contacts', contactData);
    return response.data.contact;
  },

  async updateContact(id, contactData) {
    const response = await api.put(`/contacts/${id}`, contactData);
    return response.data.contact;
  },

  async deleteContact(id, botId) {
    const response = await api.delete(`/contacts/${id}`, {
      params: { botId }
    });
    return response.data;
  },

  async blockContact(id, botId) {
    const response = await api.post(`/contacts/${id}/block`, null, {
      params: { botId }
    });
    return response.data;
  },

  async getStats(botId) {
    const response = await api.get('/contacts/stats', {
      params: { botId }
    });
    return response.data.stats;
  },

  async getLatest(botId, limit = 10) {
    const response = await api.get('/contacts/latest', {
      params: { botId, limit }
    });
    return response.data.contacts;
  },

  async syncGroupMembers(botId) {
    const response = await api.post('/contacts/sync-group-members', null, {
      params: { botId }
    });
    return response.data;
  },

  async sendExpirationReminder(id, botId) {
    const response = await api.post(`/contacts/${id}/send-expiration-reminder`, null, {
      params: { botId }
    });
    return response.data;
  },

  async sendGroupExpirationReminder(botId) {
    const response = await api.post('/contacts/send-group-expiration-reminder', null, {
      params: { botId }
    });
    return response.data;
  }
};

export default contactService;
