// Rotas para processamento de pagamentos
const express = require('express');
const router = express.Router();
const { body, query } = require('express-validator');
const paymentController = require('../controllers/paymentController');
const { authenticateToken } = require('../middlewares/auth');

// Processar pagamento PIX
router.post('/pix',
  authenticateToken,
  [
    body('payment_plan_id').notEmpty().withMessage('Payment plan ID is required'),
    body('bot_id').notEmpty().withMessage('Bot ID is required'),
    body('payer.email').isEmail().withMessage('Valid payer email is required'),
    body('payer.first_name').optional().isString(),
    body('payer.last_name').optional().isString(),
    body('contact_id').optional().isInt()
  ],
  paymentController.processPixPayment
);

// Processar pagamento com cartão de crédito
router.post('/credit-card',
  authenticateToken,
  [
    body('payment_plan_id').notEmpty().withMessage('Payment plan ID is required'),
    body('bot_id').notEmpty().withMessage('Bot ID is required'),
    body('payment_method_id').optional().isString().withMessage('Payment method ID must be a string'),
    body('card_data').optional().isObject().withMessage('Card data must be an object'),
    body('contact_id').optional().isInt()
  ],
  paymentController.processCreditCardPayment
);

// Buscar transação por ID
router.get('/transactions/:id',
  authenticateToken,
  paymentController.getTransaction
);

// Listar transações
router.get('/transactions',
  authenticateToken,
  [
    query('bot_id').optional().isInt(),
    query('contact_id').optional().isInt(),
    query('payment_plan_id').optional().isInt(),
    query('status').optional().isIn(['pending', 'processing', 'approved', 'rejected', 'cancelled', 'refunded']),
    query('payment_method').optional().isIn(['pix', 'credit_card']),
    query('limit').optional().isInt({ min: 1, max: 100 }),
    query('offset').optional().isInt({ min: 0 })
  ],
  paymentController.getTransactions
);

// Estatísticas de pagamentos
router.get('/stats',
  authenticateToken,
  [
    query('botId').notEmpty().withMessage('Bot ID is required')
  ],
  paymentController.getPaymentStats
);

module.exports = router;

