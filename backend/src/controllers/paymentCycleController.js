const paymentCycleService = require('../services/paymentCycleService');

const paymentCycleController = {
  async getAllCycles(req, res) {
    try {
      const cycles = await paymentCycleService.getAllCycles();
      res.json({ cycles });
    } catch (error) {
      console.error('Error getting payment cycles:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  },

  async getCycleById(req, res) {
    try {
      const { id } = req.params;
      const cycle = await paymentCycleService.getCycleById(id);
      res.json({ cycle });
    } catch (error) {
      if (error.message === 'Payment cycle not found') {
        return res.status(404).json({ error: error.message });
      }
      console.error('Error getting payment cycle:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  },

  async createCycle(req, res) {
    try {
      const { name, days, description, is_active } = req.body;

      if (!name || days === undefined) {
        return res.status(400).json({ error: 'Name and days are required' });
      }

      const cycle = await paymentCycleService.createCycle({
        name,
        days: parseInt(days),
        description,
        is_active: is_active !== undefined ? is_active : true
      });

      res.status(201).json({ cycle });
    } catch (error) {
      if (error.message.includes('already exists')) {
        return res.status(409).json({ error: error.message });
      }
      console.error('Error creating payment cycle:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  },

  async updateCycle(req, res) {
    try {
      const { id } = req.params;
      const { name, days, description, is_active } = req.body;

      const updateData = {};
      if (name !== undefined) updateData.name = name;
      if (days !== undefined) updateData.days = parseInt(days);
      if (description !== undefined) updateData.description = description;
      if (is_active !== undefined) updateData.is_active = is_active;

      const cycle = await paymentCycleService.updateCycle(id, updateData);
      res.json({ cycle });
    } catch (error) {
      if (error.message === 'Payment cycle not found') {
        return res.status(404).json({ error: error.message });
      }
      if (error.message.includes('already exists')) {
        return res.status(409).json({ error: error.message });
      }
      console.error('Error updating payment cycle:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  },

  async deleteCycle(req, res) {
    try {
      const { id } = req.params;
      await paymentCycleService.deleteCycle(id);
      res.json({ message: 'Payment cycle deleted successfully' });
    } catch (error) {
      if (error.message === 'Payment cycle not found') {
        return res.status(404).json({ error: error.message });
      }
      console.error('Error deleting payment cycle:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  },

  async getActiveCycles(req, res) {
    try {
      const cycles = await paymentCycleService.getActiveCycles();
      res.json({ cycles });
    } catch (error) {
      console.error('Error getting active payment cycles:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
};

module.exports = paymentCycleController;

