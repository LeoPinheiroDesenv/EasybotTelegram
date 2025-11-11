// Lida com a requisição e resposta HTTP (camada Web)
const contactService = require('../services/contactService');
const { validationResult } = require('express-validator');

const getAllContacts = async (req, res) => {
  try {
    const botId = req.query.botId;
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    const filters = {
      search: req.query.search,
      telegram_status: req.query.telegram_status,
      active: req.query.active !== undefined ? req.query.active === 'true' : undefined
    };

    const pagination = {
      page: parseInt(req.query.page) || 1,
      limit: parseInt(req.query.limit) || 10
    };

    const result = await contactService.getAllContacts(botId, filters, pagination);
    res.json(result);
  } catch (error) {
    console.error('Get all contacts error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const getContactById = async (req, res) => {
  try {
    const { id } = req.params;
    const botId = req.query.botId;
    
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    const contact = await contactService.getContactById(id, botId);
    res.json({ contact });
  } catch (error) {
    if (error.message === 'Contact not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Get contact by id error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const createContact = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const contact = await contactService.createContact(req.body);
    res.status(201).json({ contact });
  } catch (error) {
    if (error.message.includes('already exists')) {
      return res.status(400).json({ error: error.message });
    }
    console.error('Create contact error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const updateContact = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { id } = req.params;
    const botId = req.body.bot_id || req.query.botId;
    
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    const contact = await contactService.updateContact(id, botId, req.body);
    
    if (!contact) {
      return res.status(400).json({ error: 'No fields to update' });
    }

    res.json({ contact });
  } catch (error) {
    if (error.message === 'Contact not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Update contact error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const deleteContact = async (req, res) => {
  try {
    const { id } = req.params;
    const botId = req.query.botId;
    
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    await contactService.deleteContact(id, botId);
    res.json({ message: 'Contact deleted successfully' });
  } catch (error) {
    if (error.message === 'Contact not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Delete contact error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const blockContact = async (req, res) => {
  try {
    const { id } = req.params;
    const botId = req.query.botId;
    
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    const contact = await contactService.blockContact(id, botId);
    res.json({ contact, message: 'Contact blocked successfully' });
  } catch (error) {
    if (error.message === 'Contact not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Block contact error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const getStats = async (req, res) => {
  try {
    const botId = req.query.botId;
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    const stats = await contactService.getStats(botId);
    res.json({ stats });
  } catch (error) {
    console.error('Get stats error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const getLatest = async (req, res) => {
  try {
    const botId = req.query.botId;
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    const limit = parseInt(req.query.limit) || 10;
    const latest = await contactService.getLatest(botId, limit);
    res.json({ contacts: latest });
  } catch (error) {
    console.error('Get latest contacts error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

module.exports = {
  getAllContacts,
  getContactById,
  createContact,
  updateContact,
  deleteContact,
  blockContact,
  getStats,
  getLatest
};

