import React, { useState, useContext, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { AuthContext } from '../contexts/AuthContext';
import { useGoogleLogin } from '@react-oauth/google';
import SignInLayer from '../components/SignInLayer';

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
    <SignInLayer
      email={email}
      setEmail={setEmail}
      password={password}
      setPassword={setPassword}
      twoFactorToken={twoFactorToken}
      setTwoFactorToken={setTwoFactorToken}
      handleSubmit={handleSubmit}
      handleVerifyTwoFactor={handleVerifyTwoFactor}
      handleGoogleLogin={handleGoogleLogin}
      loading={loading}
      error={error}
      requiresTwoFactor={requiresTwoFactor}
      setRequiresTwoFactor={setRequiresTwoFactor}
      setError={setError}
    />
  );
};

export default Login;
