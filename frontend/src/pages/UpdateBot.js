import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faInfoCircle, faTimes } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import botService from '../services/botService';
import { useManageBot } from '../contexts/ManageBotContext';
import './UpdateBot.css';

const UpdateBot = () => {
  const { id, botId } = useParams();
  // eslint-disable-next-line no-unused-vars
  const navigate = useNavigate();
  const isInManageBot = useManageBot();
  // Usa botId se disponível (rota do ManageBot), senão usa id (rota antiga)
  const actualBotId = botId || id;
  const [formData, setFormData] = useState({
    name: '',
    token: '',
    telegram_group_id: '',
    request_email: false,
    request_phone: false,
    request_language: false,
    payment_method: ['credit_card'],
    activated: false
  });
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [validating, setValidating] = useState(false);
  const [validationResult, setValidationResult] = useState(null);
  const [initializing, setInitializing] = useState(false);
  const [settingWebhook, setSettingWebhook] = useState(false);
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [botStatus, setBotStatus] = useState(null);
  const [loadingStatus, setLoadingStatus] = useState(false);
  const [updatingLink, setUpdatingLink] = useState(false);
  const [inviteLink, setInviteLink] = useState(null);
  const [showLinkModal, setShowLinkModal] = useState(false);

  useEffect(() => {
    loadBot();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [actualBotId]);

  const loadBot = async () => {
    try {
      setLoadingData(true);
      const bot = await botService.getBotById(actualBotId);
      setFormData({
        name: bot.name || '',
        token: bot.token || '',
        telegram_group_id: bot.telegram_group_id || '',
        request_email: bot.request_email || false,
        request_phone: bot.request_phone || false,
        request_language: bot.request_language || false,
        payment_method: Array.isArray(bot.payment_method) ? bot.payment_method : (bot.payment_method ? [bot.payment_method] : ['credit_card']),
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
    setFormData(prev => {
      const currentMethods = prev.payment_method || [];
      const isSelected = currentMethods.includes(method);
      
      let newMethods;
      if (isSelected) {
        // Remove o método se já estiver selecionado
        newMethods = currentMethods.filter(m => m !== method);
        // Garante que pelo menos um método esteja selecionado
        if (newMethods.length === 0) {
          newMethods = [method]; // Mantém o método se for o único
        }
      } else {
        // Adiciona o método se não estiver selecionado
        newMethods = [...currentMethods, method];
      }
      
      return {
        ...prev,
        payment_method: newMethods
      };
    });
  };

  const handleUpdateLink = async () => {
    if (!actualBotId) {
      setError('ID do bot não encontrado');
      return;
    }

    if (!formData.telegram_group_id) {
      setError('O bot não tem um grupo do Telegram configurado. Configure o ID do grupo primeiro.');
      return;
    }

    setError('');
    setSuccess('');
    setUpdatingLink(true);
    setInviteLink(null);

    try {
      const result = await botService.updateInviteLink(actualBotId);
      
      if (result.success && result.invite_link) {
        setInviteLink(result.invite_link);
        setShowLinkModal(true);
        setSuccess('Link de convite obtido com sucesso!');
        setTimeout(() => setSuccess(''), 5000);
      } else {
        setError(result.error || 'Erro ao obter link de convite');
      }
    } catch (err) {
      const errorMessage = err.response?.data?.error || 
                          err.response?.data?.message || 
                          'Erro ao atualizar link de convite.';
      
      const details = err.response?.data?.details;
      let fullErrorMessage = errorMessage;
      
      if (details) {
        if (details.status) {
          fullErrorMessage += ` Status do bot: ${details.status}`;
        }
        if (details.is_admin === false) {
          fullErrorMessage += ' O bot não é administrador do grupo/canal.';
        } else if (details.is_admin === true && !details.can_invite_users) {
          fullErrorMessage += ' O bot é administrador mas não tem permissão para convidar usuários.';
        }
      }
      
      setError(fullErrorMessage);
    } finally {
      setUpdatingLink(false);
    }
  };

  const handleCopyLink = async () => {
    if (inviteLink) {
      try {
        await navigator.clipboard.writeText(inviteLink);
        setSuccess('Link copiado para a área de transferência!');
        setTimeout(() => setSuccess(''), 3000);
      } catch (err) {
        setError('Erro ao copiar link para a área de transferência');
      }
    }
  };

  const closeLinkModal = () => {
    setShowLinkModal(false);
    setInviteLink(null);
  };

  const handleGetBotStatus = async () => {
    if (!actualBotId) {
      setError('ID do bot não encontrado');
      return;
    }

    setLoadingStatus(true);
    setError('');
    setShowStatusModal(true);

    try {
      const status = await botService.getBotStatus(actualBotId);
      setBotStatus(status);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao obter status do bot');
      setBotStatus({
        error: err.response?.data?.error || 'Erro ao obter status do bot'
      });
    } finally {
      setLoadingStatus(false);
    }
  };

  const closeStatusModal = () => {
    setShowStatusModal(false);
    setBotStatus(null);
  };

  const handleInitialize = async () => {
    if (!actualBotId) {
      setError('ID do bot não encontrado');
      return;
    }

    setError('');
    setSuccess('');
    setInitializing(true);

    try {
      const result = await botService.initializeBot(actualBotId);
      setSuccess(result.message || 'Bot inicializado com sucesso!');
      
      // Recarrega os dados do bot para atualizar o status
      await loadBot();
      
      setTimeout(() => setSuccess(''), 5000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao inicializar o bot');
    } finally {
      setInitializing(false);
    }
  };

  const handleSetWebhook = async () => {
    if (!actualBotId) {
      setError('ID do bot não encontrado');
      return;
    }

    setError('');
    setSuccess('');
    setSettingWebhook(true);

    try {
      const result = await botService.setWebhook(actualBotId);
      setSuccess(result.message || 'Webhook configurado com sucesso!');
      
      setTimeout(() => setSuccess(''), 5000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao configurar webhook');
    } finally {
      setSettingWebhook(false);
    }
  };

  const handleSave = async () => {
    setError('');
    setSuccess('');
    setLoading(true);

    // Valida que pelo menos um método de pagamento está selecionado
    if (!formData.payment_method || formData.payment_method.length === 0) {
      setError('Selecione pelo menos um método de pagamento.');
      setLoading(false);
      return;
    }

    try {
      await botService.updateBot(actualBotId, formData);
      setSuccess('Alterações salvas com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.errors?.payment_method?.[0] || 'Erro ao salvar alterações');
    } finally {
      setLoading(false);
    }
  };

  const handleActivate = async () => {
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      await botService.updateBot(actualBotId, { activated: !formData.activated });
      setFormData({ ...formData, activated: !formData.activated });
      setSuccess(formData.activated ? 'Bot desativado com sucesso!' : 'Bot ativado com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao ativar/desativar bot');
    } finally {
      setLoading(false);
    }
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
    const loadingContent = (
      <div className="update-bot-page">
        <div className="loading-container">Carregando...</div>
      </div>
    );
    return isInManageBot ? loadingContent : <Layout>{loadingContent}</Layout>;
  }

  const content = (
    <>
      <div className="update-bot-page">
        <div className="update-bot-content">
          <div className="update-bot-layout">
          <div className="update-bot-main">
          {error && <div className="alert alert-error">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          {/* Informações gerais */}
          <div className="update-section">
            <div className="section-header">
              <h2 className="section-title">Informações gerais</h2>
              
              <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                
              <button
                onClick={handleSetWebhook}
                className="btn btn-primary"
                disabled={loading || settingWebhook}
                style={{ flex: 1, minWidth: '250px', backgroundColor: '#3b82f6', color: 'white'}}
              >
                {settingWebhook ? 'Configurando webhook...' : '🔗 Verificar e setar o webhook'}
              </button>
                
                <button 
                  onClick={handleInitialize}
                  className="btn btn-primary"
                  disabled={loading || initializing}
                  style={{ flex: 1, minWidth: '200px' }}
                >
                  {initializing ? 'Verificando e iniciando...' : '🔍 Verificar e iniciar o bot'}
                </button>
                
                <button 
                  onClick={handleUpdateLink} 
                  className="btn btn-primary-600 radius-8 px-20 py-11 d-flex align-items-center gap-2"
                  disabled={updatingLink || !formData.telegram_group_id}
                  style={{ flex: 1, minWidth: '200px' }}
                >
                  {updatingLink ? (
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
                      Atualizando...
                    </>
                  ) : (
                    <>
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <polyline points="1 20 1 14 7 14"></polyline>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                      </svg>
                      Atualizar link
                    </>
                  )}
                </button>
                <button 
                  onClick={handleGetBotStatus} 
                  className="btn btn-status-bot" 
                  disabled={loadingStatus}
                  style={{ flex: 1, minWidth: '200px' }}
                >
                  <FontAwesomeIcon icon={faInfoCircle} />
                  {loadingStatus ? 'Carregando...' : 'Status do bot'}
                </button>
              </div>
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
            <p className="section-description" style={{ marginBottom: '16px', color: '#666', fontSize: '14px' }}>
              Selecione um ou ambos os métodos de pagamento disponíveis para o bot.
            </p>
            <div className="payment-options">
              <button
                type="button"
                className={`payment-option ${formData.payment_method?.includes('credit_card') ? 'active' : ''}`}
                onClick={() => handlePaymentMethod('credit_card')}
              >
                <div className="payment-checkbox">
                  {formData.payment_method?.includes('credit_card') && (
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                  )}
                </div>
                <span>Cartão de crédito</span>
              </button>

              <button
                type="button"
                className={`payment-option ${formData.payment_method?.includes('pix') ? 'active' : ''}`}
                onClick={() => handlePaymentMethod('pix')}
              >
                <div className="payment-checkbox">
                  {formData.payment_method?.includes('pix') && (
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                  )}
                </div>
                <span>Pix direto</span>
              </button>
            </div>
            {formData.payment_method?.length === 0 && (
              <p style={{ marginTop: '8px', color: '#ef4444', fontSize: '13px' }}>
                ⚠️ Selecione pelo menos um método de pagamento.
              </p>
            )}
          </div>

          {/* Botões de verificação e configuração */}

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
        </div>
        </div>
      </div>

      {/* Modal de Link de Convite */}
      {showLinkModal && inviteLink && (
        <div className="modal-overlay" onClick={closeLinkModal}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h2 className="modal-title">Link de Convite do Grupo</h2>
              <button className="modal-close" onClick={closeLinkModal}>
                <FontAwesomeIcon icon={faTimes} />
              </button>
            </div>
            <div className="modal-body">
              <div style={{ marginBottom: '20px' }}>
                <label style={{ display: 'block', marginBottom: '8px', fontWeight: '600' }}>
                  Link de convite:
                </label>
                <div style={{ 
                  display: 'flex', 
                  gap: '8px', 
                  alignItems: 'center',
                  padding: '12px',
                  backgroundColor: '#f3f4f6',
                  borderRadius: '8px',
                  border: '1px solid #e5e7eb'
                }}>
                  <input
                    type="text"
                    value={inviteLink}
                    readOnly
                    style={{
                      flex: 1,
                      padding: '8px',
                      border: '1px solid #d1d5db',
                      borderRadius: '4px',
                      fontSize: '14px',
                      backgroundColor: 'white'
                    }}
                  />
                  <button
                    onClick={handleCopyLink}
                    className="btn btn-primary-600 radius-8 px-16 py-9"
                    style={{ whiteSpace: 'nowrap' }}
                  >
                    Copiar
                  </button>
                </div>
              </div>
              <div style={{ 
                padding: '12px', 
                backgroundColor: '#eff6ff', 
                borderRadius: '8px',
                border: '1px solid #bfdbfe'
              }}>
                <p style={{ margin: 0, fontSize: '14px', color: '#1e40af' }}>
                  <strong>💡 Dica:</strong> Este é o link de convite do grupo do Telegram associado ao bot. 
                  Compartilhe este link para permitir que usuários entrem no grupo.
                </p>
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-primary-600 radius-8 px-20 py-11" onClick={closeLinkModal}>
                Fechar
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal de Status do Bot */}
      {showStatusModal && (
        <div className="modal-overlay" onClick={closeStatusModal}>
          <div className="modal-content status-modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h2 className="modal-title">Status do Bot</h2>
              <button className="modal-close" onClick={closeStatusModal}>
                <FontAwesomeIcon icon={faTimes} />
              </button>
            </div>
            <div className="modal-body">
              {loadingStatus ? (
                <div className="loading-status">
                  <div className="spinner"></div>
                  <p>Carregando status do bot...</p>
                </div>
              ) : botStatus ? (
                <div className="status-content">
                  {/* Status Geral */}
                  <div className="status-section">
                    <h3 className="status-section-title">Status Geral</h3>
                    <div className="status-grid">
                      <div className="status-item">
                        <span className="status-label">ID do Bot:</span>
                        <span className="status-value">{botStatus.bot_id || 'N/A'}</span>
                      </div>
                      <div className="status-item">
                        <span className="status-label">Ativo:</span>
                        <span className={`status-badge ${botStatus.active ? 'status-success' : 'status-error'}`}>
                          {botStatus.active ? '✓ Sim' : '✗ Não'}
                        </span>
                      </div>
                      <div className="status-item">
                        <span className="status-label">Ativado:</span>
                        <span className={`status-badge ${botStatus.activated ? 'status-success' : 'status-warning'}`}>
                          {botStatus.activated ? '✓ Sim' : '⚠ Não'}
                        </span>
                      </div>
                      <div className="status-item">
                        <span className="status-label">Token Válido:</span>
                        <span className={`status-badge ${botStatus.token_valid ? 'status-success' : 'status-error'}`}>
                          {botStatus.token_valid ? '✓ Válido' : '✗ Inválido'}
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Informações do Bot */}
                  {botStatus.bot_info && (
                    <div className="status-section">
                      <h3 className="status-section-title">Informações do Bot</h3>
                      <div className="status-grid">
                        <div className="status-item">
                          <span className="status-label">Nome:</span>
                          <span className="status-value">{botStatus.bot_info.first_name || 'N/A'}</span>
                        </div>
                        <div className="status-item">
                          <span className="status-label">Username:</span>
                          <span className="status-value">@{botStatus.bot_info.username || 'N/A'}</span>
                        </div>
                        <div className="status-item">
                          <span className="status-label">ID:</span>
                          <span className="status-value">{botStatus.bot_info.id || 'N/A'}</span>
                        </div>
                        {botStatus.bot_info.is_bot !== undefined && (
                          <div className="status-item">
                            <span className="status-label">É Bot:</span>
                            <span className={`status-badge ${botStatus.bot_info.is_bot ? 'status-success' : 'status-error'}`}>
                              {botStatus.bot_info.is_bot ? '✓ Sim' : '✗ Não'}
                            </span>
                          </div>
                        )}
                      </div>
                    </div>
                  )}

                  {/* Permissões */}
                  {botStatus.permissions && (
                    <div className="status-section">
                      <h3 className="status-section-title">Permissões</h3>
                      <div className="status-grid">
                        <div className="status-item">
                          <span className="status-label">Ler todas as mensagens do grupo:</span>
                          <span className={`status-badge ${botStatus.permissions.can_read_all_group_messages ? 'status-success' : 'status-error'}`}>
                            {botStatus.permissions.can_read_all_group_messages ? '✓ Habilitado' : '✗ Desabilitado'}
                          </span>
                          <p className="status-description">
                            {botStatus.permissions.can_read_all_group_messages 
                              ? 'O bot pode ler todas as mensagens do grupo, permitindo gerenciamento adequado.'
                              : 'O bot não pode ler todas as mensagens. Configure no BotFather usando /setprivacy e selecione "Disable".'}
                          </p>
                        </div>
                        <div className="status-item">
                          <span className="status-label">Pode entrar em grupos:</span>
                          <span className={`status-badge ${botStatus.permissions.can_join_groups ? 'status-success' : 'status-error'}`}>
                            {botStatus.permissions.can_join_groups ? '✓ Habilitado' : '✗ Desabilitado'}
                          </span>
                          <p className="status-description">
                            {botStatus.permissions.can_join_groups
                              ? 'O bot pode ser adicionado a grupos.'
                              : 'O bot não pode entrar em grupos. Configure no BotFather usando /setjoingroups e selecione "Enable".'}
                          </p>
                        </div>
                        {botStatus.can_manage_groups !== undefined && (
                          <div className="status-item">
                            <span className="status-label">Pode gerenciar grupos:</span>
                            <span className={`status-badge ${botStatus.can_manage_groups ? 'status-success' : 'status-error'}`}>
                              {botStatus.can_manage_groups ? '✓ Sim' : '✗ Não'}
                            </span>
                            <p className="status-description">
                              {botStatus.can_manage_groups
                                ? 'O bot tem todas as permissões necessárias para gerenciar grupos.'
                                : 'O bot não tem todas as permissões necessárias para gerenciar grupos adequadamente.'}
                            </p>
                          </div>
                        )}
                      </div>
                    </div>
                  )}

                  {/* Avisos */}
                  {botStatus.warnings && botStatus.warnings.length > 0 && (
                    <div className="status-section">
                      <h3 className="status-section-title">Avisos e Recomendações</h3>
                      <div className="warnings-list">
                        {botStatus.warnings.map((warning, index) => (
                          <div key={index} className={`warning-item warning-${warning.type}`}>
                            <div className="warning-header">
                              <span className="warning-icon">
                                {warning.type === 'critical' ? '⚠️' : 'ℹ️'}
                              </span>
                              <span className="warning-title">
                                {warning.type === 'critical' ? 'Crítico' : 'Aviso'}
                              </span>
                            </div>
                            <p className="warning-message">{warning.message}</p>
                            {warning.solution && (
                              <div className="warning-solution">
                                <strong>Solução:</strong> {warning.solution}
                              </div>
                            )}
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Erro */}
                  {botStatus.error && (
                    <div className="status-section">
                      <div className="error-message">
                        <strong>Erro:</strong> {botStatus.error}
                      </div>
                    </div>
                  )}
                </div>
              ) : (
                <div className="no-status">
                  <p>Não foi possível carregar o status do bot.</p>
                </div>
              )}
            </div>
            <div className="modal-footer">
              <button className="btn btn-primary" onClick={closeStatusModal}>
                Fechar
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );

  if (isInManageBot) {
    return content;
  }

  return <Layout>{content}</Layout>;
};

export default UpdateBot;

