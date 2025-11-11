// Define os endpoints e mapeia para os Controllers
const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const userController = require('../controllers/userController');
const { authenticateToken, authorizeRoles } = require('../middlewares/auth');

// Get all users (admin only)
router.get(
  '/',
  authenticateToken,
  authorizeRoles('admin'),
  userController.getAllUsers
);

// Get user by ID
router.get(
  '/:id',
  authenticateToken,
  authorizeRoles('admin'),
  userController.getUserById
);

// Create user (admin only)
router.post(
  '/',
  authenticateToken,
  authorizeRoles('admin'),
  [
    body('name').notEmpty().withMessage('Name is required'),
    body('email').isEmail().withMessage('Valid email is required'),
    body('password')
      .isLength({ min: 6 })
      .withMessage('Password must be at least 6 characters'),
    body('role')
      .optional()
      .isIn(['admin', 'user'])
      .withMessage('Role must be either admin or user')
  ],
  userController.createUser
);

// Update user (admin only)
router.put(
  '/:id',
  authenticateToken,
  authorizeRoles('admin'),
  [
    body('name').optional().notEmpty().withMessage('Name cannot be empty'),
    body('email').optional().isEmail().withMessage('Valid email is required'),
    body('password')
      .optional()
      .isLength({ min: 6 })
      .withMessage('Password must be at least 6 characters'),
    body('role')
      .optional()
      .isIn(['admin', 'user'])
      .withMessage('Role must be either admin or user')
  ],
  userController.updateUser
);

// Delete user (admin only)
router.delete(
  '/:id',
  authenticateToken,
  authorizeRoles('admin'),
  userController.deleteUser
);

module.exports = router;

