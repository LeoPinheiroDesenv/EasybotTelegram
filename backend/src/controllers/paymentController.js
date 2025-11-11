// Controller para processamento de pagamentos
const paymentService = require('../services/paymentService');
const { validationResult } = require('express-validator');

const processPixPayment = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { payment_plan_id, bot_id, contact_id, payer } = req.body;

    const result = await paymentService.processPixPayment({
      payment_plan_id,
      bot_id,
      contact_id,
      payer
    });

    res.status(201).json(result);
  } catch (error) {
    console.error('Erro ao processar pagamento PIX:', error);
    if (error.message === 'Payment plan not found' || error.message === 'Payment plan is not active') {
      return res.status(404).json({ error: error.message });
    }
    res.status(500).json({ error: error.message || 'Erro ao processar pagamento PIX' });
  }
};

const processCreditCardPayment = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { payment_plan_id, bot_id, contact_id, payment_method_id, card_data } = req.body;

    const result = await paymentService.processCreditCardPayment({
      payment_plan_id,
      bot_id,
      contact_id,
      payment_method_id,
      card_data
    });

    res.status(201).json(result);
  } catch (error) {
    console.error('Erro ao processar pagamento com cartão:', error);
    if (error.message === 'Payment plan not found' || error.message === 'Payment plan is not active') {
      return res.status(404).json({ error: error.message });
    }
    res.status(500).json({ error: error.message || 'Erro ao processar pagamento com cartão' });
  }
};

const getTransaction = async (req, res) => {
  try {
    const { id } = req.params;
    const transaction = await paymentService.getTransactionById(id);

    if (!transaction) {
      return res.status(404).json({ error: 'Transaction not found' });
    }

    res.json({ transaction });
  } catch (error) {
    console.error('Erro ao buscar transação:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const getTransactions = async (req, res) => {
  try {
    const filters = {
      bot_id: req.query.bot_id,
      contact_id: req.query.contact_id,
      payment_plan_id: req.query.payment_plan_id,
      status: req.query.status,
      payment_method: req.query.payment_method,
      limit: req.query.limit ? parseInt(req.query.limit) : undefined,
      offset: req.query.offset ? parseInt(req.query.offset) : undefined
    };

    const transactions = await paymentService.getTransactions(filters);
    res.json({ transactions });
  } catch (error) {
    console.error('Erro ao buscar transações:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const getPaymentStats = async (req, res) => {
  try {
    const { botId } = req.query;
    
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    const stats = await paymentService.getPaymentStats(botId);
    res.json({ stats });
  } catch (error) {
    console.error('Erro ao buscar estatísticas:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const mercadoPagoWebhook = async (req, res) => {
  try {
    const webhookData = req.body;
    
    await paymentService.processMercadoPagoWebhook(webhookData);
    
    res.status(200).json({ received: true });
  } catch (error) {
    console.error('Erro ao processar webhook do Mercado Pago:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const stripeWebhook = async (req, res) => {
  try {
    const signature = req.headers['stripe-signature'];
    const event = req.body;
    
    await paymentService.processStripeWebhook(event, signature);
    
    res.status(200).json({ received: true });
  } catch (error) {
    console.error('Erro ao processar webhook do Stripe:', error);
    res.status(400).json({ error: `Webhook Error: ${error.message}` });
  }
};

module.exports = {
  processPixPayment,
  processCreditCardPayment,
  getTransaction,
  getTransactions,
  getPaymentStats,
  mercadoPagoWebhook,
  stripeWebhook
};

