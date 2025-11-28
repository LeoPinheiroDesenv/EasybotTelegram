import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faUpload, faTrash } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import botService from '../services/botService';
import './WelcomeMessage.css';

const WelcomeMessage = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  let botId = searchParams.get('botId');
  
  // Try to get botId from localStorage if not in URL
  if (!botId) {
    const storedBotId = localStorage.getItem('selectedBotId');
    if (storedBotId) {
      botId = storedBotId;
    }
  }
  
  const [formData, setFormData] = useState({
    initial_message: '',
    top_message: '',
    button_message: '',
    activate_cta: false,
    media_1_url: '',
    media_2_url: '',
    media_3_url: ''
  });
  
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    if (botId) {
      loadBot();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoadingData(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [botId]);

  const loadBot = async () => {
    try {
      setLoadingData(true);
      const bot = await botService.getBotById(botId);
      setFormData({
        initial_message: bot.initial_message || '',
        top_message: bot.top_message || '',
        button_message: bot.button_message || '',
        activate_cta: bot.activate_cta || false,
        media_1_url: bot.media_1_url || '',
        media_2_url: bot.media_2_url || '',
        media_3_url: bot.media_3_url || ''
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

  const handleMediaUpdate = (mediaNumber) => {
    // TODO: Implementar upload de mídia
    setSuccess(`Mídia ${mediaNumber} será atualizada em breve`);
    setTimeout(() => setSuccess(''), 3000);
  };

  const handleMediaDelete = (mediaNumber) => {
    const fieldName = `media_${mediaNumber}_url`;
    setFormData({
      ...formData,
      [fieldName]: ''
    });
    setSuccess(`Mídia ${mediaNumber} removida`);
    setTimeout(() => setSuccess(''), 3000);
  };

  const handleSave = async () => {
    if (!botId) {
      setError('Bot não selecionado');
      return;
    }

    setError('');
    setSuccess('');
    setLoading(true);

    try {
      await botService.updateBot(botId, formData);
      setSuccess('Mensagens de boas-vindas salvas com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar mensagens');
    } finally {
      setLoading(false);
    }
  };

  if (loadingData) {
    return (
      <Layout>
        <div className="welcome-message-page">
          <div className="loading-container">Carregando...</div>
        </div>
      </Layout>
    );
  }

  if (!botId) {
    return (
      <Layout>
        <div className="welcome-message-page">
          <div className="error-container">
            <p>{error}</p>
            <button onClick={() => navigate('/')} className="btn btn-primary">
              Voltar ao Dashboard
            </button>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="welcome-message-page">
        <div className="welcome-message-content">
          <div className="welcome-header">
            <p className="welcome-description">
              Aqui, você tem o poder de personalizar os botões iniciais que os usuários visualizarão ao digitar "start" no seu bot.
            </p>
            <p className="welcome-description">
              Sinta-se à vontade para liberar sua criatividade na edição!
            </p>
          </div>

          {error && <div className="alert alert-error">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          {/* Media Update Buttons */}
          <div className="media-section">
            <div className="media-item">
              <button
                onClick={() => handleMediaUpdate(1)}
                className={`btn-media ${formData.media_1_url ? 'has-media' : ''}`}
              >
                <FontAwesomeIcon icon={faUpload} />
                Atualizar mídia
              </button>
              {formData.media_1_url && (
                <button
                  onClick={() => handleMediaDelete(1)}
                  className="btn-delete-media"
                  title="Remover mídia"
                >
                  <FontAwesomeIcon icon={faTrash} />
                </button>
              )}
            </div>

            <button
              onClick={() => handleMediaUpdate(2)}
              className="btn-media"
            >
              <FontAwesomeIcon icon={faUpload} />
              Atualizar mídia
            </button>

            <button
              onClick={() => handleMediaUpdate(3)}
              className="btn-media"
            >
              <FontAwesomeIcon icon={faUpload} />
              Atualizar mídia
            </button>
          </div>

          {/* Mensagem inicial */}
          <div className="message-section">
            <label className="section-label">Mensagem inicial</label>
            <textarea
              name="initial_message"
              value={formData.initial_message}
              onChange={handleChange}
              className="message-input"
              placeholder="Digite a mensagem inicial que será exibida quando o usuário digitar /start"
              rows="3"
            />
          </div>

          {/* Mensagem superior */}
          <div className="message-section">
            <label className="section-label">Mensagem superior</label>
            <textarea
              name="top_message"
              value={formData.top_message}
              onChange={handleChange}
              className="message-input"
              placeholder="Digite a mensagem que aparecerá no topo"
              rows="4"
            />
          </div>

          {/* Mensagem do botão */}
          <div className="message-section">
            <label className="section-label">Mensagem do botão</label>
            <input
              type="text"
              name="button_message"
              value={formData.button_message}
              onChange={handleChange}
              className="message-input"
              placeholder="Digite o texto do botão"
            />
          </div>

          {/* Footer with CTA toggle and Save button */}
          <div className="welcome-footer">
            <div className="cta-toggle">
              <span className="toggle-label">Ativar CTA</span>
              <button
                type="button"
                className={`toggle-switch ${formData.activate_cta ? 'active' : ''}`}
                onClick={() => handleToggle('activate_cta')}
              >
                <span className="toggle-slider"></span>
              </button>
            </div>
            <button
              onClick={handleSave}
              className="btn btn-save"
              disabled={loading}
            >
              {loading ? 'Salvando...' : 'Salvar'}
            </button>
          </div>

          {/* Floating Icon */}
          <div className="floating-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"></path>
              <path d="M12 6v6l4 2"></path>
            </svg>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default WelcomeMessage;

