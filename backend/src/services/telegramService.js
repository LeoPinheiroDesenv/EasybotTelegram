// Serviço para gerenciar bots do Telegram
const TelegramBot = require('node-telegram-bot-api');
const Bot = require('../models/Bot');
const Contact = require('../models/Contact');

// Armazenar instâncias dos bots ativos
const activeBots = new Map();

const telegramService = {
  /**
   * Inicializa um bot do Telegram
   */
  async initializeBot(botId, token) {
    try {
      // Se o bot já está ativo, remove a instância anterior
      if (activeBots.has(botId)) {
        const oldBot = activeBots.get(botId);
        oldBot.stopPolling();
        activeBots.delete(botId);
      }

      // Cria nova instância do bot
      const bot = new TelegramBot(token, { polling: true });

      // Busca dados do bot no banco
      const botData = await Bot.findById(botId);

      if (!botData || !botData.active) {
        return { success: false, message: 'Bot não encontrado ou inativo' };
      }

      // Configura handlers do bot
      telegramService.setupBotHandlers(bot, botData);

      // Armazena a instância do bot
      activeBots.set(botId, bot);

      return { success: true, message: 'Bot inicializado com sucesso' };
    } catch (error) {
      console.error(`Erro ao inicializar bot ${botId}:`, error);
      return { success: false, message: error.message };
    }
  },

  /**
   * Para um bot do Telegram
   */
  async stopBot(botId) {
    try {
      if (activeBots.has(botId)) {
        const bot = activeBots.get(botId);
        bot.stopPolling();
        activeBots.delete(botId);
        return { success: true, message: 'Bot parado com sucesso' };
      }
      return { success: false, message: 'Bot não está ativo' };
    } catch (error) {
      console.error(`Erro ao parar bot ${botId}:`, error);
      return { success: false, message: error.message };
    }
  },

  /**
   * Configura os handlers de mensagens do bot
   */
  setupBotHandlers(bot, botData) {
    // Handler para comando /start
    bot.onText(/\/start/, async (msg) => {
      const chatId = msg.chat.id;
      const userId = msg.from.id;
      const username = msg.from.username || msg.from.first_name;

      try {
        // Salva ou atualiza contato
        await this.saveContact(botData.id, userId, username, msg.from);

        // Envia mensagem de boas-vindas
        await this.sendWelcomeMessage(bot, chatId, botData);

        // Se há mídia configurada, envia
        if (botData.media_1_url) {
          await bot.sendPhoto(chatId, botData.media_1_url);
        }
        if (botData.media_2_url) {
          await bot.sendPhoto(chatId, botData.media_2_url);
        }
        if (botData.media_3_url) {
          await bot.sendPhoto(chatId, botData.media_3_url);
        }
      } catch (error) {
        console.error('Erro ao processar /start:', error);
        bot.sendMessage(chatId, 'Desculpe, ocorreu um erro. Tente novamente mais tarde.');
      }
    });

    // Handler para comando /comandos
    bot.onText(/\/comandos/, async (msg) => {
      const chatId = msg.chat.id;
      
      try {
        // Verifica se o usuário é administrador
        const isAdmin = await this.checkAdmin(botData.id, msg.from.id);
        
        if (isAdmin) {
          const commands = `
📋 *Comandos Disponíveis:*

/start - Iniciar o bot
/comandos - Ver esta lista de comandos
/status - Ver status do bot
          `;
          await bot.sendMessage(chatId, commands, { parse_mode: 'Markdown' });
        } else {
          await bot.sendMessage(chatId, 'Você não tem permissão para usar este comando.');
        }
      } catch (error) {
        console.error('Erro ao processar /comandos:', error);
      }
    });

    // Handler para mensagens de texto
    bot.on('message', async (msg) => {
      // Ignora comandos (já processados acima)
      if (msg.text && msg.text.startsWith('/')) {
        return;
      }

      const chatId = msg.chat.id;
      const userId = msg.from.id;
      const username = msg.from.username || msg.from.first_name;

      try {
        // Salva ou atualiza contato
        await this.saveContact(botData.id, userId, username, msg.from);
      } catch (error) {
        console.error('Erro ao salvar contato:', error);
      }
    });

    // Handler para erros
    bot.on('error', (error) => {
      console.error(`Erro no bot ${botData.id}:`, error);
    });

    // Handler para polling_error
    bot.on('polling_error', (error) => {
      console.error(`Erro de polling no bot ${botData.id}:`, error);
    });
  },

  /**
   * Envia mensagem de boas-vindas
   */
  async sendWelcomeMessage(bot, chatId, botData) {
    try {
      let message = '';

      // Mensagem superior (se configurada)
      if (botData.top_message) {
        message += botData.top_message + '\n\n';
      }

      // Mensagem inicial (se configurada)
      if (botData.initial_message) {
        message += botData.initial_message;
      } else {
        message += 'Bem-vindo! 👋';
      }

      // Envia mensagem
      const options = {};

      // Se há botão configurado, adiciona teclado
      if (botData.button_message && botData.activate_cta) {
        options.reply_markup = {
          inline_keyboard: [[
            { text: botData.button_message, url: botData.redirect_url || '#' }
          ]]
        };
      }

      await bot.sendMessage(chatId, message, options);
    } catch (error) {
      console.error('Erro ao enviar mensagem de boas-vindas:', error);
    }
  },

  /**
   * Salva ou atualiza contato
   */
  async saveContact(botId, telegramUserId, username, userData) {
    try {
      const contactData = {
        bot_id: botId,
        telegram_id: telegramUserId.toString(),
        name: userData.first_name || username || 'Usuário',
        telegram_username: username || null,
        metadata: {
          first_name: userData.first_name || null,
          last_name: userData.last_name || null,
          language_code: userData.language_code || null
        }
      };

      // Verifica se o contato já existe
      const existingContact = await Contact.findByTelegramId(botId, telegramUserId.toString());

      if (existingContact) {
        // Atualiza contato existente
        await Contact.update(existingContact.id, botId, {
          name: contactData.name || existingContact.name,
          telegram_username: contactData.telegram_username || existingContact.telegram_username,
          telegram_status: 'active',
          active: true,
          metadata: contactData.metadata
        });
      } else {
        // Cria novo contato
        await Contact.create({
          ...contactData,
          telegram_status: 'active',
          active: true
        });
      }
    } catch (error) {
      console.error('Erro ao salvar contato:', error);
      throw error;
    }
  },

  /**
   * Verifica se o usuário é administrador
   */
  async checkAdmin(botId, telegramUserId) {
    try {
      // TODO: Implementar verificação de administradores
      // Por enquanto, retorna false
      return false;
    } catch (error) {
      console.error('Erro ao verificar administrador:', error);
      return false;
    }
  },

  /**
   * Envia mensagem para um usuário específico
   */
  async sendMessage(botId, telegramUserId, message, options = {}) {
    try {
      if (!activeBots.has(botId)) {
        return { success: false, message: 'Bot não está ativo' };
      }

      const bot = activeBots.get(botId);
      await bot.sendMessage(telegramUserId, message, options);
      return { success: true };
    } catch (error) {
      console.error('Erro ao enviar mensagem:', error);
      return { success: false, message: error.message };
    }
  },

  /**
   * Inicializa todos os bots ativos
   */
  async initializeAllBots() {
    try {
      const bots = await Bot.findAll();
      
      for (const bot of bots) {
        if (bot.active && bot.token) {
          await this.initializeBot(bot.id, bot.token);
        }
      }

      return { success: true, message: `${activeBots.size} bots inicializados` };
    } catch (error) {
      console.error('Erro ao inicializar bots:', error);
      return { success: false, message: error.message };
    }
  },

  /**
   * Retorna status de um bot
   */
  getBotStatus(botId) {
    return {
      isActive: activeBots.has(botId),
      botId: botId
    };
  }
};

module.exports = telegramService;

