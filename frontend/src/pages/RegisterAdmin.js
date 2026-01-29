import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import authService from '../services/authService';
import SignUpLayer from '../components/SignUpLayer';

const RegisterAdmin = () => {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (password !== passwordConfirmation) {
      setError('As senhas não coincidem.');
      return;
    }
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      await authService.registerAdmin({ name, email, password, password_confirmation: passwordConfirmation });
      setSuccess('Administrador cadastrado com sucesso! Redirecionando para o login...');
      setTimeout(() => {
        navigate('/login');
      }, 3000);
    } catch (err) {
      const errorData = err.response?.data;
      if (errorData && errorData.errors) {
        const errorMessages = Object.values(errorData.errors).flat().join(' ');
        setError(errorMessages);
      } else {
        setError(errorData?.message || 'Erro ao cadastrar administrador.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <SignUpLayer
      name={name}
      setName={setName}
      email={email}
      setEmail={setEmail}
      password={password}
      setPassword={setPassword}
      passwordConfirmation={passwordConfirmation}
      setPasswordConfirmation={setPasswordConfirmation}
      handleSubmit={handleSubmit}
      loading={loading}
      error={error}
      success={success}
    />
  );
};

export default RegisterAdmin;
