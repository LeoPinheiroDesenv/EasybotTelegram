import React, { useState, useContext, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { AuthContext } from '../contexts/AuthContext';
import { useGoogleLogin } from '@react-oauth/google';
import './Login.css';

const Login = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [twoFactorToken, setTwoFactorToken] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [requiresTwoFactor, setRequiresTwoFactor] = useState(false);
  const [userId, setUserId] = useState(null);
  const { login, loginWithGoogle, verifyTwoFactor, isAuthenticated } = useContext(AuthContext);
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
      
      if (result && result.requiresTwoFactor) {
        setRequiresTwoFactor(true);
        setUserId(result.userId);
        setTwoFactorToken('');
        setLoading(false);
        return;
      }
      
      if (result && result.token) {
        navigate('/');
        return;
      }
      
      setError('Resposta inválida do servidor');
      setLoading(false);
    } catch (err) {
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

  const handleGoogleLogin = useGoogleLogin({
    onSuccess: async (tokenResponse) => {
      setLoading(true);
      try {
        await loginWithGoogle(tokenResponse.access_token);
        navigate('/');
      } catch (err) {
        setError(err.response?.data?.error || 'Erro ao fazer login com o Google.');
        setLoading(false);
      }
    },
    onError: () => {
      setError('Falha no login com o Google.');
    },
  });

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
            <button
              type="button"
              onClick={() => handleGoogleLogin()}
              className="btn btn-secondary"
              disabled={loading}
              style={{ marginTop: '10px', width: '100%' }}
            >
              Fazer login com o Google
            </button>
            <div className="forgot-password-link">
              <button
                type="button"
                onClick={() => navigate('/forgot-password')}
                className="btn btn-info"
                disabled={loading}
              >
                Esqueci minha senha
              </button>
              <br/><br/>
              <button
                type="button"
                onClick={() => navigate('/register-admin')}
                className="btn btn-info"
                disabled={loading}
              >
                Cadastrar Administrador
              </button>
            </div>
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
