// Define os endpoints e mapeia para os Controllers
const express = require('express');
const router = express.Router();
const { body, query } = require('express-validator');
const paymentPlanController = require('../controllers/paymentPlanController');
const { authenticateToken } = require('../middlewares/auth');

// Get all payment plans
router.get(
  '/',
  authenticateToken,
  paymentPlanController.getAllPaymentPlans
);

// Get payment plan by ID
router.get(
  '/:id',
  authenticateToken,
  paymentPlanController.getPaymentPlanById
);

// Create payment plan
router.post(
  '/',
  authenticateToken,
  [
    body('bot_id').notEmpty().withMessage('Bot ID is required'),
    body('title').notEmpty().withMessage('Title is required'),
    body('price').isFloat({ min: 0 }).withMessage('Price must be a positive number'),
    body('charge_period').isIn(['day', 'month', 'year']).withMessage('Charge period must be day, month, or year'),
    body('cycle').optional().isInt({ min: 1 }).withMessage('Cycle must be a positive integer'),
    body('payment_cycle_id').optional().isInt({ min: 1 }).withMessage('Payment cycle ID must be a positive integer'),
    body('message').optional().isString(),
    body('pix_message').optional().isString(),
    body('active').optional().isBoolean()
  ],
  paymentPlanController.createPaymentPlan
);

// Update payment plan
router.put(
  '/:id',
  authenticateToken,
  [
    body('bot_id').notEmpty().withMessage('Bot ID is required'),
    body('title').optional().notEmpty().withMessage('Title cannot be empty'),
    body('price').optional().isFloat({ min: 0 }).withMessage('Price must be a positive number'),
    body('charge_period').optional().isIn(['day', 'month', 'year']).withMessage('Charge period must be day, month, or year'),
    body('cycle').optional().isInt({ min: 1 }).withMessage('Cycle must be a positive integer'),
    body('payment_cycle_id').optional().isInt({ min: 1 }).withMessage('Payment cycle ID must be a positive integer'),
    body('message').optional().isString(),
    body('pix_message').optional().isString(),
    body('active').optional().isBoolean()
  ],
  paymentPlanController.updatePaymentPlan
);

// Delete payment plan
router.delete(
  '/:id',
  authenticateToken,
  paymentPlanController.deletePaymentPlan
);

module.exports = router;

