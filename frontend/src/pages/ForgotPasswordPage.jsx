import React, { useState } from 'react';
import authService from '../services/authService';
import ForgotPasswordLayer from '../components/ForgotPasswordLayer';

const ForgotPasswordPage = () => {
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      const response = await authService.requestPasswordReset(email);
      setSuccess(response.message || 'Se um email correspondente for encontrado, um link de recuperação será enviado.');
    } catch (err) {
      setError(err.response?.data?.error || 'Ocorreu um erro. Tente novamente.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <ForgotPasswordLayer
      email={email}
      setEmail={setEmail}
      handleSubmit={handleSubmit}
      loading={loading}
      error={error}
      success={success}
    />
  );
};

export default ForgotPasswordPage;
