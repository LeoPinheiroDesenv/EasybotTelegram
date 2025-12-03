import api from './api';

const logService = {
  getAllLogs: async (filters = {}) => {
    const params = new URLSearchParams();
    if (filters.level) params.append('level', filters.level);
    if (filters.bot_id) params.append('bot_id', filters.bot_id);
    if (filters.limit) params.append('limit', filters.limit);
    if (filters.offset) params.append('offset', filters.offset);
    if (filters.startDate) params.append('startDate', filters.startDate);
    if (filters.endDate) params.append('endDate', filters.endDate);
    if (filters.user_email) params.append('user_email', filters.user_email);
    if (filters.message) params.append('message', filters.message);

    const response = await api.get(`/logs?${params.toString()}`);
    return response.data;
  },

  getLogById: async (id) => {
    const response = await api.get(`/logs/${id}`);
    return response.data.log;
  }
};

export default logService;

