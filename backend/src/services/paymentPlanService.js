// Lógica de Negócios (Regras da Aplicação - o "core")
const PaymentPlan = require('../models/PaymentPlan');

const paymentPlanService = {
  async getAllPaymentPlans(botId, activeOnly = false) {
    return await PaymentPlan.findByBotId(botId, activeOnly);
  },

  async getPaymentPlanById(id, botId) {
    const paymentPlan = await PaymentPlan.findById(id, botId);
    if (!paymentPlan) {
      throw new Error('Payment plan not found');
    }
    return paymentPlan;
  },

  async createPaymentPlan(paymentPlanData) {
    const { bot_id, title } = paymentPlanData;

    // Check if payment plan with same title already exists for this bot
    if (await PaymentPlan.findByBotIdAndTitle(bot_id, title)) {
      throw new Error('Payment plan with this title already exists for this bot');
    }

    return await PaymentPlan.create(paymentPlanData);
  },

  async updatePaymentPlan(id, botId, paymentPlanData) {
    // Check if payment plan exists and belongs to bot
    const existingPlan = await PaymentPlan.findById(id, botId);
    if (!existingPlan) {
      throw new Error('Payment plan not found');
    }

    // Check if title is already taken by another plan
    if (paymentPlanData.title && await PaymentPlan.findByBotIdAndTitle(botId, paymentPlanData.title, id)) {
      throw new Error('Payment plan with this title already exists for this bot');
    }

    return await PaymentPlan.update(id, botId, paymentPlanData);
  },

  async deletePaymentPlan(id, botId) {
    const plan = await PaymentPlan.findById(id, botId);
    if (!plan) {
      throw new Error('Payment plan not found');
    }
    return await PaymentPlan.delete(id, botId);
  }
};

module.exports = paymentPlanService;

