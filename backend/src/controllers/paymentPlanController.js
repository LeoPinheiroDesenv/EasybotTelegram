// Lida com a requisição e resposta HTTP (camada Web)
const paymentPlanService = require('../services/paymentPlanService');
const { validationResult } = require('express-validator');

const getAllPaymentPlans = async (req, res) => {
  try {
    const botId = req.query.botId;
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    const activeOnly = req.query.activeOnly === 'true';
    const paymentPlans = await paymentPlanService.getAllPaymentPlans(botId, activeOnly);
    res.json({ paymentPlans });
  } catch (error) {
    console.error('Get all payment plans error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const getPaymentPlanById = async (req, res) => {
  try {
    const { id } = req.params;
    const botId = req.query.botId;
    
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    const paymentPlan = await paymentPlanService.getPaymentPlanById(id, botId);
    res.json({ paymentPlan });
  } catch (error) {
    if (error.message === 'Payment plan not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Get payment plan by id error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const createPaymentPlan = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { bot_id } = req.body;
    
    // Verify bot belongs to user
    // This should be handled by a middleware, but for now we'll add it here
    const paymentPlan = await paymentPlanService.createPaymentPlan(req.body);
    res.status(201).json({ paymentPlan });
  } catch (error) {
    if (error.message === 'Payment plan with this title already exists for this bot') {
      return res.status(400).json({ error: error.message });
    }
    console.error('Create payment plan error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const updatePaymentPlan = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { id } = req.params;
    const botId = req.body.bot_id;
    
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    const paymentPlan = await paymentPlanService.updatePaymentPlan(id, botId, req.body);
    
    if (!paymentPlan) {
      return res.status(400).json({ error: 'No fields to update' });
    }

    res.json({ paymentPlan });
  } catch (error) {
    if (error.message === 'Payment plan not found' || error.message === 'Payment plan with this title already exists for this bot') {
      return res.status(error.message === 'Payment plan not found' ? 404 : 400).json({ error: error.message });
    }
    console.error('Update payment plan error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const deletePaymentPlan = async (req, res) => {
  try {
    const { id } = req.params;
    const botId = req.query.botId;
    
    if (!botId) {
      return res.status(400).json({ error: 'Bot ID is required' });
    }

    await paymentPlanService.deletePaymentPlan(id, botId);
    res.json({ message: 'Payment plan deleted successfully' });
  } catch (error) {
    if (error.message === 'Payment plan not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Delete payment plan error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

module.exports = {
  getAllPaymentPlans,
  getPaymentPlanById,
  createPaymentPlan,
  updatePaymentPlan,
  deletePaymentPlan
};

