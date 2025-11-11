// Define os endpoints e mapeia para os Controllers
const express = require('express');
const router = express.Router();
const logController = require('../controllers/logController');
const { authenticateToken, authorizeRoles } = require('../middlewares/auth');

// Get all logs (admin only)
router.get(
  '/',
  authenticateToken,
  authorizeRoles('admin'),
  logController.getAllLogs
);

// Get log by ID (admin only)
router.get(
  '/:id',
  authenticateToken,
  authorizeRoles('admin'),
  logController.getLogById
);

module.exports = router;

