const PaymentCycle = require('../models/PaymentCycle');

const paymentCycleService = {
  async getAllCycles() {
    return await PaymentCycle.findAll();
  },

  async getCycleById(id) {
    const cycle = await PaymentCycle.findById(id);
    if (!cycle) {
      throw new Error('Payment cycle not found');
    }
    return cycle;
  },

  async createCycle(data) {
    // Verificar se já existe um ciclo com o mesmo nome
    const existing = await PaymentCycle.findByName(data.name);
    if (existing) {
      throw new Error('Payment cycle with this name already exists');
    }

    return await PaymentCycle.create(data);
  },

  async updateCycle(id, data) {
    const cycle = await PaymentCycle.findById(id);
    if (!cycle) {
      throw new Error('Payment cycle not found');
    }

    // Se estiver alterando o nome, verificar se não existe outro com o mesmo nome
    if (data.name && data.name !== cycle.name) {
      const existing = await PaymentCycle.findByName(data.name);
      if (existing) {
        throw new Error('Payment cycle with this name already exists');
      }
    }

    return await PaymentCycle.update(id, data);
  },

  async deleteCycle(id) {
    const cycle = await PaymentCycle.findById(id);
    if (!cycle) {
      throw new Error('Payment cycle not found');
    }

    return await PaymentCycle.delete(id);
  },

  async getActiveCycles() {
    return await PaymentCycle.findActive();
  }
};

module.exports = paymentCycleService;

