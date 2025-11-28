import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import Layout from '../components/Layout';
import alertService from '../services/alertService';
import paymentPlanService from '../services/paymentPlanService';
import './Alerts.css';

const Alerts = () => {
  const [searchParams] = useSearchParams();
  let botId = searchParams.get('botId');
  
  // Tenta obter botId do localStorage se não estiver na URL
  if (!botId) {
    const storedBotId = localStorage.getItem('selectedBotId');
    if (storedBotId) {
      botId = storedBotId;
    }
  }

  const [alerts, setAlerts] = useState([]);
  const [paymentPlans, setPaymentPlans] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [filter, setFilter] = useState('all');
  const [searchTerm, setSearchTerm] = useState('');
  const [showSearch, setShowSearch] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingAlert, setEditingAlert] = useState(null);
  const [formData, setFormData] = useState({
    bot_id: botId || '',
    alert_type: '',
    message: '',
    scheduled_date: '',
    scheduled_time: '',
    plan_id: '',
    user_language: 'pt',
    user_category: 'all',
    file_url: ''
  });

  useEffect(() => {
    if (botId) {
      loadAlerts();
      loadPaymentPlans();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoadingData(false);
    }
  }, [botId]);

  const loadAlerts = async () => {
    try {
      setLoadingData(true);
      const alertsData = await alertService.getAlerts(botId);
      setAlerts(alertsData);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar alertas');
    } finally {
      setLoadingData(false);
    }
  };

  const loadPaymentPlans = async () => {
    try {
      const plans = await paymentPlanService.getPaymentPlans(botId);
      setPaymentPlans(plans);
    } catch (err) {
      console.error('Erro ao carregar planos:', err);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, files } = e.target;
    setFormData({
      ...formData,
      [name]: type === 'file' ? files[0] : value
    });
  };

  const handleDateTimeChange = (e) => {
    const { value } = e.target;
    // Formato: dd/mm/aaaa, hh:mm
    const [datePart, timePart] = value.split(', ');
    if (datePart && timePart) {
      setFormData({
        ...formData,
        scheduled_date: datePart,
        scheduled_time: timePart
      });
    } else {
      setFormData({
        ...formData,
        scheduled_date: value,
        scheduled_time: ''
      });
    }
  };

  const handleCreate = () => {
    if (!botId) {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      return;
    }
    setEditingAlert(null);
    setFormData({
      bot_id: botId,
      alert_type: '',
      message: '',
      scheduled_date: '',
      scheduled_time: '',
      plan_id: '',
      user_language: 'pt',
      user_category: 'all',
      file_url: ''
    });
    setShowCreateModal(true);
    setError('');
  };

  const handleEdit = (alert) => {
    setEditingAlert(alert);
    setFormData({
      bot_id: alert.bot_id || botId,
      alert_type: alert.alert_type || '',
      message: alert.message || '',
      scheduled_date: alert.scheduled_date || '',
      scheduled_time: alert.scheduled_time || '',
      plan_id: alert.plan_id || '',
      user_language: alert.user_language || 'pt',
      user_category: alert.user_category || 'all',
      file_url: alert.file_url || ''
    });
    setShowCreateModal(true);
    setError('');
  };

  const handleDelete = async (alertId) => {
    if (!window.confirm('Tem certeza que deseja deletar este alerta?')) {
      return;
    }

    try {
      setLoading(true);
      await alertService.deleteAlert(alertId);
      setSuccess('Alerta excluído com sucesso!');
      loadAlerts();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao excluir alerta');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!formData.alert_type || !formData.message) {
      setError('Por favor, preencha todos os campos obrigatórios');
      return;
    }

    if (formData.alert_type === 'scheduled' && (!formData.scheduled_date || !formData.scheduled_time)) {
      setError('Para alertas agendados, é necessário informar data e hora');
      return;
    }

    if (!formData.bot_id) {
      setError('Bot não selecionado');
      return;
    }

    setLoading(true);
    setError('');

    try {
      // Prepara dados para envio
      const alertData = {
        bot_id: formData.bot_id,
        alert_type: formData.alert_type,
        message: formData.message,
        plan_id: formData.plan_id || null,
        user_language: formData.user_language,
        user_category: formData.user_category,
        file_url: formData.file_url || null
      };

      // Adiciona data e hora apenas para alertas agendados
      if (formData.alert_type === 'scheduled') {
        alertData.scheduled_date = formData.scheduled_date;
        alertData.scheduled_time = formData.scheduled_time;
      }

      if (editingAlert) {
        await alertService.updateAlert(editingAlert.id, alertData);
        setSuccess('Alerta atualizado com sucesso!');
      } else {
        await alertService.createAlert(alertData);
        setSuccess('Alerta criado com sucesso!');
      }

      setShowCreateModal(false);
      loadAlerts();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.errors?.message?.[0] || 'Erro ao salvar alerta');
    } finally {
      setLoading(false);
    }
  };

  const handleProcessAlerts = async () => {
    if (!window.confirm('Deseja processar e enviar os alertas que estão prontos?')) {
      return;
    }

    try {
      setProcessing(true);
      setError('');
      const result = await alertService.processAlerts(botId);
      setSuccess(result.message || `Processamento concluído. ${result.processed} alerta(s) processado(s), ${result.sent} mensagem(s) enviada(s).`);
      loadAlerts();
      setTimeout(() => setSuccess(''), 5000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao processar alertas');
    } finally {
      setProcessing(false);
    }
  };

  const filteredAlerts = alerts.filter(alert => {
    const matchesSearch = alert.message?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         alert.alert_type?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesFilter = filter === 'all' || alert.status === filter;
    return matchesSearch && matchesFilter;
  });

  if (loadingData) {
    return (
      <Layout>
        <div className="alerts-page">
          <div className="loading-container">Carregando alertas...</div>
        </div>
      </Layout>
    );
  }

  if (!botId) {
    return (
      <Layout>
        <div className="alerts-page">
          <div className="error-container">
            <p>{error}</p>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="alerts-page">
        <div className="alerts-content">
          {error && (
            <div className="alert alert-error">
              {error}
            </div>
          )}

          {success && (
            <div className="alert alert-success">
              {success}
            </div>
          )}

          <div className="alerts-header">
            <div className="alerts-title-section">
              <h1 className="alerts-title">Todos os alertas cadastrados</h1>
              <div className="alerts-badge">
                {alerts.length}
              </div>
            </div>
            <div className="alerts-actions">
              {showSearch ? (
                <div className="search-input-container">
                  <input
                    type="text"
                    placeholder="Buscar alertas..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="search-input"
                    autoFocus
                  />
                  <button
                    onClick={() => {
                      setShowSearch(false);
                      setSearchTerm('');
                    }}
                    className="btn-close-search"
                    title="Fechar busca"
                  >
                    ×
                  </button>
                </div>
              ) : (
                <button 
                  className="btn-search" 
                  title="Buscar"
                  onClick={() => setShowSearch(true)}
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                  </svg>
                </button>
              )}
              <select 
                className="filter-dropdown"
                value={filter}
                onChange={(e) => setFilter(e.target.value)}
              >
                <option value="all">Mostrando todos</option>
                <option value="active">Ativos</option>
                <option value="inactive">Inativos</option>
              </select>
              <button
                onClick={handleProcessAlerts}
                className="btn-process-alerts"
                disabled={processing || loading}
                title="Processar e enviar alertas que estão prontos"
              >
                {processing ? 'Processando...' : '🔄 Processar Alertas'}
              </button>
              <button
                onClick={handleCreate}
                className="btn-create-alert"
                disabled={loading}
              >
                Criar novo alerta
              </button>
            </div>
          </div>

          <div className="alerts-table-container">
            <table className="alerts-table">
              <thead>
                <tr>
                  <th>Título</th>
                  <th>Plano</th>
                  <th>Valor da promoção</th>
                  <th>Usuários alvo</th>
                  <th>Status</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                {filteredAlerts.length === 0 ? (
                  <tr>
                    <td colSpan="6" className="empty-state">
                      <div className="empty-message">
                        <p className="empty-title">Nenhum alerta encontrado</p>
                        <p className="empty-subtitle">Tente ajustar os filtros ou criar um novo disparo</p>
                      </div>
                    </td>
                  </tr>
                ) : (
                  filteredAlerts.map((alert) => (
                    <tr key={alert.id}>
                      <td>{alert.message?.substring(0, 50) || '-'}</td>
                      <td>{alert.plan?.title || '-'}</td>
                      <td>-</td>
                      <td>{alert.user_category === 'all' ? 'Todos' : alert.user_category === 'premium' ? 'Premium' : 'Gratuitos'}</td>
                      <td>
                        <span className={`status-badge status-${alert.status}`}>
                          {alert.status === 'active' ? 'Ativo' : alert.status === 'sent' ? 'Enviado' : 'Inativo'}
                        </span>
                      </td>
                      <td>
                        <div className="action-buttons">
                          <button
                            onClick={() => handleEdit(alert)}
                            className="btn-icon btn-edit"
                            title="Editar"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                          </button>
                          <button
                            onClick={() => handleDelete(alert.id)}
                            className="btn-icon btn-delete"
                            title="Deletar"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <polyline points="3 6 5 6 21 6"></polyline>
                              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Create/Edit Modal */}
          {showCreateModal && (
            <div className="modal-overlay" onClick={() => setShowCreateModal(false)}>
              <div className="modal-content alert-modal" onClick={(e) => e.stopPropagation()}>
                <div className="modal-header">
                  <div className="modal-title-section">
                    <h3 className="modal-title">Criar Alerta</h3>
                    <p className="modal-subtitle">Use sua criatividade e capriche na construção da sua mensagem ;)</p>
                  </div>
                  <button
                    onClick={() => setShowCreateModal(false)}
                    className="btn-close"
                  >
                    ×
                  </button>
                </div>
                <div className="modal-body">
                  <div className="form-group">
                    <label>
                      Tipo de Alerta <span className="required-asterisk">*</span>
                    </label>
                    <select
                      name="alert_type"
                      value={formData.alert_type}
                      onChange={handleChange}
                      className="form-input"
                      required
                    >
                      <option value="">Selecione o tipo de alerta</option>
                      <option value="scheduled">Alerta Agendado</option>
                      <option value="periodic">Alerta Periódico</option>
                      <option value="common">Alerta Comum</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label>
                      Mensagem <span className="required-asterisk">*</span>
                    </label>
                    <textarea
                      name="message"
                      value={formData.message}
                      onChange={handleChange}
                      placeholder="Digite a mensagem do alerta"
                      className="form-input form-textarea"
                      rows="5"
                      required
                    />
                  </div>

                  {formData.alert_type === 'scheduled' && (
                    <div className="form-group">
                      <label>
                        Data do Agendamento <span className="required-asterisk">*</span>
                      </label>
                      <input
                        type="date"
                        name="scheduled_date"
                        value={formData.scheduled_date}
                        onChange={handleChange}
                        className="form-input"
                        required={formData.alert_type === 'scheduled'}
                      />
                    </div>
                  )}

                  {formData.alert_type === 'scheduled' && (
                    <div className="form-group">
                      <label>
                        Hora do Agendamento <span className="required-asterisk">*</span>
                      </label>
                      <input
                        type="time"
                        name="scheduled_time"
                        value={formData.scheduled_time}
                        onChange={handleChange}
                        className="form-input"
                        required={formData.alert_type === 'scheduled'}
                      />
                    </div>
                  )}

                  <div className="form-group">
                    <label>
                      Plano <span className="optional-label">(Opcional)</span>
                    </label>
                    <select
                      name="plan_id"
                      value={formData.plan_id}
                      onChange={handleChange}
                      className="form-input"
                    >
                      <option value="">Selecione um plano</option>
                      {paymentPlans.map(plan => (
                        <option key={plan.id} value={plan.id}>{plan.title}</option>
                      ))}
                    </select>
                  </div>

                  <div className="form-group">
                    <label>Idioma dos Usuários</label>
                    <select
                      name="user_language"
                      value={formData.user_language}
                      onChange={handleChange}
                      className="form-input"
                    >
                      <option value="pt">Português</option>
                      <option value="en">Inglês</option>
                      <option value="es">Espanhol</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label>Categoria de Usuários</label>
                    <select
                      name="user_category"
                      value={formData.user_category}
                      onChange={handleChange}
                      className="form-input"
                    >
                      <option value="all">Todos os usuários</option>
                      <option value="premium">Usuários Premium</option>
                      <option value="free">Usuários Gratuitos</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label>
                      URL do Arquivo <span className="optional-label">(Opcional)</span>
                    </label>
                    <input
                      type="url"
                      name="file_url"
                      value={formData.file_url}
                      onChange={handleChange}
                      placeholder="https://exemplo.com/imagem.jpg"
                      className="form-input"
                    />
                    <small>URL da imagem ou vídeo a ser enviado com o alerta</small>
                  </div>
                </div>
                <div className="modal-footer">
                  <button
                    onClick={() => setShowCreateModal(false)}
                    className="btn btn-cancel"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={handleSave}
                    className="btn btn-create-alert"
                    disabled={loading}
                  >
                    {loading ? 'Salvando...' : 'Criar alerta'}
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </Layout>
  );
};

export default Alerts;

