// Lógica de Negócios (Regras da Aplicação - o "core")
const Bot = require('../models/Bot');

const botService = {
  async getAllBots(userId) {
    return await Bot.findByUserId(userId);
  },

  async getBotById(id, userId) {
    const bot = await Bot.findById(id, userId);
    if (!bot) {
      throw new Error('Bot not found');
    }
    return bot;
  },

  async createBot(botData) {
    const { user_id, token } = botData;

    // Check if bot with same token already exists for this user
    if (await Bot.tokenExists(token, user_id)) {
      throw new Error('Bot with this token already exists');
    }

    return await Bot.create(botData);
  },

  async updateBot(id, userId, botData) {
    // Check if bot exists and belongs to user
    const existingBot = await Bot.findById(id, userId);
    if (!existingBot) {
      throw new Error('Bot not found');
    }

    // Check if token is already taken by another bot
    if (botData.token && await Bot.tokenExists(botData.token, userId, id)) {
      throw new Error('Bot with this token already exists');
    }

    return await Bot.update(id, userId, botData);
  },

  async deleteBot(id, userId) {
    const bot = await Bot.findById(id, userId);
    if (!bot) {
      throw new Error('Bot not found');
    }
    return await Bot.delete(id, userId);
  },

  async validateBotToken(token) {
    // Simple validation - check token format (Telegram bot tokens follow pattern: number:alphanumeric)
    const tokenPattern = /^\d+:[A-Za-z0-9_-]+$/;
    if (!tokenPattern.test(token)) {
      throw new Error('Invalid token format. Telegram bot tokens should follow the pattern: number:alphanumeric');
    }
    
    // Valida o token com a API do Telegram
    try {
      const axios = require('axios');
      const response = await axios.get(`https://api.telegram.org/bot${token}/getMe`);
      if (response.data && response.data.ok) {
        return { 
          valid: true, 
          message: 'Token válido!',
          botInfo: response.data.result
        };
      }
      throw new Error('Token inválido');
    } catch (error) {
      if (error.response && error.response.status === 401) {
        throw new Error('Token inválido ou expirado');
      }
      throw new Error('Erro ao validar token com o Telegram');
    }
  }
};

module.exports = botService;

