import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import Layout from '../components/Layout';
import botService from '../services/botService';
import paymentPlanService from '../services/paymentPlanService';
import './Groups.css';

const Groups = () => {
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
  
  const [groups, setGroups] = useState([]);
  const [paymentPlans, setPaymentPlans] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingGroup, setEditingGroup] = useState(null);
  const [selectedPlans, setSelectedPlans] = useState([]);
  const [formData, setFormData] = useState({
    title: '',
    link: '',
    group_id: ''
  });

  useEffect(() => {
    if (botId) {
      loadGroups();
      loadPaymentPlans();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoadingData(false);
    }
  }, [botId]);

  const loadGroups = async () => {
    try {
      setLoadingData(true);
      // TODO: Implementar API para carregar grupos
      // Por enquanto, usando dados mockados
      setGroups([]);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar grupos');
    } finally {
      setLoadingData(false);
    }
  };

  const loadPaymentPlans = async () => {
    try {
      const plans = await paymentPlanService.getAllPaymentPlans(botId);
      setPaymentPlans(plans);
    } catch (err) {
      console.error('Erro ao carregar planos de pagamento:', err);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({
      ...formData,
      [name]: value
    });
    setError('');
  };

  const handleAdd = () => {
    setEditingGroup(null);
    setFormData({ title: '', link: '', group_id: '' });
    setSelectedPlans([]);
    setShowAddModal(true);
  };

  const handleEdit = (group) => {
    setEditingGroup(group);
    setFormData({
      title: group.title || '',
      link: group.link || '',
      group_id: group.group_id || ''
    });
    setSelectedPlans(group.selected_plans || []);
    setShowAddModal(true);
  };

  const handlePlanToggle = (planId) => {
    setSelectedPlans(prev => {
      if (prev.includes(planId)) {
        return prev.filter(id => id !== planId);
      } else {
        return [...prev, planId];
      }
    });
  };

  const handleDelete = async (groupId) => {
    if (!window.confirm('Tem certeza que deseja deletar este grupo?')) {
      return;
    }

    try {
      setLoading(true);
      // TODO: Implementar API para deletar grupo
      setGroups(groups.filter(group => group.id !== groupId));
      setSuccess('Grupo deletado com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao deletar grupo');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!formData.title || !formData.link || !formData.group_id) {
      setError('Por favor, preencha todos os campos');
      return;
    }

    // Validate URL
    try {
      new URL(formData.link);
    } catch {
      setError('Por favor, insira uma URL válida');
      return;
    }

    // Validate group ID (should start with -100 for Telegram groups)
    if (!formData.group_id.startsWith('-100')) {
      setError('O ID do grupo deve começar com -100');
      return;
    }

    setError('');
    setLoading(true);

    try {
      // TODO: Implementar API para salvar grupo
      if (editingGroup) {
        // Update existing group
        setGroups(groups.map(group => 
          group.id === editingGroup.id ? { ...group, ...formData, selected_plans: selectedPlans } : group
        ));
        setSuccess('Grupo atualizado com sucesso!');
      } else {
        // Add new group
        const newGroup = {
          id: Date.now(), // Temporary ID
          ...formData,
          selected_plans: selectedPlans
        };
        setGroups([...groups, newGroup]);
        setSuccess('Grupo adicionado com sucesso!');
      }
      setShowAddModal(false);
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar grupo');
    } finally {
      setLoading(false);
    }
  };

  if (loadingData) {
    return (
      <Layout>
        <div className="groups-page">
          <div className="loading-container">Carregando...</div>
        </div>
      </Layout>
    );
  }

  if (!botId) {
    return (
      <Layout>
        <div className="groups-page">
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
      <div className="groups-page">
        <div className="groups-content">
          {/* Top Section - "Tenha grupos e canais agora mesmo" */}
          <div className="groups-promo-section">
            <div className="groups-promo-content">
              <div className="groups-promo-text">
                <h2 className="promo-title">Tenha grupos e canais agora mesmo</h2>
                <p className="promo-description">
                  Adicione canais para expandir sua comunidade e gerenciar grupos de forma mais eficiente.
                </p>
                <button
                  onClick={handleAdd}
                  className="btn btn-create-group"
                >
                  Criar novo grupo
                </button>
              </div>
              <div className="groups-illustration">
                <div className="illustration-container">
                  <div className="smartphone">
                    <div className="phone-screen">
                      <div className="app-icon app-icon-1"></div>
                      <div className="app-icon app-icon-2"></div>
                      <div className="app-icon app-icon-3"></div>
                      <div className="app-icon app-icon-4"></div>
                    </div>
                  </div>
                  <div className="person person-1">
                    <div className="person-body"></div>
                    <div className="person-head"></div>
                    <div className="megaphone"></div>
                  </div>
                  <div className="person person-2">
                    <div className="person-body"></div>
                    <div className="person-head"></div>
                  </div>
                  <div className="person person-3">
                    <div className="person-body"></div>
                    <div className="person-head"></div>
                  </div>
                  <div className="decoration decoration-1">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                      <polyline points="17 8 12 3 7 8"></polyline>
                      <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                  </div>
                  <div className="decoration decoration-2">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M7 10v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V10"></path>
                      <path d="M12 2v8"></path>
                      <path d="M9 6l3-3 3 3"></path>
                    </svg>
                  </div>
                  <div className="decoration decoration-3">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Bottom Section - "Grupos ativos" */}
          <div className="active-groups-section">
            <h2 className="section-title">Grupos ativos</h2>
            
            {error && <div className="alert alert-error">{error}</div>}
            {success && <div className="alert alert-success">{success}</div>}

            <div className="groups-table-container">
              <table className="groups-table">
                <thead>
                  <tr>
                    <th>Título</th>
                    <th>Link</th>
                    <th>ID do grupo</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {groups.length === 0 ? (
                    <tr>
                      <td colSpan="4" className="empty-state">
                        <div className="empty-icon">+</div>
                        <p>Nenhum grupo adicionado ainda</p>
                      </td>
                    </tr>
                  ) : (
                    groups.map((group) => (
                      <tr key={group.id}>
                        <td>{group.title}</td>
                        <td>
                          <a href={group.link} target="_blank" rel="noopener noreferrer" className="link-preview">
                            {group.link}
                          </a>
                        </td>
                        <td>{group.group_id}</td>
                        <td>
                          <div className="action-buttons">
                            <button
                              onClick={() => handleEdit(group)}
                              className="btn-icon btn-edit"
                              title="Editar"
                            >
                              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                              </svg>
                            </button>
                            <button
                              onClick={() => handleDelete(group.id)}
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
          </div>

          {/* Add/Edit Modal */}
          {showAddModal && (
            <div className="modal-overlay" onClick={() => setShowAddModal(false)}>
              <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                <div className="modal-header">
                  <h3>{editingGroup ? 'Editar Grupo' : 'Criar Grupo'}</h3>
                  <button
                    onClick={() => setShowAddModal(false)}
                    className="btn-close"
                  >
                    ×
                  </button>
                </div>
                <div className="modal-body">
                  <div className="form-group">
                    <label>Título do Grupo</label>
                    <input
                      type="text"
                      name="title"
                      value={formData.title}
                      onChange={handleChange}
                      placeholder="Digite o título do grupo"
                      className="form-input"
                    />
                  </div>
                  <div className="form-group">
                    <label>Id do Grupo</label>
                    <input
                      type="text"
                      name="group_id"
                      value={formData.group_id}
                      onChange={handleChange}
                      placeholder="-100..."
                      className="form-input"
                    />
                  </div>
                  
                  {/* Planos Disponíveis Section */}
                  <div className="form-group plans-section">
                    <div className="plans-header">
                      <label>Planos Disponíveis</label>
                      <span className="plans-selected-text">O plano(s) selecionado(s)</span>
                    </div>
                    <div className="plans-container">
                      {paymentPlans.length === 0 ? (
                        <p className="no-plans-text">Nenhum plano disponível</p>
                      ) : (
                        paymentPlans.map((plan) => (
                          <div
                            key={plan.id}
                            className={`plan-item ${selectedPlans.includes(plan.id) ? 'selected' : ''}`}
                            onClick={() => handlePlanToggle(plan.id)}
                          >
                            <input
                              type="checkbox"
                              id={`plan-${plan.id}`}
                              checked={selectedPlans.includes(plan.id)}
                              onChange={() => handlePlanToggle(plan.id)}
                              className="plan-checkbox"
                            />
                            <label htmlFor={`plan-${plan.id}`} className="plan-label">
                              {plan.title}
                            </label>
                          </div>
                        ))
                      )}
                    </div>
                    <p className="plans-instruction">
                      Clique nos planos para selecioná-los ou desselecioná-los
                    </p>
                  </div>

                  <div className="form-group">
                    <label>Link do Grupo</label>
                    <input
                      type="url"
                      name="link"
                      value={formData.link}
                      onChange={handleChange}
                      placeholder="https://t.me/..."
                      className="form-input"
                    />
                  </div>
                </div>
                <div className="modal-footer">
                  <button
                    onClick={() => setShowAddModal(false)}
                    className="btn btn-cancel"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={handleSave}
                    className="btn btn-save"
                    disabled={loading}
                  >
                    {loading ? 'Salvando...' : 'Salvar'}
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

export default Groups;

