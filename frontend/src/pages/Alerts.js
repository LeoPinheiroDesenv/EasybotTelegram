import React, { useState, useEffect } from 'react';
import Layout from '../components/Layout';
import './Alerts.css';

const Alerts = () => {
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(false);
  const [filter, setFilter] = useState('all');
  const [searchTerm, setSearchTerm] = useState('');
  const [showSearch, setShowSearch] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingAlert, setEditingAlert] = useState(null);
  const [formData, setFormData] = useState({
    alert_type: '',
    message: '',
    scheduled_date: '',
    scheduled_time: '',
    plan_id: '',
    user_language: 'pt',
    user_category: 'all',
    file: null
  });

  useEffect(() => {
    loadAlerts();
  }, []);

  const loadAlerts = async () => {
    try {
      setLoading(true);
      // TODO: Implementar API para carregar alertas
      // Por enquanto, usando dados mockados
      setAlerts([]);
    } catch (err) {
      console.error('Erro ao carregar alertas:', err);
    } finally {
      setLoading(false);
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
    setEditingAlert(null);
    setFormData({
      alert_type: '',
      message: '',
      scheduled_date: '',
      scheduled_time: '',
      plan_id: '',
      user_language: 'pt',
      user_category: 'all',
      file: null
    });
    setShowCreateModal(true);
  };

  const handleEdit = (alert) => {
    setEditingAlert(alert);
    setFormData({
      alert_type: alert.alert_type || '',
      message: alert.message || '',
      scheduled_date: alert.scheduled_date || '',
      scheduled_time: alert.scheduled_time || '',
      plan_id: alert.plan_id || '',
      user_language: alert.user_language || 'pt',
      user_category: alert.user_category || 'all',
      file: alert.file || null
    });
    setShowCreateModal(true);
  };

  const handleDelete = async (alertId) => {
    if (!window.confirm('Tem certeza que deseja deletar este alerta?')) {
      return;
    }

    try {
      setLoading(true);
      // TODO: Implementar API para deletar alerta
      setAlerts(alerts.filter(alert => alert.id !== alertId));
    } catch (err) {
      console.error('Erro ao deletar alerta:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!formData.alert_type || !formData.message || !formData.scheduled_date) {
      alert('Por favor, preencha todos os campos obrigatórios');
      return;
    }

    setLoading(true);

    try {
      // TODO: Implementar API para salvar alerta
      if (editingAlert) {
        setAlerts(alerts.map(alert => 
          alert.id === editingAlert.id ? { ...alert, ...formData } : alert
        ));
      } else {
        const newAlert = {
          id: Date.now(),
          ...formData
        };
        setAlerts([...alerts, newAlert]);
      }
      setShowCreateModal(false);
    } catch (err) {
      console.error('Erro ao salvar alerta:', err);
    } finally {
      setLoading(false);
    }
  };

  const filteredAlerts = alerts.filter(alert => {
    const matchesSearch = alert.title?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesFilter = filter === 'all' || alert.status === filter;
    return matchesSearch && matchesFilter;
  });

  return (
    <Layout>
      <div className="alerts-page">
        <div className="alerts-content">
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
                onClick={handleCreate}
                className="btn-create-alert"
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
                      <td>{alert.title}</td>
                      <td>{alert.plan_name || '-'}</td>
                      <td>{alert.promotion_value}</td>
                      <td>{alert.target_users}</td>
                      <td>
                        <span className={`status-badge status-${alert.status}`}>
                          {alert.status === 'active' ? 'Ativo' : 'Inativo'}
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

                  <div className="form-group">
                    <label>
                      Data e Hora do Agendamento <span className="required-asterisk">*</span>
                    </label>
                    <div className="datetime-input-container">
                      <input
                        type="text"
                        name="scheduled_datetime"
                        value={`${formData.scheduled_date || ''}${formData.scheduled_time ? ', ' + formData.scheduled_time : ''}`}
                        onChange={handleDateTimeChange}
                        placeholder="dd/mm/aaaa, --:--"
                        className="form-input datetime-input"
                        required
                      />
                      <button type="button" className="datetime-icon-btn" title="Selecionar data e hora">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                          <circle cx="12" cy="12" r="10"></circle>
                          <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                      </button>
                    </div>
                  </div>

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
                      {/* TODO: Carregar planos da API */}
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
                      Arquivo <span className="optional-label">(Opcional)</span>
                    </label>
                    <div className="file-upload-area">
                      <input
                        type="file"
                        name="file"
                        id="file-upload"
                        onChange={handleChange}
                        className="file-input"
                        accept="image/*,video/*"
                      />
                      <label htmlFor="file-upload" className="file-upload-label">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="file-icon">
                          <path d="M16.5 6v11.5c0 2.5-2 4.5-4.5 4.5S7 20 7 17.5V5a2.5 2.5 0 0 1 5 0v10.5"></path>
                        </svg>
                        <span className="file-upload-text">Arquivo (Opcional)</span>
                      </label>
                      <p className="file-upload-note">
                        Anexos devem ter até 2MB para imagens e até 20MB para videos
                      </p>
                      {formData.file && (
                        <div className="file-selected">
                          <span>{formData.file.name}</span>
                          <button
                            type="button"
                            onClick={() => setFormData({ ...formData, file: null })}
                            className="file-remove-btn"
                          >
                            ×
                          </button>
                        </div>
                      )}
                    </div>
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

