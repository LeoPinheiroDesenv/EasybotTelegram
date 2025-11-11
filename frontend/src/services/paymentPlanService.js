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

const paymentPlanService = {
  async getAllPaymentPlans(botId) {
    const response = await api.get('/payment-plans', {
      params: { botId }
    });
    return response.data.paymentPlans;
  },

  async getPaymentPlanById(id, botId) {
    const response = await api.get(`/payment-plans/${id}`, {
      params: { botId }
    });
    return response.data.paymentPlan;
  },

  async createPaymentPlan(paymentPlanData) {
    const response = await api.post('/payment-plans', paymentPlanData);
    return response.data.paymentPlan;
  },

  async updatePaymentPlan(id, paymentPlanData) {
    const response = await api.put(`/payment-plans/${id}`, paymentPlanData);
    return response.data.paymentPlan;
  },

  async deletePaymentPlan(id, botId) {
    const response = await api.delete(`/payment-plans/${id}`, {
      params: { botId }
    });
    return response.data;
  }
};

export default paymentPlanService;

