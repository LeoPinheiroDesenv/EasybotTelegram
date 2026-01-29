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

  return (
    <div className="login-container">
      <div className="login-card">
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
            <div className="forgot-password-link">
              <button
                type="button"
                onClick={() => navigate('/forgot-password')}
                className="btn-link"
                disabled={loading}
              >
                Esqueci minha senha
              </button>
              <button
                type="button"
                onClick={() => navigate('/register-admin')}
                className="btn-link"
                disabled={loading}
              >
                Cadastrar Administrador
              </button>
            </div>
          </form>
        ) : (
          <form onSubmit={handleVerifyTwoFactor}>
            <div className="form-group">
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
              />
            </div>
            {error && <div className="error">{error}</div>}
            <button type="submit" className="btn btn-primary" disabled={loading || twoFactorToken.length !== 6}>
              {loading ? 'Verificando...' : 'Verificar e Entrar'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
};

export default Login;
