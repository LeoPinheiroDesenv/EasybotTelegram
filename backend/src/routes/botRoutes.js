// Define os endpoints e mapeia para os Controllers
const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const botController = require('../controllers/botController');
const { authenticateToken } = require('../middlewares/auth');

// Get all bots (for authenticated user)
router.get(
  '/',
  authenticateToken,
  botController.getAllBots
);

// Get bot by ID
router.get(
  '/:id',
  authenticateToken,
  botController.getBotById
);

// Create bot
router.post(
  '/',
  authenticateToken,
  [
    body('name').notEmpty().withMessage('Bot name is required'),
    body('token').notEmpty().withMessage('Bot token is required'),
    body('telegram_group_id').optional().isString()
  ],
  botController.createBot
);

// Update bot
router.put(
  '/:id',
  authenticateToken,
  [
    body('name').optional().notEmpty().withMessage('Bot name cannot be empty'),
    body('token').optional().notEmpty().withMessage('Bot token cannot be empty'),
    body('telegram_group_id').optional().isString()
  ],
  botController.updateBot
);

// Delete bot
router.delete(
  '/:id',
  authenticateToken,
  botController.deleteBot
);

// Validate bot token
router.post(
  '/validate',
  authenticateToken,
  [
    body('token').notEmpty().withMessage('Token is required')
  ],
  botController.validateBot
);

// Initialize bot (start Telegram bot)
router.post(
  '/:id/initialize',
  authenticateToken,
  botController.initializeBot
);

// Stop bot (stop Telegram bot)
router.post(
  '/:id/stop',
  authenticateToken,
  botController.stopBot
);

// Get bot status
router.get(
  '/:id/status',
  authenticateToken,
  botController.getBotStatus
);

module.exports = router;

