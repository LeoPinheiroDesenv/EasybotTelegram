// Define os endpoints e mapeia para os Controllers
const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const contactController = require('../controllers/contactController');
const { authenticateToken } = require('../middlewares/auth');

// Get all contacts
router.get(
  '/',
  authenticateToken,
  contactController.getAllContacts
);

// Get contact stats
router.get(
  '/stats',
  authenticateToken,
  contactController.getStats
);

// Get latest contacts
router.get(
  '/latest',
  authenticateToken,
  contactController.getLatest
);

// Get contact by ID
router.get(
  '/:id',
  authenticateToken,
  contactController.getContactById
);

// Create contact
router.post(
  '/',
  authenticateToken,
  [
    body('bot_id').notEmpty().withMessage('Bot ID is required'),
    body('telegram_id').isInt().withMessage('Telegram ID must be a number')
  ],
  contactController.createContact
);

// Update contact
router.put(
  '/:id',
  authenticateToken,
  contactController.updateContact
);

// Delete contact
router.delete(
  '/:id',
  authenticateToken,
  contactController.deleteContact
);

// Block contact
router.post(
  '/:id/block',
  authenticateToken,
  contactController.blockContact
);

module.exports = router;

