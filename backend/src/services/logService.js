// Lógica de Negócios (Regras da Aplicação - o "core")
const Log = require('../models/Log');

const logService = {
  async createLog(logData) {
    return await Log.create(logData);
  },

  async getAllLogs(filters = {}) {
    const logs = await Log.findAll(filters);
    const total = await Log.count(filters);
    return { logs, total };
  },

  async getLogById(id) {
    const log = await Log.findById(id);
    if (!log) {
      throw new Error('Log not found');
    }
    return log;
  }
};

module.exports = logService;

