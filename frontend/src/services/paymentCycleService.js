import api from './api';

const paymentCycleService = {
  async getAllCycles() {
    const response = await api.get('/payment-cycles');
    return response.data.cycles;
  },

  async getCycleById(id) {
    const response = await api.get(`/payment-cycles/${id}`);
    return response.data.cycle;
  },

  async createCycle(cycleData) {
    const response = await api.post('/payment-cycles', cycleData);
    return response.data.cycle;
  },

  async updateCycle(id, cycleData) {
    const response = await api.put(`/payment-cycles/${id}`, cycleData);
    return response.data.cycle;
  },

  async deleteCycle(id) {
    const response = await api.delete(`/payment-cycles/${id}`);
    return response.data;
  },

  async getActiveCycles() {
    const response = await api.get('/payment-cycles/active');
    return response.data.cycles;
  }
};

export default paymentCycleService;

