import api from './api';

const botFatherService = {
  /**
   * Obtém informações do bot
   */
  getBotInfo: async (botId) => {
    const response = await api.get(`/bots/${botId}/botfather/info`);
    return response.data;
  },

  /**
   * Define o nome do bot
   */
  setMyName: async (botId, name) => {
    const response = await api.post(`/bots/${botId}/botfather/set-name`, { name });
    return response.data;
  },

  /**
   * Define a descrição do bot
   */
  setMyDescription: async (botId, description, languageCode = null) => {
    const data = { description };
    if (languageCode) {
      data.language_code = languageCode;
    }
    const response = await api.post(`/bots/${botId}/botfather/set-description`, data);
    return response.data;
  },

  /**
   * Define a descrição curta do bot
   */
  setMyShortDescription: async (botId, shortDescription, languageCode = null) => {
    const data = { short_description: shortDescription };
    if (languageCode) {
      data.language_code = languageCode;
    }
    const response = await api.post(`/bots/${botId}/botfather/set-short-description`, data);
    return response.data;
  },

  /**
   * Define o texto "sobre" do bot
   */
  setMyAbout: async (botId, about, languageCode = null) => {
    const data = { about };
    if (languageCode) {
      data.language_code = languageCode;
    }
    const response = await api.post(`/bots/${botId}/botfather/set-about`, data);
    return response.data;
  },

  /**
   * Define o botão de menu do chat
   */
  setChatMenuButton: async (botId, type, text = null, url = null) => {
    const data = { type };
    if (text) {
      data.text = text;
    }
    if (url) {
      data.url = url;
    }
    const response = await api.post(`/bots/${botId}/botfather/set-menu-button`, data);
    return response.data;
  },

  /**
   * Define os direitos padrão de administrador
   */
  setMyDefaultAdministratorRights: async (botId, rights, forChannels = false) => {
    const response = await api.post(`/bots/${botId}/botfather/set-default-admin-rights`, {
      rights,
      for_channels: forChannels
    });
    return response.data;
  },

  /**
   * Deleta comandos do bot
   */
  deleteMyCommands: async (botId, scope = null, languageCode = null) => {
    const data = {};
    if (scope) {
      data.scope = scope;
    }
    if (languageCode) {
      data.language_code = languageCode;
    }
    const response = await api.post(`/bots/${botId}/botfather/delete-commands`, data);
    return response.data;
  },
};

export default botFatherService;

