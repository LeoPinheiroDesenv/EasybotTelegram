import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Layout from '../components/Layout';
import paymentGatewayConfigService from '../services/paymentGatewayConfigService';
import useConfirm from '../hooks/useConfirm';
import './PaymentGatewayConfigs.css';

const PaymentGatewayConfigs = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const [botId, setBotId] = useState(null);
  const [configs, setConfigs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [editingConfig, setEditingConfig] = useState(null);
  const [selectedGateway, setSelectedGateway] = useState('mercadopago');
  const [selectedEnvironment, setSelectedEnvironment] = useState('test');
  const [apiStatus, setApiStatus] = useState(null);
  const [loadingStatus, setLoadingStatus] = useState(false);
  const [formData, setFormData] = useState({
    gateway: 'mercadopago',
    environment: 'test',
    access_token: '',
    public_key: '',
    client_id: '',
    client_secret: '',
    secret_key: '',
    webhook_secret: '',
    webhook_url: '',
    is_active: false
  });

  useEffect(() => {
    const storedBotId = localStorage.getItem('selectedBotId');
    if (!storedBotId) {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoading(false);
      return;
    }
    setBotId(storedBotId);
    loadConfigs(storedBotId);
  }, []);

  const loadConfigs = async (id) => {
    try {
      setLoading(true);
      const data = await paymentGatewayConfigService.getConfigs(id);
      setConfigs(data);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar configurações');
    } finally {
      setLoading(false);
    }
  };

  const getConfigForGateway = (gateway, environment) => {
    return configs.find(
      c => c.gateway === gateway && c.environment === environment
    );
  };

  const handleOpenModal = (gateway, environment) => {
    setSelectedGateway(gateway);
    setSelectedEnvironment(environment);
    
    const existingConfig = getConfigForGateway(gateway, environment);
    
    if (existingConfig) {
      setEditingConfig(existingConfig);
      // Mapear dados do backend para o formulário
      setFormData({
        gateway: existingConfig.gateway || gateway,
        environment: existingConfig.environment || environment,
        access_token: existingConfig.access_token || existingConfig.api_key || '',
        public_key: existingConfig.public_key || '',
        client_id: existingConfig.client_id || '',
        client_secret: existingConfig.client_secret || '',
        secret_key: existingConfig.secret_key || existingConfig.api_secret || '',
        webhook_secret: existingConfig.webhook_secret || '',
        webhook_url: existingConfig.webhook_url || '',
        is_active: existingConfig.is_active !== undefined ? existingConfig.is_active : (existingConfig.active !== undefined ? existingConfig.active : false)
      });
    } else {
      setEditingConfig(null);
      setFormData({
        gateway: gateway,
        environment: environment,
        access_token: '',
        public_key: '',
        client_id: '',
        client_secret: '',
        secret_key: '',
        webhook_secret: '',
        webhook_url: '',
        is_active: false
      });
    }
    
    setShowModal(true);
  };

  const handleCloseModal = () => {
    setShowModal(false);
    setEditingConfig(null);
    setFormData({
      gateway: 'mercadopago',
      environment: 'test',
      access_token: '',
      public_key: '',
      client_id: '',
      client_secret: '',
      secret_key: '',
      webhook_secret: '',
      webhook_url: '',
      is_active: false
    });
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData({
      ...formData,
      [name]: type === 'checkbox' ? checked : value
    });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    try {
      // Prepara os dados para envio, garantindo que todos os campos sejam enviados
      const configToSave = {
        gateway: formData.gateway || selectedGateway,
        environment: formData.environment || selectedEnvironment,
        bot_id: botId,
        is_active: formData.is_active !== undefined ? formData.is_active : false,
        webhook_url: formData.webhook_url || null,
      };

      // Adiciona campos específicos por gateway
      if (formData.gateway === 'mercadopago' || selectedGateway === 'mercadopago') {
        configToSave.access_token = formData.access_token || '';
        configToSave.public_key = formData.public_key || '';
        configToSave.client_id = formData.client_id || '';
        configToSave.client_secret = formData.client_secret || '';
        configToSave.webhook_secret = formData.webhook_secret || null;
      } else if (formData.gateway === 'stripe' || selectedGateway === 'stripe') {
        configToSave.secret_key = formData.secret_key || '';
        configToSave.public_key = formData.public_key || '';
        configToSave.webhook_secret = formData.webhook_secret || null;
      }

      if (editingConfig) {
        await paymentGatewayConfigService.updateConfig(editingConfig.id, configToSave);
        setSuccess('Configuração atualizada com sucesso!');
      } else {
        await paymentGatewayConfigService.createOrUpdateConfig(configToSave);
        setSuccess('Configuração salva com sucesso!');
      }

      handleCloseModal();
      loadConfigs(botId);
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      // Trata erros de validação
      if (err.response?.data?.errors) {
        const errors = err.response.data.errors;
        const errorMessages = Object.keys(errors).map(key => {
          return `${key}: ${Array.isArray(errors[key]) ? errors[key].join(', ') : errors[key]}`;
        }).join('\n');
        setError(errorMessages);
      } else {
        setError(err.response?.data?.error || err.message || 'Erro ao salvar configuração');
      }
    }
  };

  const handleDelete = async (id) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja excluir esta configuração?',
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      await paymentGatewayConfigService.deleteConfig(id);
      setSuccess('Configuração excluída com sucesso!');
      loadConfigs(botId);
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao excluir configuração');
    }
  };

  const maskToken = (token) => {
    if (!token) return '';
    if (token.length <= 8) return token;
    return token.substring(0, 4) + '••••••••' + token.substring(token.length - 4);
  };

  const handleCheckApiStatus = async (gateway, environment) => {
    setLoadingStatus(true);
    setShowStatusModal(true);
    setSelectedGateway(gateway);
    setSelectedEnvironment(environment);
    setApiStatus(null);

    try {
      const status = await paymentGatewayConfigService.checkApiStatus(botId, gateway, environment);
      setApiStatus(status);
    } catch (err) {
      setApiStatus({
        status: 'error',
        message: 'Erro ao verificar status',
        details: err.response?.data?.message || err.message || 'Erro desconhecido'
      });
    } finally {
      setLoadingStatus(false);
    }
  };

  const handleCloseStatusModal = () => {
    setShowStatusModal(false);
    setApiStatus(null);
  };

  if (loading && !botId) {
    return (
      <Layout>
        <div className="payment-gateway-configs-page">
          <div className="loading-container">Carregando...</div>
        </div>
      </Layout>
    );
  }

  if (!botId) {
    return (
      <Layout>
        <div className="payment-gateway-configs-page">
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
      <DialogComponent />
      <div className="payment-gateway-configs-page">
        <div className="payment-gateway-configs-content">
          
          {error && <div className="alert alert-error">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          {/* Mercado Pago Section */}
          <div className="gateway-section">
            <div className="gateway-header">
              <div className="gateway-title-wrapper">
                <h2>Mercado Pago (PIX)</h2>
                <div className="status-api-buttons">
                  <button
                    className="btn-status-api"
                    onClick={() => handleCheckApiStatus('mercadopago', 'test')}
                    title="Verificar status da API - Ambiente de Teste"
                  >
                    Status API (Teste)
                  </button>
                  <button
                    className="btn-status-api"
                    onClick={() => handleCheckApiStatus('mercadopago', 'production')}
                    title="Verificar status da API - Ambiente de Produção"
                  >
                    Status API (Produção)
                  </button>
                </div>
              </div>
              <div className="gateway-badges">
                <button
                  className={`badge ${getConfigForGateway('mercadopago', 'test')?.is_active ? 'active' : ''}`}
                  onClick={() => handleOpenModal('mercadopago', 'test')}
                >
                  {getConfigForGateway('mercadopago', 'test') ? '✓ Teste' : '+ Teste'}
                </button>
                <button
                  className={`badge ${getConfigForGateway('mercadopago', 'production')?.is_active ? 'active' : ''}`}
                  onClick={() => handleOpenModal('mercadopago', 'production')}
                >
                  {getConfigForGateway('mercadopago', 'production') ? '✓ Produção' : '+ Produção'}
                </button>
              </div>
            </div>

            <div className="gateway-configs-grid">
              {/* Test Environment */}
              <div className="config-card">
                <div className="config-card-header">
                  <h3>Ambiente de Teste</h3>
                  {getConfigForGateway('mercadopago', 'test')?.is_active && (
                    <span className="badge-active">Ativo</span>
                  )}
                </div>
                {getConfigForGateway('mercadopago', 'test') ? (
                  <div className="config-card-content">
                    <p><strong>Access Token:</strong> {maskToken(getConfigForGateway('mercadopago', 'test').access_token || getConfigForGateway('mercadopago', 'test').api_key || '')}</p>
                    <p><strong>Public Key:</strong> {getConfigForGateway('mercadopago', 'test').public_key ? maskToken(getConfigForGateway('mercadopago', 'test').public_key) : 'Não configurado'}</p>
                    <p><strong>Client ID:</strong> {getConfigForGateway('mercadopago', 'test').client_id || 'Não configurado'}</p>
                    <p><strong>Webhook URL:</strong> {getConfigForGateway('mercadopago', 'test').webhook_url || 'Não configurado'}</p>
                    <div className="config-card-actions">
                      <button
                        className="btn btn-edit"
                        onClick={() => handleOpenModal('mercadopago', 'test')}
                      >
                        Editar
                      </button>
                      <button
                        className="btn btn-delete"
                        onClick={() => handleDelete(getConfigForGateway('mercadopago', 'test').id)}
                      >
                        Excluir
                      </button>
                    </div>
                  </div>
                ) : (
                  <div className="config-card-content">
                    <p className="empty-config">Nenhuma configuração</p>
                    <button
                      className="btn btn-primary"
                      onClick={() => handleOpenModal('mercadopago', 'test')}
                    >
                      Configurar
                    </button>
                  </div>
                )}
              </div>

              {/* Production Environment */}
              <div className="config-card">
                <div className="config-card-header">
                  <h3>Ambiente de Produção</h3>
                  {getConfigForGateway('mercadopago', 'production')?.is_active && (
                    <span className="badge-active">Ativo</span>
                  )}
                </div>
                {getConfigForGateway('mercadopago', 'production') ? (
                  <div className="config-card-content">
                    <p><strong>Access Token:</strong> {maskToken(getConfigForGateway('mercadopago', 'production').access_token || getConfigForGateway('mercadopago', 'production').api_key || '')}</p>
                    <p><strong>Public Key:</strong> {getConfigForGateway('mercadopago', 'production').public_key ? maskToken(getConfigForGateway('mercadopago', 'production').public_key) : 'Não configurado'}</p>
                    <p><strong>Client ID:</strong> {getConfigForGateway('mercadopago', 'production').client_id || 'Não configurado'}</p>
                    <p><strong>Webhook URL:</strong> {getConfigForGateway('mercadopago', 'production').webhook_url || 'Não configurado'}</p>
                    <div className="config-card-actions">
                      <button
                        className="btn btn-edit"
                        onClick={() => handleOpenModal('mercadopago', 'production')}
                      >
                        Editar
                      </button>
                      <button
                        className="btn btn-delete"
                        onClick={() => handleDelete(getConfigForGateway('mercadopago', 'production').id)}
                      >
                        Excluir
                      </button>
                    </div>
                  </div>
                ) : (
                  <div className="config-card-content">
                    <p className="empty-config">Nenhuma configuração</p>
                    <button
                      className="btn btn-primary"
                      onClick={() => handleOpenModal('mercadopago', 'production')}
                    >
                      Configurar
                    </button>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Stripe Section */}
          <div className="gateway-section">
            <div className="gateway-header">
              <div className="gateway-title-wrapper">
                <h2>Stripe (Cartão de Crédito)</h2>
                <div className="status-api-buttons">
                  <button
                    className="btn-status-api"
                    onClick={() => handleCheckApiStatus('stripe', 'test')}
                    title="Verificar status da API - Ambiente de Teste"
                  >
                    Status API (Teste)
                  </button>
                  <button
                    className="btn-status-api"
                    onClick={() => handleCheckApiStatus('stripe', 'production')}
                    title="Verificar status da API - Ambiente de Produção"
                  >
                    Status API (Produção)
                  </button>
                </div>
              </div>
              <div className="gateway-badges">
                <button
                  className={`badge ${getConfigForGateway('stripe', 'test')?.is_active ? 'active' : ''}`}
                  onClick={() => handleOpenModal('stripe', 'test')}
                >
                  {getConfigForGateway('stripe', 'test') ? '✓ Teste' : '+ Teste'}
                </button>
                <button
                  className={`badge ${getConfigForGateway('stripe', 'production')?.is_active ? 'active' : ''}`}
                  onClick={() => handleOpenModal('stripe', 'production')}
                >
                  {getConfigForGateway('stripe', 'production') ? '✓ Produção' : '+ Produção'}
                </button>
              </div>
            </div>

            <div className="gateway-configs-grid">
              {/* Test Environment */}
              <div className="config-card">
                <div className="config-card-header">
                  <h3>Ambiente de Teste</h3>
                  {getConfigForGateway('stripe', 'test')?.is_active && (
                    <span className="badge-active">Ativo</span>
                  )}
                </div>
                {getConfigForGateway('stripe', 'test') ? (
                  <div className="config-card-content">
                    <p><strong>Secret Key:</strong> {maskToken(getConfigForGateway('stripe', 'test').secret_key || getConfigForGateway('stripe', 'test').api_secret || '')}</p>
                    <p><strong>Public Key:</strong> {maskToken(getConfigForGateway('stripe', 'test').public_key) || 'Não configurado'}</p>
                    <p><strong>Webhook Secret:</strong> {maskToken(getConfigForGateway('stripe', 'test').webhook_secret) || 'Não configurado'}</p>
                    <div className="config-card-actions">
                      <button
                        className="btn btn-edit"
                        onClick={() => handleOpenModal('stripe', 'test')}
                      >
                        Editar
                      </button>
                      <button
                        className="btn btn-delete"
                        onClick={() => handleDelete(getConfigForGateway('stripe', 'test').id)}
                      >
                        Excluir
                      </button>
                    </div>
                  </div>
                ) : (
                  <div className="config-card-content">
                    <p className="empty-config">Nenhuma configuração</p>
                    <button
                      className="btn btn-primary"
                      onClick={() => handleOpenModal('stripe', 'test')}
                    >
                      Configurar
                    </button>
                  </div>
                )}
              </div>

              {/* Production Environment */}
              <div className="config-card">
                <div className="config-card-header">
                  <h3>Ambiente de Produção</h3>
                  {getConfigForGateway('stripe', 'production')?.is_active && (
                    <span className="badge-active">Ativo</span>
                  )}
                </div>
                {getConfigForGateway('stripe', 'production') ? (
                  <div className="config-card-content">
                    <p><strong>Secret Key:</strong> {maskToken(getConfigForGateway('stripe', 'production').secret_key || getConfigForGateway('stripe', 'production').api_secret || '')}</p>
                    <p><strong>Public Key:</strong> {maskToken(getConfigForGateway('stripe', 'production').public_key) || 'Não configurado'}</p>
                    <p><strong>Webhook Secret:</strong> {maskToken(getConfigForGateway('stripe', 'production').webhook_secret) || 'Não configurado'}</p>
                    <div className="config-card-actions">
                      <button
                        className="btn btn-edit"
                        onClick={() => handleOpenModal('stripe', 'production')}
                      >
                        Editar
                      </button>
                      <button
                        className="btn btn-delete"
                        onClick={() => handleDelete(getConfigForGateway('stripe', 'production').id)}
                      >
                        Excluir
                      </button>
                    </div>
                  </div>
                ) : (
                  <div className="config-card-content">
                    <p className="empty-config">Nenhuma configuração</p>
                    <button
                      className="btn btn-primary"
                      onClick={() => handleOpenModal('stripe', 'production')}
                    >
                      Configurar
                    </button>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Modal */}
        {showModal && (
          <div className="modal-overlay" onClick={handleCloseModal}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>
                  {editingConfig ? 'Editar' : 'Nova'} Configuração - {selectedGateway === 'mercadopago' ? 'Mercado Pago' : 'Stripe'} ({selectedEnvironment === 'test' ? 'Teste' : 'Produção'})
                </h2>
                <button className="modal-close" onClick={handleCloseModal}>×</button>
              </div>
              <form onSubmit={handleSubmit} className="modal-form">
                {selectedGateway === 'mercadopago' ? (
                  <>
                    <div className="form-group">
                      <label>Access Token *</label>
                      <input
                        type="password"
                        name="access_token"
                        value={formData.access_token}
                        onChange={handleChange}
                        required
                        placeholder="APP_USR-..."
                      />
                      <small>Token de acesso do Mercado Pago (obrigatório)</small>
                    </div>
                    <div className="form-group">
                      <label>Public Key</label>
                      <input
                        type="text"
                        name="public_key"
                        value={formData.public_key}
                        onChange={handleChange}
                        placeholder="APP_USR-..."
                      />
                      <small>Chave pública do Mercado Pago (usada no frontend)</small>
                    </div>
                    <div className="form-group">
                      <label>Client ID</label>
                      <input
                        type="text"
                        name="client_id"
                        value={formData.client_id}
                        onChange={handleChange}
                        placeholder="1234567890123456"
                      />
                      <small>ID único da sua integração no Mercado Pago</small>
                    </div>
                    <div className="form-group">
                      <label>Client Secret</label>
                      <input
                        type="password"
                        name="client_secret"
                        value={formData.client_secret}
                        onChange={handleChange}
                        placeholder="Chave secreta do cliente"
                      />
                      <small>Chave secreta usada em alguns plugins para gerar pagamentos</small>
                    </div>
                    <div className="form-group">
                      <label>Webhook URL</label>
                      <input
                        type="url"
                        name="webhook_url"
                        value={formData.webhook_url}
                        onChange={handleChange}
                        placeholder="https://seu-dominio.com/api/payments/webhook/mercadopago"
                      />
                      <small>URL para receber notificações do Mercado Pago</small>
                    </div>
                    <div className="form-group">
                      <label>Webhook Secret</label>
                      <input
                        type="password"
                        name="webhook_secret"
                        value={formData.webhook_secret}
                        onChange={handleChange}
                        placeholder="Chave secreta para validar webhooks"
                      />
                      <small>Chave secreta para validar a autenticidade dos webhooks (opcional, mas recomendado)</small>
                    </div>
                  </>
                ) : (
                  <>
                    <div className="form-group">
                      <label>Secret Key *</label>
                      <input
                        type="password"
                        name="secret_key"
                        value={formData.secret_key}
                        onChange={handleChange}
                        required
                        placeholder="sk_test_..."
                      />
                      <small>Chave secreta do Stripe</small>
                    </div>
                    <div className="form-group">
                      <label>Public Key *</label>
                      <input
                        type="text"
                        name="public_key"
                        value={formData.public_key}
                        onChange={handleChange}
                        required
                        placeholder="pk_test_..."
                      />
                      <small>Chave pública do Stripe (para uso no frontend) - Obrigatória</small>
                    </div>
                    <div className="form-group">
                      <label>Webhook Secret</label>
                      <input
                        type="password"
                        name="webhook_secret"
                        value={formData.webhook_secret}
                        onChange={handleChange}
                        placeholder="whsec_..."
                      />
                      <small>Secret do webhook do Stripe</small>
                    </div>
                    <div className="form-group">
                      <label>Webhook URL</label>
                      <input
                        type="url"
                        name="webhook_url"
                        value={formData.webhook_url}
                        onChange={handleChange}
                        placeholder="https://seu-dominio.com/api/payments/webhook/stripe"
                      />
                      <small>URL para receber notificações do Stripe</small>
                    </div>
                  </>
                )}
                <div className="form-group">
                  <label>
                    <input
                      type="checkbox"
                      name="is_active"
                      checked={formData.is_active}
                      onChange={handleChange}
                    />
                    Ativar esta configuração
                  </label>
                  <small>Quando ativado, esta configuração será usada para processar pagamentos</small>
                </div>
                <div className="modal-footer">
                  <button
                    type="button"
                    onClick={handleCloseModal}
                    className="btn btn-cancel"
                  >
                    Cancelar
                  </button>
                  <button type="submit" className="btn btn-primary">
                    {editingConfig ? 'Atualizar' : 'Salvar'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Status API Modal */}
        {showStatusModal && (
          <div className="modal-overlay" onClick={handleCloseStatusModal}>
            <div className="modal-content status-modal" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>
                  Status da API - {selectedGateway === 'mercadopago' ? 'Mercado Pago' : 'Stripe'} ({selectedEnvironment === 'test' ? 'Teste' : 'Produção'})
                </h2>
                <button className="modal-close" onClick={handleCloseStatusModal}>×</button>
              </div>
              <div className="status-modal-content">
                {loadingStatus ? (
                  <div className="status-loading">
                    <p>Verificando status da API...</p>
                  </div>
                ) : apiStatus ? (
                  <div className={`status-result status-${apiStatus.status}`}>
                    <div className="status-icon">
                      {apiStatus.status === 'success' && '✓'}
                      {apiStatus.status === 'error' && '✗'}
                      {apiStatus.status === 'warning' && '⚠'}
                    </div>
                    <h3 className="status-message">{apiStatus.message}</h3>
                    {apiStatus.details && (
                      <div className="status-details">
                        {typeof apiStatus.details === 'string' ? (
                          <p>{apiStatus.details}</p>
                        ) : (
                          <div className="status-details-object">
                            {Object.entries(apiStatus.details).map(([key, value]) => {
                              // Formatação especial para métodos de pagamento
                              if (key === 'payment_methods_available' && Array.isArray(value)) {
                                return (
                                  <div key={key} className="status-detail-item payment-methods">
                                    <strong>Métodos de Pagamento Habilitados:</strong>
                                    <div className="payment-methods-list">
                                      {value.map((method, index) => (
                                        <span key={index} className={`payment-method-badge ${method === 'pix' ? 'pix-badge' : ''}`}>
                                          {method.toUpperCase()}
                                        </span>
                                      ))}
                                    </div>
                                  </div>
                                );
                              }
                              
                              // Formatação especial para status PIX
                              if (key === 'pix_enabled') {
                                const pixStatus = value === true ? 'Habilitado' : value === false ? 'Não Habilitado' : 'Indeterminado';
                                const pixClass = value === true ? 'pix-enabled' : value === false ? 'pix-disabled' : 'pix-indeterminate';
                                return (
                                  <div key={key} className={`status-detail-item ${pixClass}`}>
                                    <strong>PIX:</strong> 
                                    <span className={`pix-status ${pixClass}`}>
                                      {pixStatus}
                                      {value === false && (
                                        <span className="pix-warning">
                                          {' '}(Verifique se há uma chave PIX cadastrada na conta)
                                        </span>
                                      )}
                                    </span>
                                  </div>
                                );
                              }
                              
                              // Campos normais
                              return (
                                <div key={key} className="status-detail-item">
                                  <strong>{key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}:</strong> {String(value)}
                                </div>
                              );
                            })}
                          </div>
                        )}
                      </div>
                    )}
                    {apiStatus.timestamp && (
                      <div className="status-timestamp">
                        <small>Verificado em: {new Date(apiStatus.timestamp).toLocaleString('pt-BR')}</small>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="status-error">
                    <p>Nenhum status disponível</p>
                  </div>
                )}
              </div>
              <div className="modal-footer">
                <button
                  type="button"
                  onClick={handleCloseStatusModal}
                  className="btn btn-primary"
                >
                  Fechar
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
};

export default PaymentGatewayConfigs;

