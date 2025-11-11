// Lida com a requisição e resposta HTTP (camada Web)
const authService = require('../services/authService');
const twoFactorService = require('../services/twoFactorService');
const { validationResult } = require('express-validator');

const login = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { email, password, twoFactorToken } = req.body;
    const result = await authService.login(email, password, twoFactorToken);
    
    // Se requer 2FA, retorna sem token
    if (result.requiresTwoFactor) {
      return res.status(200).json(result);
    }
    
    res.json(result);
  } catch (error) {
    if (error.message === 'Invalid credentials' || 
        error.message === 'Account is deactivated' ||
        error.message === 'Invalid two-factor authentication code') {
      return res.status(error.message === 'Invalid credentials' ? 401 : 403).json({ error: error.message });
    }
    console.error('Login error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

// Verifica código 2FA após login inicial
const verifyTwoFactor = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const { userId, token } = req.body;
    
    // Busca o usuário
    const User = require('../models/User');
    const user = await User.findById(userId);
    
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    if (!user.two_factor_enabled) {
      return res.status(400).json({ error: 'Two-factor authentication is not enabled for this user' });
    }

    // Verifica o código 2FA
    await twoFactorService.verifyLoginCode(userId, token);

    // Gera o token JWT
    const jwt = require('jsonwebtoken');
    const jwtToken = jwt.sign(
      { id: user.id, email: user.email, role: user.role },
      process.env.JWT_SECRET,
      { expiresIn: '24h' }
    );

    res.json({
      token: jwtToken,
      user: {
        id: user.id,
        name: user.name,
        email: user.email,
        role: user.role
      }
    });
  } catch (error) {
    if (error.message === 'Invalid verification code' || error.message === 'Invalid two-factor authentication code' || error.message === '2FA is not enabled for this user') {
      return res.status(401).json({ error: error.message });
    }
    console.error('Verify two factor error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

// Setup inicial do 2FA
const setup2FA = async (req, res) => {
  try {
    const userId = req.user.id;
    const user = await require('../models/User').findById(userId);
    
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    const result = await twoFactorService.setup2FA(userId, user.email);
    res.json(result);
  } catch (error) {
    console.error('Setup 2FA error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

// Verifica e ativa o 2FA
const verifyAndEnable2FA = async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ errors: errors.array() });
    }

    const userId = req.user.id;
    const { token } = req.body;

    const result = await twoFactorService.verifyAndEnable2FA(userId, token);
    res.json(result);
  } catch (error) {
    if (error.message === 'Invalid verification code' || error.message === '2FA not set up. Please set up 2FA first.') {
      return res.status(400).json({ error: error.message });
    }
    console.error('Verify and enable 2FA error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

// Desativa o 2FA
const disable2FA = async (req, res) => {
  try {
    const userId = req.user.id;
    const result = await twoFactorService.disable2FA(userId);
    res.json(result);
  } catch (error) {
    console.error('Disable 2FA error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

const getCurrentUser = async (req, res) => {
  try {
    const user = await authService.getCurrentUser(req.user.id);
    res.json({ user });
  } catch (error) {
    if (error.message === 'User not found') {
      return res.status(404).json({ error: error.message });
    }
    console.error('Get current user error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
};

module.exports = {
  login,
  getCurrentUser,
  verifyTwoFactor,
  setup2FA,
  verifyAndEnable2FA,
  disable2FA
};

