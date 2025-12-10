import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEdit, faTrash, faPlus, faSync } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import { useManageBot } from '../contexts/ManageBotContext';
import redirectButtonService from '../services/redirectButtonService';
import useConfirm from '../hooks/useConfirm';
import './RedirectButtons.css';

const RedirectButtons = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const { botId } = useParams();
  const isInManageBot = useManageBot();

  const [redirectButtons, setRedirectButtons] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showModal, setShowModal] = useState(false);
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [botId]);

  const loadRedirectButtons = async () => {
    try {
      setLoadingData(true);
      setError('');
      const buttons = await redirectButtonService.getRedirectButtons(botId);
      setRedirectButtons(buttons);
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

  const handleCreate = () => {
    if (redirectButtons.length >= MAX_BUTTONS) {
      setError(`Limite de ${MAX_BUTTONS} botões atingido`);
      return;
    }
    setEditingButton(null);
    setFormData({ title: '', link: '' });
    setShowModal(true);
  };

  const handleEdit = (button) => {
    setEditingButton(button);
    setFormData({
      title: button.title || '',
      link: button.link || ''
    });
    setShowModal(true);
  };

  const handleDelete = async (buttonId) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja remover este botão de redirecionamento?',
      type: 'warning',
    });

    if (!confirmed) return;

    setLoading(true);
    setError('');
    setSuccess('');

    try {
      await redirectButtonService.deleteRedirectButton(botId, buttonId);
      setSuccess('Botão removido com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
      loadRedirectButtons();
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao remover botão');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
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
    setSuccess('');
    setLoading(true);

    try {
      if (editingButton) {
        await redirectButtonService.updateRedirectButton(botId, editingButton.id, formData);
        setSuccess('Botão atualizado com sucesso!');
      } else {
        await redirectButtonService.createRedirectButton(botId, formData);
        setSuccess('Botão criado com sucesso!');
      }
      setTimeout(() => setSuccess(''), 3000);
      setShowModal(false);
      loadRedirectButtons();
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.errors?.link?.[0] || 'Erro ao salvar botão');
    } finally {
      setLoading(false);
    }
  };

  const content = (
    <>
      <DialogComponent />
      <div className="redirect-buttons-page">
        {loadingData ? (
          <div className="loading-container">Carregando...</div>
        ) : !botId ? (
          <div className="error-container">
            <p>{error}</p>
            <button onClick={() => navigate('/')} className="btn btn-primary">
              Voltar ao Dashboard
            </button>
          </div>
        ) : (
          <>
            <div className="redirect-buttons-content">
              <div className="redirect-buttons-header">
                <div className="header-text">
                  <h1>Gerencie seus botões de redirecionamento</h1>
                  <p>Botões extras que aparecerão no bot, redirecionando o usuário para outro lugar. Você pode adicionar até {MAX_BUTTONS} botões.</p>
                </div>
                <div className="header-actions">
                  <button onClick={loadRedirectButtons} className="btn btn-update" disabled={loading}>
                    <FontAwesomeIcon icon={faSync} />
                    Atualizar
                  </button>
                  <button onClick={handleCreate} className="btn btn-primary" disabled={redirectButtons.length >= MAX_BUTTONS}>
                    <FontAwesomeIcon icon={faPlus} />
                    Adicionar Botão
                  </button>
                </div>
              </div>

              {error && <div className="alert alert-error">{error}</div>}
              {success && <div className="alert alert-success">{success}</div>}

              {/* Buttons List */}
              <div className="redirect-buttons-section">
                <h2 className="section-title">Botões de redirecionamento</h2>
                {redirectButtons.length === 0 ? (
                  <div className="empty-state">
                    <p>Nenhum botão de redirecionamento cadastrado ainda.</p>
                    <p>Clique em "Adicionar Botão" para começar.</p>
                  </div>
                ) : (
                  <div className="table-wrapper">
                    <table className="redirect-buttons-table">
                      <thead>
                        <tr>
                          <th>Título</th>
                          <th>Link</th>
                          <th>Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        {redirectButtons.map((button) => (
                          <tr key={button.id}>
                            <td>{button.title}</td>
                            <td>
                              <a 
                                href={button.link} 
                                target="_blank" 
                                rel="noopener noreferrer" 
                                className="link-preview"
                              >
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
                                  <FontAwesomeIcon icon={faEdit} />
                                </button>
                                <button
                                  onClick={() => handleDelete(button.id)}
                                  className="btn-icon btn-delete"
                                  title="Remover"
                                  disabled={loading}
                                >
                                  <FontAwesomeIcon icon={faTrash} />
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>

            {/* Modal */}
            {showModal && (
              <div className="modal-overlay" onClick={() => setShowModal(false)}>
                <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                  <div className="modal-header">
                    <h2>{editingButton ? 'Editar Botão' : 'Adicionar Novo Botão'}</h2>
                    <button className="modal-close" onClick={() => setShowModal(false)}>
                      ×
                    </button>
                  </div>
                  <form className="modal-form" onSubmit={handleSubmit}>
                    <div className="form-group">
                      <label>Título *</label>
                      <input
                        type="text"
                        name="title"
                        value={formData.title}
                        onChange={handleChange}
                        required
                        placeholder="Digite o título do botão"
                      />
                    </div>

                    <div className="form-group">
                      <label>Link *</label>
                      <input
                        type="url"
                        name="link"
                        value={formData.link}
                        onChange={handleChange}
                        required
                        placeholder="https://exemplo.com"
                      />
                    </div>

                    <div className="modal-footer">
                      <button
                        type="button"
                        className="btn btn-cancel"
                        onClick={() => setShowModal(false)}
                      >
                        Cancelar
                      </button>
                      <button type="submit" className="btn btn-primary" disabled={loading}>
                        {loading ? 'Salvando...' : editingButton ? 'Atualizar' : 'Criar'}
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </>
  );

  if (isInManageBot) {
    return content;
  }

  return <Layout>{content}</Layout>;
};

export default RedirectButtons;
