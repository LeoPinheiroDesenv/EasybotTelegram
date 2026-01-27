import api from './api';

const authService = {
  login: async (email, password, twoFactorToken = null) => {
    const response = await api.post('/auth/login', { email, password, twoFactorToken });
    return response.data;
  },

  loginWithGoogle: async (token) => {
    const response = await api.post('/auth/google', { token });
    return response.data;
  },

  registerAdmin: async (userData) => {
    const response = await api.post('/auth/register/admin', userData);
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
  },

  requestPasswordReset: async (email) => {
    const response = await api.post('/auth/password/request-reset', { email });
    return response.data;
  },

  resetPassword: async (email, token, password, passwordConfirmation) => {
    const response = await api.post('/auth/password/reset', {
      email,
      token,
      password,
      password_confirmation: passwordConfirmation
    });
    return response.data;
  }
};

export default authService;
