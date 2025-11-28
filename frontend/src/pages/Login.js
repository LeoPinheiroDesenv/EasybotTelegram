import React, { useState, useContext, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { AuthContext } from '../contexts/AuthContext';
import './Login.css';

const Login = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [twoFactorToken, setTwoFactorToken] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [requiresTwoFactor, setRequiresTwoFactor] = useState(false);
  const [userId, setUserId] = useState(null);
  const { login, verifyTwoFactor, isAuthenticated } = useContext(AuthContext);
  const navigate = useNavigate();

  useEffect(() => {
    if (isAuthenticated) {
      navigate('/');
    }
  }, [isAuthenticated, navigate]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const result = await login(email, password, twoFactorToken || null);
      
      // Se requer 2FA, mostra o campo de código
      if (result && result.requiresTwoFactor) {
        setRequiresTwoFactor(true);
        setUserId(result.userId);
        setTwoFactorToken(''); // Limpa o campo para o código 2FA
        setLoading(false);
        return;
      }
      
      // Se chegou aqui, login foi bem-sucedido
      if (result && result.token) {
        navigate('/');
        return;
      }
      
      // Se não tem token nem requiresTwoFactor, algo está errado
      setError('Resposta inválida do servidor');
      setLoading(false);
    } catch (err) {
      // Verifica se é uma resposta de 2FA (não é erro)
      if (err.response?.data?.requiresTwoFactor) {
        setRequiresTwoFactor(true);
        setUserId(err.response.data.userId);
        setTwoFactorToken('');
        setLoading(false);
        return;
      }
      
      setError(err.response?.data?.error || err.response?.data?.message || 'Erro ao fazer login');
      setLoading(false);
    }
  };

  const handleVerifyTwoFactor = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await verifyTwoFactor(userId, twoFactorToken);
      navigate('/');
    } catch (err) {
      setError(err.response?.data?.error || 'Código de verificação inválido');
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
        <h2 className="login-title">Login</h2>
        {!requiresTwoFactor ? (
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
            </div>
            <div className="form-group">
              <label htmlFor="password">Senha:</label>
              <input
                type="password"
                id="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                placeholder="••••••••"
                disabled={loading}
              />
            </div>
            {error && <div className="error">{error}</div>}
            <button type="submit" className="btn btn-primary" disabled={loading}>
              {loading ? 'Entrando...' : 'Entrar'}
            </button>
          </form>
        ) : (
          <form onSubmit={handleVerifyTwoFactor}>
            <div className="form-group">
              <div className="two-factor-info">
                <p className="two-factor-title">
                  🔐 Autenticação de dois fatores ativada
                </p>
                <p className="two-factor-description">
                  Digite o código de 6 dígitos do seu aplicativo autenticador
                </p>
              </div>
              <label htmlFor="twoFactorToken">Código de Verificação (2FA):</label>
              <input
                type="text"
                id="twoFactorToken"
                value={twoFactorToken}
                onChange={(e) => setTwoFactorToken(e.target.value.replace(/\D/g, '').slice(0, 6))}
                required
                placeholder="000000"
                maxLength="6"
                autoFocus
                disabled={loading}
                style={{ 
                  textAlign: 'center', 
                  letterSpacing: '8px', 
                  fontSize: '20px',
                  fontWeight: 'bold'
                }}
              />
              <small>Digite o código de 6 dígitos do seu aplicativo autenticador (Google Authenticator, Authy, etc.)</small>
            </div>
            {error && <div className="error">{error}</div>}
            <button type="submit" className="btn btn-primary" disabled={loading || twoFactorToken.length !== 6}>
              {loading ? 'Verificando...' : 'Verificar e Entrar'}
            </button>
            <button 
              type="button" 
              className="btn btn-secondary" 
              onClick={() => {
                setRequiresTwoFactor(false);
                setTwoFactorToken('');
                setError('');
              }}
              disabled={loading}
              style={{ marginTop: '10px', width: '100%' }}
            >
              Voltar
            </button>
          </form>
        )}
      </div>
    </div>
  );
};

export default Login;

