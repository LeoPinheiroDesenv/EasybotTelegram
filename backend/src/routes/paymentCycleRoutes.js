const express = require('express');
const router = express.Router();
const paymentCycleController = require('../controllers/paymentCycleController');
const { authenticateToken, authorizeRoles } = require('../middlewares/auth');

// Todas as rotas requerem autenticação
router.use(authenticateToken);

// Rotas públicas (apenas autenticação)
router.get('/', paymentCycleController.getAllCycles);
router.get('/active', paymentCycleController.getActiveCycles);
router.get('/:id', paymentCycleController.getCycleById);

// Rotas administrativas (requerem admin)
router.post('/', authorizeRoles('admin'), paymentCycleController.createCycle);
router.put('/:id', authorizeRoles('admin'), paymentCycleController.updateCycle);
router.delete('/:id', authorizeRoles('admin'), paymentCycleController.deleteCycle);

module.exports = router;

