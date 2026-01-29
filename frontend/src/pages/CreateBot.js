import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Layout from '../components/Layout';
import botService from '../services/botService';
import './CreateBot.css';

const CreateBot = () => {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    name: '',
    token: '',
    telegram_group_id: ''
  });
  const [loading, setLoading] = useState(false);
  const [validating, setValidating] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({
      ...formData,
      [name]: value
    });
    setError('');
  };

  const handleValidate = async () => {
    if (!formData.token) {
      setError('Por favor, preencha o token do bot');
      return;
    }

    setValidating(true);
    setError('');
    setSuccess('');

    try {
      const response = await botService.validateBot(formData.token);
      if (response.valid) {
        setSuccess('Token válido!');
        setTimeout(() => setSuccess(''), 3000);
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao validar token');
    } finally {
      setValidating(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      await botService.createBot(formData);
      setSuccess('Bot criado com sucesso!');
      setTimeout(() => {
        navigate('/');
      }, 2000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao criar bot');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Layout>
      <div className="create-bot-page">
        <div className="create-bot-content">
          

          <div className="create-bot-grid">
            <div className="create-bot-form-section">
              <h2 className="section-title">Criação do bot</h2>
              
              <form onSubmit={handleSubmit}>
                <div className="form-group">
                  <label htmlFor="name">Nome do bot</label>
                  <input
                    type="text"
                    id="name"
                    name="name"
                    value={formData.name}
                    onChange={handleChange}
                    required
                    placeholder="Digite o nome do bot"
                  />
                </div>

                <div className="form-group">
                  <label htmlFor="token">Token do bot</label>
                  <input
                    type="text"
                    id="token"
                    name="token"
                    value={formData.token}
                    onChange={handleChange}
                    required
                    placeholder="Digite o token do bot"
                  />
                </div>

                <div className="form-group">
                  <label htmlFor="telegram_group_id">Id do grupo no telegram</label>
                  <input
                    type="text"
                    id="telegram_group_id"
                    name="telegram_group_id"
                    value={formData.telegram_group_id}
                    onChange={handleChange}
                    placeholder="Digite o ID do grupo (opcional)"
                  />
                </div>

                {error && <div className="alert alert-error">{error}</div>}
                {success && <div className="alert alert-success">{success}</div>}

                <div className="form-actions">
                  <button
                    type="button"
                    onClick={handleValidate}
                    className="btn btn-validate"
                    disabled={validating || !formData.token}
                  >
                    {validating ? 'Validando...' : 'Validar >'}
                  </button>
                  <button
                    type="submit"
                    className="btn btn-create"
                    disabled={loading}
                  >
                    {loading ? 'Criando...' : 'Criar >'}
                  </button>
                </div>
              </form>
            </div>

            <div className="create-bot-tutorial-section">
              <h2 className="section-title">Tutorial e orientações</h2>
              
              <div className="tutorial-content">
                <div className="tutorial-step">
                  <p>
                    1. Acesse o link abaixo para criar um bot no Telegram utilizando o BotFather e copie o token gerado.
                  </p>
                  <a
                    href="https://t.me/botfather"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="tutorial-link"
                  >
                    https://t.me/botfather
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default CreateBot;

