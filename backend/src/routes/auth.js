// Define os endpoints e mapeia para os Controllers
const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const authController = require('../controllers/authController');
const { authenticateToken } = require('../middlewares/auth');

// Login
router.post('/login',
  [
    body('email').isEmail().withMessage('Valid email is required'),
    body('password').notEmpty().withMessage('Password is required'),
    body('twoFactorToken')
      .optional({ nullable: true, checkFalsy: true })
      .custom((value) => {
        if (value === null || value === undefined || value === '') {
          return true;
        }
        return typeof value === 'string';
      })
      .withMessage('Two factor token must be a string or null')
  ],
  authController.login
);

// Verify two-factor authentication code
router.post('/verify-2fa',
  [
    body('userId').notEmpty().withMessage('User ID is required'),
    body('token').notEmpty().withMessage('Two-factor authentication token is required')
  ],
  authController.verifyTwoFactor
);

// Get current user
router.get('/me', authenticateToken, authController.getCurrentUser);

// Setup 2FA (generate secret and QR code)
router.get('/2fa/setup', authenticateToken, authController.setup2FA);

// Verify and enable 2FA
router.post('/2fa/verify',
  authenticateToken,
  [
    body('token').notEmpty().withMessage('Verification token is required')
  ],
  authController.verifyAndEnable2FA
);

// Disable 2FA
router.post('/2fa/disable', authenticateToken, authController.disable2FA);

module.exports = router;

