import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import Layout from '../components/Layout';
import botService from '../services/botService';
import './Redirect.css';

const Redirect = () => {
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
  
  const [redirectButtons, setRedirectButtons] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingButton, setEditingButton] = useState(null);
  const [formData, setFormData] = useState({
    title: '',
    link: ''
  });

  const MAX_BUTTONS = 3;

  useEffect(() => {
    if (botId) {
      loadRedirectButtons();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoadingData(false);
    }
  }, [botId]);

  const loadRedirectButtons = async () => {
    try {
      setLoadingData(true);
      // TODO: Implementar API para carregar botões de redirecionamento
      // Por enquanto, usando dados mockados
      setRedirectButtons([]);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar botões de redirecionamento');
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
    if (redirectButtons.length >= MAX_BUTTONS) {
      setError(`Limite de ${MAX_BUTTONS} botões atingido`);
      return;
    }
    setEditingButton(null);
    setFormData({ title: '', link: '' });
    setShowAddModal(true);
  };

  const handleEdit = (button) => {
    setEditingButton(button);
    setFormData({
      title: button.title || '',
      link: button.link || ''
    });
    setShowAddModal(true);
  };

  const handleDelete = async (buttonId) => {
    if (!window.confirm('Tem certeza que deseja deletar este botão?')) {
      return;
    }

    try {
      setLoading(true);
      // TODO: Implementar API para deletar botão
      setRedirectButtons(redirectButtons.filter(btn => btn.id !== buttonId));
      setSuccess('Botão deletado com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao deletar botão');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!formData.title || !formData.link) {
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

    if (redirectButtons.length >= MAX_BUTTONS && !editingButton) {
      setError(`Limite de ${MAX_BUTTONS} botões atingido`);
      return;
    }

    setError('');
    setLoading(true);

    try {
      // TODO: Implementar API para salvar botão
      if (editingButton) {
        // Update existing button
        setRedirectButtons(redirectButtons.map(btn => 
          btn.id === editingButton.id ? { ...btn, ...formData } : btn
        ));
        setSuccess('Botão atualizado com sucesso!');
      } else {
        // Add new button
        const newButton = {
          id: Date.now(), // Temporary ID
          ...formData
        };
        setRedirectButtons([...redirectButtons, newButton]);
        setSuccess('Botão adicionado com sucesso!');
      }
      setShowAddModal(false);
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar botão');
    } finally {
      setLoading(false);
    }
  };

  if (loadingData) {
    return (
      <Layout>
        <div className="redirect-page">
          <div className="loading-container">Carregando...</div>
        </div>
      </Layout>
    );
  }

  if (!botId) {
    return (
      <Layout>
        <div className="redirect-page">
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
      <div className="redirect-page">
        <div className="redirect-content">
          <div className="redirect-header">
            <h2 className="redirect-title">Botões de redirecionamento</h2>
            <p className="redirect-description">
              Botões Extras que aparecerão no bot, redirecionando o usuário para outro lugar.
            </p>
          </div>

          {error && <div className="alert alert-error">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          <div className="redirect-actions">
            <button
              onClick={handleAdd}
              className="btn btn-add"
              disabled={redirectButtons.length >= MAX_BUTTONS}
            >
              Adicionar novo
            </button>
            <span className="limit-info">*Limite: {MAX_BUTTONS} botões</span>
          </div>

          <div className="redirect-table-container">
            <table className="redirect-table">
              <thead>
                <tr>
                  <th>Título</th>
                  <th>Link</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                {redirectButtons.length === 0 ? (
                  <tr>
                    <td colSpan="3" className="empty-state">
                      <div className="empty-icon">+</div>
                      <p>Nenhum botão adicionado ainda</p>
                    </td>
                  </tr>
                ) : (
                  redirectButtons.map((button) => (
                    <tr key={button.id}>
                      <td>{button.title}</td>
                      <td>
                        <a href={button.link} target="_blank" rel="noopener noreferrer" className="link-preview">
                          {button.link}
                        </a>
                      </td>
                      <td>
                        <div className="action-buttons">
                          <button
                            onClick={() => handleEdit(button)}
                            className="btn-icon btn-edit"
                            title="Editar"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                          </button>
                          <button
                            onClick={() => handleDelete(button.id)}
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

          {/* Add/Edit Modal */}
          {showAddModal && (
            <div className="modal-overlay" onClick={() => setShowAddModal(false)}>
              <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                <div className="modal-header">
                  <h3>{editingButton ? 'Editar Botão' : 'Adicionar Novo Botão'}</h3>
                  <button
                    onClick={() => setShowAddModal(false)}
                    className="btn-close"
                  >
                    ×
                  </button>
                </div>
                <div className="modal-body">
                  <div className="form-group">
                    <label>Título</label>
                    <input
                      type="text"
                      name="title"
                      value={formData.title}
                      onChange={handleChange}
                      placeholder="Digite o título do botão"
                      className="form-input"
                    />
                  </div>
                  <div className="form-group">
                    <label>Link</label>
                    <input
                      type="url"
                      name="link"
                      value={formData.link}
                      onChange={handleChange}
                      placeholder="https://exemplo.com"
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

export default Redirect;

