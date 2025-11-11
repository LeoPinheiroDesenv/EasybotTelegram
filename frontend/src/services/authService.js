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

const authService = {
  login: async (email, password, twoFactorToken = null) => {
    const response = await api.post('/auth/login', { email, password, twoFactorToken });
    return response.data;
  },

  verifyTwoFactor: async (userId, token) => {
    const response = await api.post('/auth/verify-2fa', { userId, token });
    return response.data;
  },

  setup2FA: async () => {
    const response = await api.get('/auth/2fa/setup');
    return response.data;
  },

  verifyAndEnable2FA: async (token) => {
    const response = await api.post('/auth/2fa/verify', { token });
    return response.data;
  },

  disable2FA: async () => {
    const response = await api.post('/auth/2fa/disable');
    return response.data;
  },

  getCurrentUser: async () => {
    const response = await api.get('/auth/me');
    return response.data.user;
  }
};

export default authService;

