import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import Layout from '../components/Layout';
import botService from '../services/botService';
import './UpdateBot.css';

const UpdateBot = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [bots, setBots] = useState([]);
  const [formData, setFormData] = useState({
    name: '',
    token: '',
    telegram_group_id: '',
    request_email: false,
    request_phone: false,
    request_language: false,
    payment_method: 'credit_card',
    activated: false
  });
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    loadBots();
    loadBot();
  }, [id]);

  const loadBots = async () => {
    try {
      const data = await botService.getAllBots();
      setBots(data);
    } catch (err) {
      console.error('Error loading bots:', err);
    }
  };

  const loadBot = async () => {
    try {
      setLoadingData(true);
      const bot = await botService.getBotById(id);
      setFormData({
        name: bot.name || '',
        token: bot.token || '',
        telegram_group_id: bot.telegram_group_id || '',
        request_email: bot.request_email || false,
        request_phone: bot.request_phone || false,
        request_language: bot.request_language || false,
        payment_method: bot.payment_method || 'credit_card',
        activated: bot.activated || false
      });
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar bot');
    } finally {
      setLoadingData(false);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData({
      ...formData,
      [name]: type === 'checkbox' ? checked : value
    });
    setError('');
  };

  const handleToggle = (field) => {
    setFormData({
      ...formData,
      [field]: !formData[field]
    });
  };

  const handlePaymentMethod = (method) => {
    setFormData({
      ...formData,
      payment_method: method
    });
  };

  const handleUpdateLink = () => {
    // Implementar lógica de atualização de link
    setSuccess('Link atualizado com sucesso!');
    setTimeout(() => setSuccess(''), 3000);
  };

  const handleSave = async () => {
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      await botService.updateBot(id, formData);
      setSuccess('Alterações salvas com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar alterações');
    } finally {
      setLoading(false);
    }
  };

  const handleActivate = async () => {
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      await botService.updateBot(id, { activated: !formData.activated });
      setFormData({ ...formData, activated: !formData.activated });
      setSuccess(formData.activated ? 'Bot desativado com sucesso!' : 'Bot ativado com sucesso!');
      await loadBots(); // Reload bots list
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao ativar/desativar bot');
    } finally {
      setLoading(false);
    }
  };

  const handleSelectBot = (botId) => {
    localStorage.setItem('selectedBotId', botId.toString());
    navigate(`/bot/update/${botId}`);
    window.location.reload();
  };

  const handleCreateBot = () => {
    navigate('/bot/create');
  };

  if (loadingData) {
    return (
      <Layout>
        <div className="update-bot-page">
          <div className="loading-container">Carregando...</div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="update-bot-page">
        <div className="update-bot-layout">
          <div className="update-bot-main">
          {error && <div className="alert alert-error">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          {/* Informações gerais */}
          <div className="update-section">
            <div className="section-header">
              <h2 className="section-title">Informações gerais</h2>
              <p className="section-description">
                Altere as informações do seu bot por esse campo.
              </p>
              <button onClick={handleUpdateLink} className="btn btn-update-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <polyline points="23 4 23 10 17 10"></polyline>
                  <polyline points="1 20 1 14 7 14"></polyline>
                  <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
                Atualizar link
              </button>
            </div>

            <div className="form-grid">
              <div className="form-field-card">
                <label>Nome do bot</label>
                <input
                  type="text"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  placeholder="Nome do bot"
                />
              </div>

              <div className="form-field-card">
                <label>ID do grupo</label>
                <input
                  type="text"
                  name="telegram_group_id"
                  value={formData.telegram_group_id}
                  onChange={handleChange}
                  placeholder="ID do grupo"
                />
              </div>

              <div className="form-field-card">
                <label>Token</label>
                <input
                  type="text"
                  name="token"
                  value={formData.token}
                  onChange={handleChange}
                  placeholder="Token do bot"
                />
              </div>
            </div>
          </div>

          {/* Configurações de privacidade */}
          <div className="update-section">
            <h2 className="section-title">Configurações de privacidade</h2>
            <div className="toggle-list">
              <div className="toggle-item">
                <div className="toggle-label">
                  <span>Solicitar e-mail</span>
                </div>
                <button
                  type="button"
                  className={`toggle-switch ${formData.request_email ? 'active' : ''}`}
                  onClick={() => handleToggle('request_email')}
                >
                  <span className="toggle-slider"></span>
                </button>
              </div>

              <div className="toggle-item">
                <div className="toggle-label">
                  <span>Solicitar telefone</span>
                </div>
                <button
                  type="button"
                  className={`toggle-switch ${formData.request_phone ? 'active' : ''}`}
                  onClick={() => handleToggle('request_phone')}
                >
                  <span className="toggle-slider"></span>
                </button>
              </div>

              <div className="toggle-item">
                <div className="toggle-label">
                  <span>Solicitar idioma</span>
                </div>
                <button
                  type="button"
                  className={`toggle-switch ${formData.request_language ? 'active' : ''}`}
                  onClick={() => handleToggle('request_language')}
                >
                  <span className="toggle-slider"></span>
                </button>
              </div>
            </div>
          </div>

          {/* Configurações de pagamento */}
          <div className="update-section">
            <h2 className="section-title">Configurações de pagamento</h2>
            <div className="payment-options">
              <button
                type="button"
                className={`payment-option ${formData.payment_method === 'credit_card' ? 'active' : ''}`}
                onClick={() => handlePaymentMethod('credit_card')}
              >
                <div className="payment-radio">
                  {formData.payment_method === 'credit_card' && <div className="radio-dot"></div>}
                </div>
                <span>Cartão de crédito</span>
              </button>

              <button
                type="button"
                className={`payment-option ${formData.payment_method === 'pix' ? 'active' : ''}`}
                onClick={() => handlePaymentMethod('pix')}
              >
                <div className="payment-radio">
                  {formData.payment_method === 'pix' && <div className="radio-dot"></div>}
                </div>
                <span>Pix direto</span>
              </button>
            </div>
          </div>

          {/* Action buttons */}
          <div className="action-buttons">
            <button
              onClick={handleSave}
              className="btn btn-save"
              disabled={loading}
            >
              {loading ? 'Salvando...' : 'Salvar alterações'}
            </button>
            <button
              onClick={handleActivate}
              className={`btn btn-activate ${formData.activated ? 'activated' : ''}`}
              disabled={loading}
            >
              {formData.activated ? 'Desativar bot' : 'Ativar bot'}
            </button>
          </div>
          </div>

          {/* Right Panel - Meus bots ativos */}
          <div className="update-bot-sidebar">
            <div className="sidebar-header">
              <h2 className="sidebar-title">Meus bots ativos</h2>
              <a href="/configuracoes" className="sidebar-link">
                Configurações
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
              </a>
            </div>

            <div className="bots-list">
              {bots.map((bot) => (
                <div key={bot.id} className="bot-list-item">
                  <div className="bot-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9333ea" strokeWidth="2">
                      <rect x="6" y="4" width="12" height="16" rx="2"></rect>
                      <circle cx="10" cy="10" r="2"></circle>
                      <circle cx="14" cy="10" r="2"></circle>
                      <path d="M9 16h6"></path>
                    </svg>
                  </div>
                  <div className="bot-info">
                    <span className="bot-name">{bot.name}</span>
                  </div>
                  <button
                    className={`btn-select ${parseInt(id) === bot.id ? 'deselect' : ''}`}
                    onClick={() => handleSelectBot(bot.id)}
                  >
                    {parseInt(id) === bot.id ? 'Deselecionar' : 'Selecionar'}
                  </button>
                </div>
              ))}
            </div>

            <button onClick={handleCreateBot} className="btn-create-new">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
              Criar novo bot
            </button>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default UpdateBot;

