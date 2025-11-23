import api from './api';

const groupManagementService = {
  /**
   * Adiciona um membro ao grupo
   */
  async addMember(botId, contactId, reason = null) {
    const response = await api.post(`/bots/${botId}/group/add-member`, {
      contact_id: contactId,
      reason: reason
    });
    return response.data;
  },

  /**
   * Remove um membro do grupo
   */
  async removeMember(botId, contactId, reason = null) {
    const response = await api.post(`/bots/${botId}/group/remove-member`, {
      contact_id: contactId,
      reason: reason
    });
    return response.data;
  },

  /**
   * Verifica o status de um membro no grupo
   */
  async checkMemberStatus(botId, contactId) {
    const response = await api.get(`/bots/${botId}/group/member-status/${contactId}`);
    return response.data;
  },

  /**
   * Lista informações do grupo
   */
  async getGroupInfo(botId) {
    const response = await api.get(`/bots/${botId}/group/info`);
    return response.data;
  },

  /**
   * Obtém estatísticas de gerenciamento de grupo
   */
  async getStatistics(botId, days = 30) {
    const response = await api.get(`/bots/${botId}/group/statistics`, {
      params: { days }
    });
    return response.data;
  },

  /**
   * Obtém histórico de ações para um contato
   */
  async getContactHistory(botId, contactId) {
    const response = await api.get(`/bots/${botId}/group/contact-history/${contactId}`);
    return response.data;
  }
};

export default groupManagementService;

