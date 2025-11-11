// Lida com a requisição e resposta HTTP (camada Web)
const logService = require('../services/logService');

const getAllLogs = async (req, res) => {
  try {
    const { level, limit = 100, offset = 0, startDate, endDate } = req.query;
    
    const filters = {
      level: level || undefined,
      limit: parseInt(limit),
      offset: parseInt(offset),
      startDate: startDate || undefined,
      endDate: endDate || undefined
    };

    const result = await logService.getAllLogs(filters);
    res.json(result);
  } catch (error) {
    console.error('Get all logs error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const getLogById = async (req, res) => {
  try {
    const { id } = req.params;
    const log = await logService.getLogById(id);
    res.json({ log });
  } catch (error) {
    if (error.message === 'Log not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Get log by id error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

module.exports = {
  getAllLogs,
  getLogById
};

