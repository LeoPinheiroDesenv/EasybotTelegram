const express = require('express');
const router = express.Router();
const { body, query } = require('express-validator');
const paymentGatewayConfigController = require('../controllers/paymentGatewayConfigController');
const { authenticateToken, authorizeRoles } = require('../middlewares/auth');

// Listar todas as configurações de um bot
router.get('/',
  authenticateToken,
  [
    query('botId').notEmpty().withMessage('Bot ID is required')
  ],
  paymentGatewayConfigController.getConfigs
);

// Buscar configuração específica
router.get('/config',
  authenticateToken,
  [
    query('botId').notEmpty().withMessage('Bot ID is required'),
    query('gateway').isIn(['mercadopago', 'stripe']).withMessage('Gateway must be mercadopago or stripe'),
    query('environment').isIn(['test', 'production']).withMessage('Environment must be test or production')
  ],
  paymentGatewayConfigController.getConfig
);

// Criar ou atualizar configuração
router.post('/',
  authenticateToken,
  [
    body('bot_id').notEmpty().withMessage('Bot ID is required'),
    body('gateway').isIn(['mercadopago', 'stripe']).withMessage('Gateway must be mercadopago or stripe'),
    body('environment').isIn(['test', 'production']).withMessage('Environment must be test or production'),
    body('access_token').optional().isString(),
    body('secret_key').optional().isString(),
    body('webhook_secret').optional().isString(),
    body('webhook_url').optional().isString().isURL(),
    body('public_key').optional().isString(),
    body('is_active').optional().isBoolean()
  ],
  paymentGatewayConfigController.createOrUpdateConfig
);

// Atualizar configuração
router.put('/:id',
  authenticateToken,
  [
    body('access_token').optional().isString(),
    body('secret_key').optional().isString(),
    body('webhook_secret').optional().isString(),
    body('webhook_url').optional().isString().isURL(),
    body('public_key').optional().isString(),
    body('is_active').optional().isBoolean()
  ],
  paymentGatewayConfigController.updateConfig
);

// Excluir configuração
router.delete('/:id',
  authenticateToken,
  paymentGatewayConfigController.deleteConfig
);

module.exports = router;

