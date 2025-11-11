import axios from 'axios';

const API_URL = process.env.REACT_APP_API_URL || 'http://localhost:5000/api';

const api = axios.create({
  baseURL: API_URL,
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

const botService = {
  getAllBots: async () => {
    const response = await api.get('/bots');
    return response.data.bots;
  },

  getBotById: async (id) => {
    const response = await api.get(`/bots/${id}`);
    return response.data.bot;
  },

  createBot: async (botData) => {
    const response = await api.post('/bots', botData);
    return response.data.bot;
  },

  updateBot: async (id, botData) => {
    const response = await api.put(`/bots/${id}`, botData);
    return response.data.bot;
  },

  deleteBot: async (id) => {
    const response = await api.delete(`/bots/${id}`);
    return response.data;
  },

  validateBot: async (token) => {
    const response = await api.post('/bots/validate', { token });
    return response.data;
  }
};

export default botService;

