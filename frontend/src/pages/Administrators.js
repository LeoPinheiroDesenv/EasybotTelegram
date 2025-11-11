import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import Layout from '../components/Layout';
import botService from '../services/botService';
import './Administrators.css';

const Administrators = () => {
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
  
  const [administrators, setAdministrators] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingAdmin, setEditingAdmin] = useState(null);
  const [formData, setFormData] = useState({
    user_id: ''
  });

  useEffect(() => {
    if (botId) {
      loadAdministrators();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoadingData(false);
    }
  }, [botId]);

  const loadAdministrators = async () => {
    try {
      setLoadingData(true);
      // TODO: Implementar API para carregar administradores
      // Por enquanto, usando dados mockados
      setAdministrators([]);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar administradores');
    } finally {
      setLoadingData(false);
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
    setEditingAdmin(null);
    setFormData({ user_id: '' });
    setShowAddModal(true);
  };

  const handleEdit = (admin) => {
    setEditingAdmin(admin);
    setFormData({
      user_id: admin.user_id || ''
    });
    setShowAddModal(true);
  };

  const handleDelete = async (adminId) => {
    if (!window.confirm('Tem certeza que deseja remover este administrador?')) {
      return;
    }

    try {
      setLoading(true);
      // TODO: Implementar API para deletar administrador
      setAdministrators(administrators.filter(admin => admin.id !== adminId));
      setSuccess('Administrador removido com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao remover administrador');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!formData.user_id || !formData.user_id.trim()) {
      setError('Por favor, insira o ID do usuário');
      return;
    }

    // Validate user ID is numeric
    if (!/^\d+$/.test(formData.user_id.trim())) {
      setError('O ID do usuário deve conter apenas números');
      return;
    }

    setError('');
    setLoading(true);

    try {
      // TODO: Implementar API para salvar administrador
      if (editingAdmin) {
        // Update existing administrator
        setAdministrators(administrators.map(admin => 
          admin.id === editingAdmin.id ? { ...admin, user_id: formData.user_id.trim() } : admin
        ));
        setSuccess('Administrador atualizado com sucesso!');
      } else {
        // Add new administrator
        const newAdmin = {
          id: Date.now(), // Temporary ID
          user_id: formData.user_id.trim()
        };
        setAdministrators([...administrators, newAdmin]);
        setSuccess('Administrador adicionado com sucesso!');
      }
      setShowAddModal(false);
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar administrador');
    } finally {
      setLoading(false);
    }
  };

  if (loadingData) {
    return (
      <Layout>
        <div className="administrators-page">
          <div className="loading-container">Carregando...</div>
        </div>
      </Layout>
    );
  }

  if (!botId) {
    return (
      <Layout>
        <div className="administrators-page">
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
      <div className="administrators-page">
        <div className="administrators-content">
          {/* Funções de administrador Section */}
          <div className="admin-functions-section">
            <div className="admin-functions-content">
              <div className="admin-functions-text">
                <h2 className="section-title">Funções de administrador</h2>
                <p className="section-description">
                  Coloque o ID de usuários em "adicionar administrador". Após isso, dê o seguinte comando no seu BOT no Telegram: <strong>/comandos</strong>
                </p>
                <button
                  onClick={handleAdd}
                  className="btn btn-add-admin"
                >
                  Adicionar administrador
                </button>
              </div>
              <div className="admin-illustration">
                <div className="illustration-container">
                  <div className="monitor">
                    <div className="robot-face">
                      <div className="robot-eye"></div>
                      <div className="robot-eye"></div>
                      <div className="robot-mouth"></div>
                    </div>
                    <div className="robot-antenna"></div>
                    <div className="robot-antenna"></div>
                  </div>
                  <div className="desk"></div>
                  <div className="coffee-cup">
                    <div className="coffee-steam"></div>
                    <div className="coffee-steam"></div>
                    <div className="coffee-steam"></div>
                  </div>
                  <div className="plant plant-1"></div>
                  <div className="plant plant-2"></div>
                </div>
              </div>
            </div>
          </div>

          {/* Administradores ativos Section */}
          <div className="active-admins-section">
            <h2 className="section-title">Administradores ativos</h2>
            
            {error && <div className="alert alert-error">{error}</div>}
            {success && <div className="alert alert-success">{success}</div>}

            <div className="admins-table-container">
              <table className="admins-table">
                <thead>
                  <tr>
                    <th>ID do usuário</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {administrators.length === 0 ? (
                    <tr>
                      <td colSpan="2" className="empty-state">
                        <div className="empty-icon">+</div>
                        <p>Nenhum administrador adicionado ainda</p>
                      </td>
                    </tr>
                  ) : (
                    administrators.map((admin) => (
                      <tr key={admin.id}>
                        <td>{admin.user_id}</td>
                        <td>
                          <div className="action-buttons">
                            <button
                              onClick={() => handleEdit(admin)}
                              className="btn-icon btn-edit"
                              title="Editar"
                            >
                              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                              </svg>
                            </button>
                            <button
                              onClick={() => handleDelete(admin.id)}
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

            <div className="add-button-container">
              <button
                onClick={handleAdd}
                className="btn-add-circle"
                title="Adicionar administrador"
              >
                +
              </button>
            </div>
          </div>

          {/* Add/Edit Modal */}
          {showAddModal && (
            <div className="modal-overlay" onClick={() => setShowAddModal(false)}>
              <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                <div className="modal-header">
                  <h3>{editingAdmin ? 'Editar Administrador' : 'Adicionar Administrador'}</h3>
                  <button
                    onClick={() => setShowAddModal(false)}
                    className="btn-close"
                  >
                    ×
                  </button>
                </div>
                <div className="modal-body">
                  <div className="form-group">
                    <label>ID do usuário</label>
                    <input
                      type="text"
                      name="user_id"
                      value={formData.user_id}
                      onChange={handleChange}
                      placeholder="Digite o ID do usuário (apenas números)"
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
                    className="btn btn-primary"
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

export default Administrators;

