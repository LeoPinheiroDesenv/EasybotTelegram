import api from './api';

const userGroupService = {
  /**
   * Lista todos os grupos de usuários
   */
  async getAllGroups() {
    const response = await api.get('/user-groups');
    return response.data.groups;
  },

  /**
   * Obtém um grupo específico
   */
  async getGroupById(id) {
    const response = await api.get(`/user-groups/${id}`);
    return response.data.group;
  },

  /**
   * Cria um novo grupo
   */
  async createGroup(groupData) {
    const response = await api.post('/user-groups', groupData);
    return response.data.group;
  },

  /**
   * Atualiza um grupo
   */
  async updateGroup(id, groupData) {
    const response = await api.put(`/user-groups/${id}`, groupData);
    return response.data.group;
  },

  /**
   * Remove um grupo
   */
  async deleteGroup(id) {
    const response = await api.delete(`/user-groups/${id}`);
    return response.data;
  },

  /**
   * Obtém menus disponíveis
   * Retorna tanto a lista simples de menus quanto menus com labels
   */
  async getAvailableMenus() {
    const response = await api.get('/user-groups/menus/available');
    // Retorna o objeto completo com menus e menus_with_labels
    return response.data;
  },

  /**
   * Obtém bots disponíveis
   */
  async getAvailableBots() {
    const response = await api.get('/user-groups/bots/available');
    return response.data.bots;
  }
};

export default userGroupService;

