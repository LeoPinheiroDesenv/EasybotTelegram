const paymentGatewayConfigService = require('../services/paymentGatewayConfigService');
const { validationResult } = require('express-validator');

const getConfigs = async (req, res) => {
  try {
    const botId = req.query.botId;
    
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    const configs = await paymentGatewayConfigService.getConfigsByBot(botId);
    res.json({ configs });
  } catch (error) {
    console.error('Erro ao buscar configurações:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const getConfig = async (req, res) => {
  try {
    const { botId, gateway, environment } = req.query;
    
    if (!botId || !gateway || !environment) {
      return res.status(400).json({ error: 'Bot ID, gateway and environment are required' });
    }

    const config = await paymentGatewayConfigService.getConfig(botId, gateway, environment);
    
    if (!config) {
      return res.status(404).json({ error: 'Configuration not found' });
    }

    res.json({ config });
  } catch (error) {
    console.error('Erro ao buscar configuração:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const createOrUpdateConfig = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const config = await paymentGatewayConfigService.createOrUpdateConfig(req.body);
    res.status(201).json({ config });
  } catch (error) {
    console.error('Erro ao salvar configuração:', error);
    res.status(500).json({ error: error.message || 'Internal server error' });
  }
};

const updateConfig = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { id } = req.params;
    const config = await paymentGatewayConfigService.updateConfig(id, req.body);
    
    res.json({ config });
  } catch (error) {
    if (error.message === 'Configuration not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Erro ao atualizar configuração:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const deleteConfig = async (req, res) => {
  try {
    const { id } = req.params;
    await paymentGatewayConfigService.deleteConfig(id);
    res.json({ message: 'Configuration deleted successfully' });
  } catch (error) {
    if (error.message === 'Configuration not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Erro ao excluir configuração:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

module.exports = {
  getConfigs,
  getConfig,
  createOrUpdateConfig,
  updateConfig,
  deleteConfig
};

