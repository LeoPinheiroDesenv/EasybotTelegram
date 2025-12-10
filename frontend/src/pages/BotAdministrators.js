import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEdit, faTrash, faPlus, faSync } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import { useManageBot } from '../contexts/ManageBotContext';
import botAdministratorService from '../services/botAdministratorService';
import useConfirm from '../hooks/useConfirm';
import './BotAdministrators.css';

const BotAdministrators = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const { botId } = useParams();
  const isInManageBot = useManageBot();

  const [administrators, setAdministrators] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showModal, setShowModal] = useState(false);
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [botId]);

  const loadAdministrators = async () => {
    try {
      setLoadingData(true);
      setError('');
      const admins = await botAdministratorService.getAll(botId);
      setAdministrators(admins);
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

  const handleCreate = () => {
    setEditingAdmin(null);
    setFormData({ user_id: '' });
    setShowModal(true);
  };

  const handleEdit = (admin) => {
    setEditingAdmin(admin);
    setFormData({
      user_id: admin.telegram_user_id || ''
    });
    setShowModal(true);
  };

  const handleDelete = async (adminId) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja remover este administrador?',
      type: 'warning',
    });

    if (!confirmed) return;

    setLoading(true);
    setError('');
    setSuccess('');

    try {
      await botAdministratorService.delete(adminId);
      setSuccess('Administrador removido com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
      loadAdministrators();
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao remover administrador');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!formData.user_id || !formData.user_id.trim()) {
      setError('Por favor, insira o ID do usuário');
      return;
    }

    if (!/^\d+$/.test(formData.user_id.trim())) {
      setError('O ID do usuário deve conter apenas números');
      return;
    }

    if (!botId) {
      setError('Bot não selecionado');
      return;
    }

    setError('');
    setSuccess('');
    setLoading(true);

    try {
      if (editingAdmin) {
        await botAdministratorService.update(editingAdmin.id, {
          telegram_user_id: formData.user_id.trim()
        });
        setSuccess('Administrador atualizado com sucesso!');
      } else {
        await botAdministratorService.create({
          bot_id: botId,
          telegram_user_id: formData.user_id.trim()
        });
        setSuccess('Administrador adicionado com sucesso!');
      }
      setTimeout(() => setSuccess(''), 3000);
      setShowModal(false);
      loadAdministrators();
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.errors?.telegram_user_id?.[0] || 'Erro ao salvar administrador');
    } finally {
      setLoading(false);
    }
  };

  const content = (
    <>
      <DialogComponent />
      <div className="bot-administrators-page">
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
            <div className="bot-administrators-content">
              <div className="bot-administrators-header">
                <div className="header-text">
                  <h1>Gerencie os administradores do seu bot</h1>
                  <p>Adicione usuários que terão permissões administrativas. Coloque o ID de usuários e dê o comando <strong>/comandos</strong> no seu BOT no Telegram.</p>
                </div>
                <div className="header-actions">
                  <button onClick={loadAdministrators} className="btn btn-update" disabled={loading}>
                    <FontAwesomeIcon icon={faSync} />
                    Atualizar
                  </button>
                  <button onClick={handleCreate} className="btn btn-primary">
                    <FontAwesomeIcon icon={faPlus} />
                    Adicionar Administrador
                  </button>
                </div>
              </div>

              {error && <div className="alert alert-error">{error}</div>}
              {success && <div className="alert alert-success">{success}</div>}

              {/* Administrators List */}
              <div className="bot-administrators-section">
                <h2 className="section-title">Administradores ativos</h2>
                {administrators.length === 0 ? (
                  <div className="empty-state">
                    <p>Nenhum administrador cadastrado ainda.</p>
                    <p>Clique em "Adicionar Administrador" para começar.</p>
                  </div>
                ) : (
                  <div className="table-wrapper">
                    <table className="bot-administrators-table">
                      <thead>
                        <tr>
                          <th>ID do usuário</th>
                          <th>Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        {administrators.map((admin) => (
                          <tr key={admin.id}>
                            <td>{admin.telegram_user_id}</td>
                            <td>
                              <div className="action-buttons">
                                <button
                                  onClick={() => handleEdit(admin)}
                                  className="btn-icon btn-edit"
                                  title="Editar"
                                >
                                  <FontAwesomeIcon icon={faEdit} />
                                </button>
                                <button
                                  onClick={() => handleDelete(admin.id)}
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
                    <h2>{editingAdmin ? 'Editar Administrador' : 'Adicionar Novo Administrador'}</h2>
                    <button className="modal-close" onClick={() => setShowModal(false)}>
                      ×
                    </button>
                  </div>
                  <form className="modal-form" onSubmit={handleSubmit}>
                    <div className="form-group">
                      <label>
                        ID do usuário <span className="required">*</span>
                      </label>
                      <input
                        type="text"
                        name="user_id"
                        value={formData.user_id}
                        onChange={handleChange}
                        placeholder="Digite o ID do usuário (apenas números)"
                        required
                        pattern="[0-9]+"
                        title="Apenas números são permitidos"
                      />
                      <small>O ID do usuário do Telegram (apenas números)</small>
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
                        {loading ? 'Salvando...' : editingAdmin ? 'Atualizar' : 'Criar'}
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

export default BotAdministrators;
