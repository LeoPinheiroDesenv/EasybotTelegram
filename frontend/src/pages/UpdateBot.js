import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faChevronRight, faRobot, faPlus } from '@fortawesome/free-solid-svg-icons';
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
  const [validating, setValidating] = useState(false);
  const [validationResult, setValidationResult] = useState(null);

  useEffect(() => {
    loadBots();
    loadBot();
    // eslint-disable-next-line react-hooks/exhaustive-deps
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

  const handleValidate = async () => {
    if (!formData.token) {
      setError('Por favor, preencha o token do bot antes de validar.');
      return;
    }

    setValidating(true);
    setError('');
    setSuccess('');
    setValidationResult(null);

    try {
      const result = await botService.validateTokenAndGroup(
        formData.token,
        formData.telegram_group_id || null
      );

      setValidationResult(result);

      if (result.valid) {
        setSuccess('Token e grupo validados com sucesso!');
        setTimeout(() => setSuccess(''), 5000);
      } else {
        const errors = result.errors || [];
        if (result.token_valid === false) {
          setError('Token inválido. Verifique se o token está correto.');
        } else if (result.group_valid === false && formData.telegram_group_id) {
          setError('Grupo inválido ou bot não tem acesso ao grupo. ' + (errors.join(' ') || ''));
        } else {
          setError(errors.join(' ') || 'Validação falhou.');
        }
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao validar token e grupo');
      setValidationResult({
        valid: false,
        error: err.response?.data?.error || 'Erro ao validar'
      });
    } finally {
      setValidating(false);
    }
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
                <small style={{ color: '#666', marginTop: '5px', display: 'block' }}>
                  ID numérico do grupo (ex: -1001234567890). O bot deve ser membro do grupo.
                </small>
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
                <small style={{ color: '#666', marginTop: '5px', display: 'block' }}>
                  Token do bot obtido em @BotFather no Telegram
                </small>
              </div>
            </div>

            {/* Botão de validação */}
            <div style={{ marginTop: '20px', marginBottom: '20px' }}>
              <button
                onClick={handleValidate}
                className="btn btn-primary"
                disabled={validating || !formData.token}
                style={{ 
                  display: 'flex', 
                  alignItems: 'center', 
                  gap: '8px',
                  backgroundColor: '#9333ea',
                  color: 'white',
                  border: 'none',
                  padding: '10px 20px',
                  borderRadius: '8px',
                  cursor: validating || !formData.token ? 'not-allowed' : 'pointer',
                  opacity: validating || !formData.token ? 0.6 : 1
                }}
              >
                {validating ? (
                  <>
                    <span className="spinner" style={{ 
                      width: '16px', 
                      height: '16px', 
                      border: '2px solid rgba(255,255,255,0.3)',
                      borderTopColor: 'white',
                      borderRadius: '50%',
                      animation: 'spin 0.8s linear infinite',
                      display: 'inline-block'
                    }}></span>
                    Validando...
                  </>
                ) : (
                  <>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Validar Token e Grupo
                  </>
                )}
              </button>
            </div>

            {/* Resultado da validação */}
            {validationResult && (
              <div style={{ 
                marginTop: '15px', 
                padding: '15px', 
                borderRadius: '8px',
                backgroundColor: validationResult.valid ? '#d1fae5' : '#fee2e2',
                border: `1px solid ${validationResult.valid ? '#10b981' : '#ef4444'}`
              }}>
                <h4 style={{ 
                  margin: '0 0 10px 0', 
                  color: validationResult.valid ? '#065f46' : '#991b1b',
                  fontSize: '14px',
                  fontWeight: '600'
                }}>
                  Resultado da Validação:
                </h4>
                
                <div style={{ fontSize: '13px', color: validationResult.valid ? '#065f46' : '#991b1b' }}>
                  <div style={{ marginBottom: '5px' }}>
                    <strong>Token:</strong> {validationResult.token_valid ? '✅ Válido' : '❌ Inválido'}
                  </div>
                  
                  {formData.telegram_group_id && (
                    <div style={{ marginBottom: '5px' }}>
                      <strong>Grupo:</strong> {validationResult.group_valid ? '✅ Válido' : '❌ Inválido'}
                    </div>
                  )}

                  {validationResult.bot_info && (
                    <div style={{ marginTop: '10px', paddingTop: '10px', borderTop: '1px solid rgba(0,0,0,0.1)' }}>
                      <strong>Informações do Bot:</strong>
                      <ul style={{ margin: '5px 0', paddingLeft: '20px' }}>
                        <li>Nome: {validationResult.bot_info.first_name || 'N/A'}</li>
                        <li>Username: @{validationResult.bot_info.username || 'N/A'}</li>
                        <li>ID: {validationResult.bot_info.id}</li>
                      </ul>
                    </div>
                  )}

                  {validationResult.group_info && validationResult.group_valid && (
                    <div style={{ marginTop: '10px', paddingTop: '10px', borderTop: '1px solid rgba(0,0,0,0.1)' }}>
                      <strong>Informações do Grupo:</strong>
                      <ul style={{ margin: '5px 0', paddingLeft: '20px' }}>
                        <li>Título: {validationResult.group_info.title || 'N/A'}</li>
                        <li>Tipo: {validationResult.group_info.type || 'N/A'}</li>
                        {validationResult.group_info.member_count && (
                          <li>Membros: {validationResult.group_info.member_count}</li>
                        )}
                        {validationResult.group_info.permissions && (
                          <li>
                            Permissões: 
                            {validationResult.group_info.permissions.can_restrict_members ? ' ✅ Pode restringir membros' : ' ❌ Não pode restringir'}
                            {validationResult.group_info.permissions.can_invite_users ? ' ✅ Pode convidar' : ' ❌ Não pode convidar'}
                          </li>
                        )}
                      </ul>
                    </div>
                  )}

                  {validationResult.errors && validationResult.errors.length > 0 && (
                    <div style={{ marginTop: '10px', paddingTop: '10px', borderTop: '1px solid rgba(0,0,0,0.1)' }}>
                      <strong>Erros:</strong>
                      <ul style={{ margin: '5px 0', paddingLeft: '20px' }}>
                        {validationResult.errors.map((err, idx) => (
                          <li key={idx}>{err}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              </div>
            )}
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
                <FontAwesomeIcon icon={faChevronRight} />
              </a>
            </div>

            <div className="bots-list">
              {bots.map((bot) => (
                <div key={bot.id} className="bot-list-item">
                  <div className="bot-icon">
                    <FontAwesomeIcon icon={faRobot} style={{ color: '#9333ea' }} />
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
              <FontAwesomeIcon icon={faPlus} />
              Criar novo bot
            </button>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default UpdateBot;

