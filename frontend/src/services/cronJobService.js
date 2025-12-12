import api from './api';

const cronJobService = {
  /**
   * Lista todos os cron jobs
   */
  getAll: async () => {
    try {
      const response = await api.get('/cron-jobs');
      return response?.data || response || { success: false, cron_jobs: [], default_cron_jobs: [] };
    } catch (error) {
      // Retorna estrutura padrão em caso de erro
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Erro ao carregar cron jobs',
        cron_jobs: error.response?.data?.cron_jobs || [],
        default_cron_jobs: error.response?.data?.default_cron_jobs || []
      };
    }
  },

  /**
   * Busca um cron job específico
   */
  getById: async (id) => {
    try {
      const response = await api.get(`/cron-jobs/${id}`);
      return response?.data || response || { success: false };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Erro ao buscar cron job'
      };
    }
  },

  /**
   * Cria um novo cron job
   */
  create: async (cronJobData) => {
    try {
      const response = await api.post('/cron-jobs', cronJobData);
      return response?.data || response || { success: false };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Erro ao criar cron job',
        errors: error.response?.data?.errors
      };
    }
  },

  /**
   * Cria um cron job padrão do sistema
   */
  createDefault: async (name) => {
    try {
      const response = await api.post('/cron-jobs/create-default', { name });
      return response?.data || response || { success: false };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Erro ao criar cron job padrão'
      };
    }
  },

  /**
   * Atualiza um cron job
   */
  update: async (id, cronJobData) => {
    try {
      const response = await api.put(`/cron-jobs/${id}`, cronJobData);
      return response?.data || response || { success: false };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Erro ao atualizar cron job',
        errors: error.response?.data?.errors
      };
    }
  },

  /**
   * Deleta um cron job
   */
  delete: async (id) => {
    try {
      const response = await api.delete(`/cron-jobs/${id}`);
      return response?.data || response || { success: false };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Erro ao deletar cron job'
      };
    }
  },

  /**
   * Testa um cron job (executa manualmente)
   */
  test: async (id) => {
    try {
      const response = await api.post(`/cron-jobs/${id}/test`);
      return response?.data || response || { success: false };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Erro ao testar cron job'
      };
    }
  },
};

export default cronJobService;
