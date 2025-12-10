import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEdit, faTrash, faPlus, faLink, faSync, faCopy, faCheck } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import { useManageBot } from '../contexts/ManageBotContext';
import telegramGroupService from '../services/telegramGroupService';
import paymentPlanService from '../services/paymentPlanService';
import useConfirm from '../hooks/useConfirm';
import './BotTelegramGroups.css';

const BotTelegramGroups = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const { botId } = useParams();
  const isInManageBot = useManageBot();

  const [groups, setGroups] = useState([]);
  const [paymentPlans, setPaymentPlans] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editingGroup, setEditingGroup] = useState(null);
  const [copiedLinkId, setCopiedLinkId] = useState(null);
  const [formData, setFormData] = useState({
    title: '',
    telegram_group_id: '',
    payment_plan_id: '',
    type: 'group',
    active: true,
  });

  useEffect(() => {
    if (botId) {
      loadGroups();
      loadPaymentPlans();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoadingData(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [botId]);

  const loadGroups = async () => {
    try {
      setLoadingData(true);
      setError('');
      const data = await telegramGroupService.getAll(botId);
      setGroups(data || []);
    } catch (err) {
      console.error('Erro ao carregar grupos:', err);
      setError(err.response?.data?.error || 'Erro ao carregar grupos e canais');
    } finally {
      setLoadingData(false);
    }
  };

  const loadPaymentPlans = async () => {
    try {
      const data = await paymentPlanService.getAllPaymentPlans(botId);
      setPaymentPlans(data || []);
    } catch (err) {
      console.error('Erro ao carregar planos:', err);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData({
      ...formData,
      [name]: type === 'checkbox' ? checked : value,
    });
    setError('');
  };

  const handleCreate = () => {
    setEditingGroup(null);
    setFormData({
      title: '',
      telegram_group_id: '',
      payment_plan_id: '',
      type: 'group',
      active: true,
    });
    setShowModal(true);
    setError('');
  };

  const handleEdit = (group) => {
    setEditingGroup(group);
    setFormData({
      title: group.title,
      telegram_group_id: group.telegram_group_id,
      payment_plan_id: group.payment_plan_id || '',
      type: group.type,
      active: group.active,
    });
    setShowModal(true);
    setError('');
  };

  const handleDelete = async (id) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja remover este grupo/canal?',
      type: 'warning',
    });

    if (!confirmed) return;

    setLoading(true);
    setError('');
    setSuccess('');

    try {
      await telegramGroupService.delete(id);
      setSuccess('Grupo/Canal removido com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
      loadGroups();
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao remover grupo/canal');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      const data = {
        ...formData,
        bot_id: botId,
        payment_plan_id: formData.payment_plan_id || null,
      };

      if (editingGroup) {
        await telegramGroupService.update(editingGroup.id, data);
        setSuccess('Grupo/Canal atualizado com sucesso!');
      } else {
        await telegramGroupService.create(data);
        setSuccess('Grupo/Canal criado com sucesso!');
      }
      setTimeout(() => setSuccess(''), 3000);
      setShowModal(false);
      loadGroups();
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar grupo/canal');
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateInviteLink = async (id) => {
    try {
      setLoading(true);
      setError('');
      const updatedGroup = await telegramGroupService.updateInviteLink(id);
      
      if (updatedGroup.invite_link) {
        setSuccess('Link de convite obtido e salvo com sucesso!');
        await loadGroups();
        
        try {
          await navigator.clipboard.writeText(updatedGroup.invite_link);
          setSuccess('Link de convite obtido, salvo e copiado para a área de transferência!');
        } catch (copyErr) {
          // Se falhar ao copiar, apenas mostra que foi salvo
        }
        
        setTimeout(() => setSuccess(''), 5000);
      } else {
        setError('Não foi possível obter o link de convite. Verifique se o bot é administrador do grupo/canal.');
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
      setLoading(false);
    }
  };

  const handleCopyLink = async (link, groupId) => {
    try {
      await navigator.clipboard.writeText(link);
      setCopiedLinkId(groupId);
      setSuccess('Link copiado para a área de transferência!');
      setTimeout(() => {
        setCopiedLinkId(null);
        setSuccess('');
      }, 2000);
    } catch (err) {
      setError('Erro ao copiar link. Tente novamente.');
    }
  };

  const generateInviteLink = (group) => {
    if (group.invite_link) {
      return group.invite_link;
    }
    
    const groupId = group.telegram_group_id;
    if (groupId && groupId.startsWith('@')) {
      return `https://t.me/${groupId.substring(1)}`;
    }
    
    return null;
  };

  const content = (
    <>
      <DialogComponent />
      <div className="bot-telegram-groups-page">
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
            <div className="bot-telegram-groups-content">
              <div className="bot-telegram-groups-header">
                <div className="header-text">
                  <h1>Gerencie seus grupos e canais do Telegram</h1>
                  <p>Adicione grupos e canais que serão usados para redirecionar usuários após o pagamento. Você pode vincular grupos e canais a planos de pagamento específicos.</p>
                </div>
                <div className="header-actions">
                  <button onClick={loadGroups} className="btn btn-update" disabled={loading}>
                    <FontAwesomeIcon icon={faSync} />
                    Atualizar
                  </button>
                  <button onClick={handleCreate} className="btn btn-primary">
                    <FontAwesomeIcon icon={faPlus} />
                    Adicionar Grupo/Canal
                  </button>
                </div>
              </div>

              {error && <div className="alert alert-error">{error}</div>}
              {success && <div className="alert alert-success">{success}</div>}

              {/* Groups List */}
              <div className="bot-telegram-groups-section">
                <h2 className="section-title">Grupos e Canais</h2>
                {groups.length === 0 ? (
                  <div className="empty-state">
                    <p>Nenhum grupo ou canal cadastrado ainda.</p>
                    <p>Clique em "Adicionar Grupo/Canal" para começar.</p>
                  </div>
                ) : (
                  <div className="table-wrapper">
                    <table className="bot-telegram-groups-table">
                      <thead>
                        <tr>
                          <th>Título</th>
                          <th>ID do Grupo/Canal</th>
                          <th>Tipo</th>
                          <th>Plano de Pagamento</th>
                          <th>Link de Convite</th>
                          <th>Status</th>
                          <th>Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        {groups.map((group) => {
                          const inviteLink = generateInviteLink(group);
                          return (
                            <tr key={group.id}>
                              <td>{group.title}</td>
                              <td>
                                <code>{group.telegram_group_id}</code>
                              </td>
                              <td>
                                <span className={`badge badge-${group.type === 'group' ? 'primary' : 'info'}`}>
                                  {group.type === 'group' ? 'Grupo' : 'Canal'}
                                </span>
                              </td>
                              <td>
                                {group.payment_plan ? (
                                  <span>{group.payment_plan.title}</span>
                                ) : (
                                  <span className="text-muted">Nenhum</span>
                                )}
                              </td>
                              <td>
                                {inviteLink ? (
                                  <div className="invite-link-container">
                                    <div className="invite-link-actions">
                                      <a 
                                        href={inviteLink} 
                                        target="_blank" 
                                        rel="noopener noreferrer"
                                        className="invite-link"
                                        title={inviteLink}
                                      >
                                        <FontAwesomeIcon icon={faLink} /> Abrir
                                      </a>
                                      <button
                                        className="btn-icon btn-copy"
                                        onClick={() => handleCopyLink(inviteLink, group.id)}
                                        title="Copiar link"
                                      >
                                        <FontAwesomeIcon icon={copiedLinkId === group.id ? faCheck : faCopy} />
                                      </button>
                                    </div>
                                    <div className="invite-link-text" title={inviteLink}>
                                      {inviteLink.length > 40 ? inviteLink.substring(0, 40) + '...' : inviteLink}
                                    </div>
                                  </div>
                                ) : (
                                  <button
                                    className="btn btn-sm btn-secondary"
                                    onClick={() => handleUpdateInviteLink(group.id)}
                                    disabled={loading}
                                    title="Obter e salvar link de convite"
                                  >
                                    <FontAwesomeIcon icon={faSync} /> Obter Link
                                  </button>
                                )}
                              </td>
                              <td>
                                <span className={`badge badge-${group.active ? 'success' : 'secondary'}`}>
                                  {group.active ? 'Ativo' : 'Inativo'}
                                </span>
                              </td>
                              <td>
                                <div className="action-buttons">
                                  <button
                                    onClick={() => handleEdit(group)}
                                    className="btn-icon btn-edit"
                                    title="Editar"
                                  >
                                    <FontAwesomeIcon icon={faEdit} />
                                  </button>
                                  <button
                                    onClick={() => handleDelete(group.id)}
                                    className="btn-icon btn-delete"
                                    title="Remover"
                                    disabled={loading}
                                  >
                                    <FontAwesomeIcon icon={faTrash} />
                                  </button>
                                </div>
                              </td>
                            </tr>
                          );
                        })}
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
                    <h2>{editingGroup ? 'Editar Grupo/Canal' : 'Adicionar Novo Grupo/Canal'}</h2>
                    <button className="modal-close" onClick={() => setShowModal(false)}>
                      ×
                    </button>
                  </div>
                  <form className="modal-form" onSubmit={handleSubmit}>
                    <div className="form-group">
                      <label>
                        Título <span className="required">*</span>
                      </label>
                      <input
                        type="text"
                        name="title"
                        value={formData.title}
                        onChange={handleChange}
                        required
                        placeholder="Nome do grupo ou canal"
                      />
                    </div>

                    <div className="form-group">
                      <label>
                        ID do Grupo/Canal <span className="required">*</span>
                      </label>
                      <input
                        type="text"
                        name="telegram_group_id"
                        value={formData.telegram_group_id}
                        onChange={handleChange}
                        required
                        placeholder="@username ou -1001234567890"
                        disabled={!!editingGroup}
                      />
                      <small>Use @username para canais públicos ou o ID numérico do grupo/canal</small>
                    </div>

                    <div className="form-group">
                      <label>
                        Tipo <span className="required">*</span>
                      </label>
                      <select
                        name="type"
                        value={formData.type}
                        onChange={handleChange}
                        required
                      >
                        <option value="group">Grupo</option>
                        <option value="channel">Canal</option>
                      </select>
                    </div>

                    <div className="form-group">
                      <label>Plano de Pagamento</label>
                      <select
                        name="payment_plan_id"
                        value={formData.payment_plan_id}
                        onChange={handleChange}
                      >
                        <option value="">Nenhum</option>
                        {paymentPlans.map((plan) => (
                          <option key={plan.id} value={plan.id}>
                            {plan.title} - R$ {parseFloat(plan.price).toFixed(2)}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className="form-group">
                      <label className="checkbox-label">
                        <input
                          type="checkbox"
                          name="active"
                          checked={formData.active}
                          onChange={handleChange}
                        />
                        <span>Ativo</span>
                      </label>
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
                        {loading ? 'Salvando...' : editingGroup ? 'Atualizar' : 'Criar'}
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

export default BotTelegramGroups;
