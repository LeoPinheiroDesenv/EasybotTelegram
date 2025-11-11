// Lógica de Negócios (Regras da Aplicação - o "core")
const bcrypt = require('bcryptjs');
const User = require('../models/User');

const userService = {
  async getAllUsers() {
    return await User.findAll();
  },

  async getUserById(id) {
    const user = await User.findById(id);
    if (!user) {
      throw new Error('User not found');
    }
    return user;
  },

  async createUser(userData) {
    const { name, email, password, role = 'user', active = true } = userData;

    // Check if email already exists
    if (await User.emailExists(email)) {
      throw new Error('Email already registered');
    }

    // Hash password
    const hashedPassword = await bcrypt.hash(password, 10);

    return await User.create({
      name,
      email,
      password: hashedPassword,
      role,
      active
    });
  },

  async updateUser(id, userData) {
    // Check if user exists
    const existingUser = await User.findById(id);
    if (!existingUser) {
      throw new Error('User not found');
    }

    // Check if email is already taken by another user
    if (userData.email && await User.emailExists(userData.email, id)) {
      throw new Error('Email already registered');
    }

    // Hash password if provided
    if (userData.password) {
      userData.password = await bcrypt.hash(userData.password, 10);
    }

    return await User.update(id, userData);
  },

  async deleteUser(id) {
    const user = await User.findById(id);
    if (!user) {
      throw new Error('User not found');
    }
    return await User.delete(id);
  }
};

module.exports = userService;

