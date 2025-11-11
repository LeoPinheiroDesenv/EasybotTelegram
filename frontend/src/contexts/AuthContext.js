import React, { createContext, useState, useEffect } from 'react';
import authService from '../services/authService';

export const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (token) {
      authService.getCurrentUser()
        .then((userData) => {
          setUser(userData);
        })
        .catch(() => {
          localStorage.removeItem('token');
        })
        .finally(() => {
          setLoading(false);
        });
    } else {
      setLoading(false);
    }
  }, []);

  const login = async (email, password, twoFactorToken = null) => {
    const response = await authService.login(email, password, twoFactorToken);
    
    // Se requer 2FA, retorna o objeto com requiresTwoFactor
    if (response.requiresTwoFactor) {
      return response;
    }
    
    localStorage.setItem('token', response.token);
    setUser(response.user);
    return response;
  };

  const verifyTwoFactor = async (userId, token) => {
    const response = await authService.verifyTwoFactor(userId, token);
    localStorage.setItem('token', response.token);
    setUser(response.user);
    return response;
  };

  const logout = () => {
    localStorage.removeItem('token');
    setUser(null);
  };

  const value = {
    user,
    login,
    verifyTwoFactor,
    logout,
    loading,
    isAuthenticated: !!user,
    isAdmin: user?.role === 'admin'
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

