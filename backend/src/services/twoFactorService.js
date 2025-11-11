// Serviço para autenticação de dois fatores (2FA)
const speakeasy = require('speakeasy');
const QRCode = require('qrcode');
const User = require('../models/User');

const twoFactorService = {
  // Gera um secret para 2FA
  generateSecret(userEmail) {
    const secret = speakeasy.generateSecret({
      name: `Easy Bot (${userEmail})`,
      issuer: 'Easy Bot Telegram'
    });
    return secret;
  },

  // Gera QR code em formato data URL
  async generateQRCode(otpauthUrl) {
    try {
      const qrCodeDataUrl = await QRCode.toDataURL(otpauthUrl);
      return qrCodeDataUrl;
    } catch (error) {
      throw new Error('Failed to generate QR code');
    }
  },

  // Verifica o código TOTP
  verifyToken(secret, token) {
    return speakeasy.totp.verify({
      secret: secret,
      encoding: 'base32',
      token: token,
      window: 2 // Permite tokens de 2 períodos antes e depois (60 segundos)
    });
  },

  // Setup inicial do 2FA - gera secret e QR code
  async setup2FA(userId, userEmail) {
    const secret = this.generateSecret(userEmail);
    
    // Salva o secret temporariamente (ainda não está ativado)
    await User.update(userId, {
      two_factor_secret: secret.base32
    });

    // Gera QR code
    const qrCodeUrl = await this.generateQRCode(secret.otpauth_url);

    return {
      secret: secret.base32,
      qrCode: qrCodeUrl,
      manualEntryKey: secret.base32
    };
  },

  // Verifica o código durante o setup e ativa o 2FA
  async verifyAndEnable2FA(userId, token) {
    const user = await User.findById(userId);
    
    if (!user || !user.two_factor_secret) {
      throw new Error('2FA not set up. Please set up 2FA first.');
    }

    const isValid = this.verifyToken(user.two_factor_secret, token);
    
    if (!isValid) {
      throw new Error('Invalid verification code');
    }

    // Ativa o 2FA
    await User.update(userId, {
      two_factor_enabled: true
    });

    return { success: true };
  },

  // Desativa o 2FA
  async disable2FA(userId) {
    await User.update(userId, {
      two_factor_secret: null,
      two_factor_enabled: false
    });

    return { success: true };
  },

  // Verifica o código durante o login
  async verifyLoginCode(userId, token) {
    const user = await User.findById(userId);
    
    if (!user || !user.two_factor_enabled || !user.two_factor_secret) {
      throw new Error('2FA is not enabled for this user');
    }

    const isValid = this.verifyToken(user.two_factor_secret, token);
    
    if (!isValid) {
      throw new Error('Invalid verification code');
    }

    return { success: true };
  }
};

module.exports = twoFactorService;

