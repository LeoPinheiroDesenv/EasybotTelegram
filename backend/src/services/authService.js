// Lógica de Negócios (Regras da Aplicação - o "core")
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const User = require('../models/User');
const twoFactorService = require('./twoFactorService');

const authService = {
  async login(email, password, twoFactorToken = null) {
    // Find user by email
    const user = await User.findByEmail(email);

    if (!user) {
      throw new Error('Invalid credentials');
    }

    if (!user.active) {
      throw new Error('Account is deactivated');
    }

    // Verify password
    const isPasswordValid = await bcrypt.compare(password, user.password);

    if (!isPasswordValid) {
      throw new Error('Invalid credentials');
    }

    // Se 2FA está ativado, verifica o token
    if (user.two_factor_enabled) {
      if (!twoFactorToken) {
        // Retorna indicando que precisa do código 2FA
        return {
          requiresTwoFactor: true,
          userId: user.id,
          message: 'Two-factor authentication required'
        };
      }

      // Verifica o código 2FA
      try {
        await twoFactorService.verifyLoginCode(user.id, twoFactorToken);
      } catch (error) {
        throw new Error('Invalid two-factor authentication code');
      }
    }

    // Generate JWT token
    const token = jwt.sign(
      { id: user.id, email: user.email, role: user.role },
      process.env.JWT_SECRET,
      { expiresIn: '24h' }
    );

    return {
      token,
      user: {
        id: user.id,
        name: user.name,
        email: user.email,
        role: user.role
      }
    };
  },

  async getCurrentUser(userId) {
    const user = await User.findById(userId);
    if (!user) {
      throw new Error('User not found');
    }
    return user;
  }
};

module.exports = authService;

