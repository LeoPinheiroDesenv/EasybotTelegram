import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import authService from '../services/authService';
import './ForgotPassword.css';

const ForgotPassword = () => {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await authService.requestPasswordReset(email);
      setSuccess(true);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao solicitar recuperação de senha');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-container">
      <div className="login-card">
        <div className="login-logo">
          <div className="logo-circles">
            <span className="circle circle-1"></span>
            <span className="circle circle-2"></span>
            <span className="circle circle-3"></span>
          </div>
          <div className="logo-text">
            <div className="logo-title">Easy</div>
            <div className="logo-subtitle">Sistema de Gerenciamento</div>
          </div>
        </div>
        <h2 className="login-title">Recuperar Senha</h2>
        
        {success ? (
          <div className="success-message">
            <p>Se o email existir, você receberá um link de recuperação de senha.</p>
            <p className="success-note">Verifique sua caixa de entrada e spam.</p>
            <button
              onClick={() => navigate('/login')}
              className="btn btn-primary"
            >
              Voltar para o login
            </button>
          </div>
        ) : (
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label htmlFor="email">Email:</label>
              <input
                type="email"
                id="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                placeholder="seu@email.com"
                disabled={loading}
              />
              <small>Digite seu email para receber o link de recuperação de senha</small>
            </div>
            {error && <div className="error">{error}</div>}
            <button type="submit" className="btn btn-primary" disabled={loading}>
              {loading ? 'Enviando...' : 'Enviar link de recuperação'}
            </button>
            <div className="forgot-password-link">
              <button
                type="button"
                onClick={() => navigate('/login')}
                className="btn-link"
                disabled={loading}
              >
                Voltar para o login
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
};

export default ForgotPassword;

