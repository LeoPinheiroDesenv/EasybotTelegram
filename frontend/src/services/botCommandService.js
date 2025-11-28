import api from './api';

const botCommandService = {
  /**
   * Lista todos os comandos de um bot
   */
  async getCommands(botId) {
    const response = await api.get(`/bots/${botId}/commands`);
    return response.data.commands || [];
  },

  /**
   * Cria um novo comando
   */
  async createCommand(botId, commandData) {
    const response = await api.post(`/bots/${botId}/commands`, commandData);
    return response.data.command;
  },

  /**
   * Atualiza um comando existente
   */
  async updateCommand(botId, commandId, commandData) {
    const response = await api.put(`/bots/${botId}/commands/${commandId}`, commandData);
    return response.data.command;
  },

  /**
   * Remove um comando
   */
  async deleteCommand(botId, commandId) {
    const response = await api.delete(`/bots/${botId}/commands/${commandId}`);
    return response.data;
  },

  /**
   * Registra comandos no Telegram
   */
  async registerCommands(botId) {
    const response = await api.post(`/bots/${botId}/commands/register`);
    return response.data;
  },

  /**
   * Obtém comandos registrados no Telegram
   */
  async getTelegramCommands(botId) {
    const response = await api.get(`/bots/${botId}/commands/telegram`);
    return response.data.commands || [];
  }
};

export default botCommandService;

