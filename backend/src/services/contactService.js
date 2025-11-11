// Lógica de Negócios (Regras da Aplicação - o "core")
const Contact = require('../models/Contact');

const contactService = {
  async getAllContacts(botId, filters = {}, pagination = {}) {
    const { page = 1, limit = 10 } = pagination;
    const offset = (page - 1) * limit;

    const contacts = await Contact.findByBotId(botId, {
      ...filters,
      limit,
      offset
    });
    
    const total = await Contact.countByBotId(botId, filters);
    
    return {
      contacts,
      pagination: {
        page,
        limit,
        total,
        totalPages: Math.ceil(total / limit)
      }
    };
  },

  async getContactById(id, botId) {
    const contact = await Contact.findById(id, botId);
    if (!contact) {
      throw new Error('Contact not found');
    }
    return contact;
  },

  async getContactByTelegramId(botId, telegramId) {
    return await Contact.findByTelegramId(botId, telegramId);
  },

  async createContact(contactData) {
    const { bot_id, telegram_id } = contactData;

    // Check if contact already exists
    const existing = await Contact.findByTelegramId(bot_id, telegram_id);
    if (existing) {
      throw new Error('Contact with this Telegram ID already exists for this bot');
    }

    return await Contact.create(contactData);
  },

  async updateContact(id, botId, contactData) {
    const existing = await Contact.findById(id, botId);
    if (!existing) {
      throw new Error('Contact not found');
    }

    return await Contact.update(id, botId, contactData);
  },

  async deleteContact(id, botId) {
    const contact = await Contact.findById(id, botId);
    if (!contact) {
      throw new Error('Contact not found');
    }
    return await Contact.delete(id, botId);
  },

  async blockContact(id, botId) {
    const contact = await Contact.findById(id, botId);
    if (!contact) {
      throw new Error('Contact not found');
    }
    return await Contact.block(id, botId);
  },

  async getStats(botId) {
    return await Contact.getStatsByBotId(botId);
  },

  async getLatest(botId, limit = 10) {
    return await Contact.getLatest(botId, limit);
  }
};

module.exports = contactService;

