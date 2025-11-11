// Lida com a requisição e resposta HTTP (camada Web)
const botService = require('../services/botService');
const { validationResult } = require('express-validator');

const getAllBots = async (req, res) => {
  try {
    const bots = await botService.getAllBots(req.user.id);
    res.json({ bots });
  } catch (error) {
    console.error('Get all bots error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const getBotById = async (req, res) => {
  try {
    const { id } = req.params;
    const bot = await botService.getBotById(id, req.user.id);
    res.json({ bot });
  } catch (error) {
    if (error.message === 'Bot not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Get bot by id error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const createBot = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const botData = {
      ...req.body,
      user_id: req.user.id
    };

    const bot = await botService.createBot(botData);
    
    // Inicializa o bot do Telegram se estiver ativo
    if (bot.active && bot.token) {
      const telegramService = require('../services/telegramService');
      await telegramService.initializeBot(bot.id, bot.token);
    }
    
    res.status(201).json({ bot });
  } catch (error) {
    if (error.message === 'Bot with this token already exists') {
      return res.status(400).json({ error: error.message });
    }
    console.error('Create bot error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const updateBot = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { id } = req.params;
    const bot = await botService.updateBot(id, req.user.id, req.body);
    
    if (!bot) {
      return res.status(400).json({ error: 'No fields to update' });
    }

    // Reinicializa o bot do Telegram se necessário
    const telegramService = require('../services/telegramService');
    if (bot.active && bot.token) {
      await telegramService.initializeBot(bot.id, bot.token);
    } else {
      await telegramService.stopBot(bot.id);
    }

    res.json({ bot });
  } catch (error) {
    if (error.message === 'Bot not found' || error.message === 'Bot with this token already exists') {
      return res.status(error.message === 'Bot not found' ? 404 : 400).json({ error: error.message });
    }
    console.error('Update bot error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const deleteBot = async (req, res) => {
  try {
    const { id } = req.params;
    
    // Para o bot do Telegram antes de deletar
    const telegramService = require('../services/telegramService');
    await telegramService.stopBot(id);
    
    await botService.deleteBot(id, req.user.id);
    res.json({ message: 'Bot deleted successfully' });
  } catch (error) {
    if (error.message === 'Bot not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Delete bot error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const validateBot = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { token } = req.body;
    const result = await botService.validateBotToken(token);
    res.json(result);
  } catch (error) {
    if (error.message.includes('Invalid token format') || error.message.includes('Token inválido')) {
      return res.status(400).json({ valid: false, error: error.message });
    }
    console.error('Validate bot error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const initializeBot = async (req, res) => {
  try {
    const { id } = req.params;
    
    // Verifica se o bot pertence ao usuário
    const bot = await botService.getBotById(id, req.user.id);
    
    if (!bot.active) {
      return res.status(400).json({ error: 'Bot está inativo' });
    }
    
    if (!bot.token) {
      return res.status(400).json({ error: 'Token do bot não configurado' });
    }
    
    const telegramService = require('../services/telegramService');
    const result = await telegramService.initializeBot(id, bot.token);
    
    if (result.success) {
      res.json(result);
    } else {
      res.status(400).json(result);
    }
  } catch (error) {
    if (error.message === 'Bot not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Initialize bot error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const stopBot = async (req, res) => {
  try {
    const { id } = req.params;
    
    // Verifica se o bot pertence ao usuário
    await botService.getBotById(id, req.user.id);
    
    const telegramService = require('../services/telegramService');
    const result = await telegramService.stopBot(id);
    
    if (result.success) {
      res.json(result);
    } else {
      res.status(400).json(result);
    }
  } catch (error) {
    if (error.message === 'Bot not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Stop bot error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const getBotStatus = async (req, res) => {
  try {
    const { id } = req.params;
    
    // Verifica se o bot pertence ao usuário
    await botService.getBotById(id, req.user.id);
    
    const telegramService = require('../services/telegramService');
    const status = telegramService.getBotStatus(id);
    
    res.json(status);
  } catch (error) {
    if (error.message === 'Bot not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Get bot status error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

module.exports = {
  getAllBots,
  getBotById,
  createBot,
  updateBot,
  deleteBot,
  validateBot,
  initializeBot,
  stopBot,
  getBotStatus
};

