import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEdit, faTrash, faPlus, faLink, faRefresh, faCopy, faCheck } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import telegramGroupService from '../services/telegramGroupService';
import paymentPlanService from '../services/paymentPlanService';
import useConfirm from '../hooks/useConfirm';
import RefreshButton from '../components/RefreshButton';
import './TelegramGroups.css';

const TelegramGroups = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const { botId } = useParams();
  
  const [groups, setGroups] = useState([]);
  const [paymentPlans, setPaymentPlans] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editingGroup, setEditingGroup] = useState(null);
  const [formData, setFormData] = useState({
    title: '',
    telegram_group_id: '',
    payment_plan_id: '',
    type: 'group',
    active: true,
  });
  const [copiedLinkId, setCopiedLinkId] = useState(null);

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

  const handleRefresh = async () => {
    if (!botId) return;
    await Promise.all([
      loadGroups(),
      loadPaymentPlans()
    ]);
  };

  const handleOpenModal = (group = null) => {
    if (group) {
      setEditingGroup(group);
      setFormData({
        title: group.title,
        telegram_group_id: group.telegram_group_id,
        payment_plan_id: group.payment_plan_id || '',
        type: group.type,
        active: group.active,
      });
    } else {
      setEditingGroup(null);
      setFormData({
        title: '',
        telegram_group_id: '',
        payment_plan_id: '',
        type: 'group',
        active: true,
      });
    }
    setShowModal(true);
    setError('');
    setSuccess('');
  };

  const handleCloseModal = () => {
    setShowModal(false);
    setEditingGroup(null);
    setFormData({
      title: '',
      telegram_group_id: '',
      payment_plan_id: '',
      type: 'group',
      active: true,
    });
    setError('');
    setSuccess('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    setSuccess('');

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

      await loadGroups();
      setTimeout(() => {
        handleCloseModal();
        setSuccess('');
      }, 1500);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar grupo/canal');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (id) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja excluir este grupo/canal?',
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      setLoading(true);
      await telegramGroupService.delete(id);
      setSuccess('Grupo/Canal excluído com sucesso!');
      await loadGroups();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao excluir grupo/canal');
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
        // Recarrega a lista para mostrar o link atualizado
        await loadGroups();
        
        // Copia automaticamente para a área de transferência
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
      // Tenta obter mensagem de erro detalhada
      const errorMessage = err.response?.data?.error || 
                          err.response?.data?.message || 
                          'Erro ao atualizar link de convite.';
      
      // Adiciona detalhes se disponíveis
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
      console.error('Erro ao atualizar link de convite:', err.response?.data || err);
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
    
    // Tenta gerar link baseado no ID
    const groupId = group.telegram_group_id;
    if (groupId.startsWith('@')) {
      return `https://t.me/${groupId.substring(1)}`;
    }
    
    return null;
  };

  if (!botId) {
    return (
      <Layout>
        <DialogComponent />
        <div className="telegram-groups-page">
          <div className="error-message">
            <h2>Bot não selecionado</h2>
            <p>Por favor, selecione um bot primeiro.</p>
            <button className="btn btn-primary" onClick={() => navigate('/')}>
              Voltar ao Dashboard
            </button>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="telegram-groups-page">
        <div className="telegram-groups-content">
          <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h1>Grupos e Canais</h1>
          <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
            <RefreshButton onRefresh={handleRefresh} loading={loadingData} className="compact" />
            <button className="btn btn-primary" onClick={() => handleOpenModal()}>
              <FontAwesomeIcon icon={faPlus} /> Adicionar Grupo/Canal
            </button>
          </div>
        </div>

        {error && (
          <div className="alert alert-error">
            {error}
            <button onClick={() => setError('')}>×</button>
          </div>
        )}

        {success && (
          <div className="alert alert-success">
            {success}
            <button onClick={() => setSuccess('')}>×</button>
          </div>
        )}

        {loadingData ? (
          <div className="loading">Carregando...</div>
        ) : groups.length === 0 ? (
          <div className="empty-state">
            <p>Nenhum grupo ou canal cadastrado.</p>
            <button className="btn btn-primary" onClick={() => handleOpenModal()}>
              Adicionar primeiro grupo/canal
            </button>
          </div>
        ) : (
          <div className="groups-table-container">
            <table className="groups-table">
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
                              className="btn btn-sm btn-secondary copy-link-btn"
                              onClick={() => handleCopyLink(inviteLink, group.id)}
                              title="Copiar link"
                            >
                              <FontAwesomeIcon icon={copiedLinkId === group.id ? faCheck : faCopy} />
                            </button>
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
                            <FontAwesomeIcon icon={faRefresh} /> Obter Link
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
                            className="btn btn-sm btn-primary"
                            onClick={() => handleOpenModal(group)}
                            title="Editar"
                          >
                            <FontAwesomeIcon icon={faEdit} />
                          </button>
                          <button
                            className="btn btn-sm btn-danger"
                            onClick={() => handleDelete(group.id)}
                            disabled={loading}
                            title="Excluir"
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

        {/* Modal */}
        {showModal && (
          <div className="modal-overlay" onClick={handleCloseModal}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>{editingGroup ? 'Editar' : 'Adicionar'} Grupo/Canal</h2>
                <button className="modal-close" onClick={handleCloseModal}>×</button>
              </div>
              <form onSubmit={handleSubmit}>
                <div className="form-group">
                  <label>Título *</label>
                  <input
                    type="text"
                    value={formData.title}
                    onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                    required
                    placeholder="Nome do grupo ou canal"
                  />
                </div>

                <div className="form-group">
                  <label>ID do Grupo/Canal *</label>
                  <input
                    type="text"
                    value={formData.telegram_group_id}
                    onChange={(e) => setFormData({ ...formData, telegram_group_id: e.target.value })}
                    required
                    placeholder="@username ou -1001234567890"
                    disabled={!!editingGroup}
                  />
                  <small>Use @username para canais públicos ou o ID numérico do grupo/canal</small>
                </div>

                <div className="form-group">
                  <label>Tipo *</label>
                  <select
                    value={formData.type}
                    onChange={(e) => setFormData({ ...formData, type: e.target.value })}
                    required
                  >
                    <option value="group">Grupo</option>
                    <option value="channel">Canal</option>
                  </select>
                </div>

                <div className="form-group">
                  <label>Plano de Pagamento</label>
                  <select
                    value={formData.payment_plan_id}
                    onChange={(e) => setFormData({ ...formData, payment_plan_id: e.target.value })}
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
                  <label>
                    <input
                      type="checkbox"
                      checked={formData.active}
                      onChange={(e) => setFormData({ ...formData, active: e.target.checked })}
                    />
                    {' '}Ativo
                  </label>
                </div>

                {error && <div className="alert alert-error">{error}</div>}

                <div className="modal-footer">
                  <button type="button" className="btn btn-secondary" onClick={handleCloseModal}>
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
        </div>
      </div>
    </Layout>
  );
};

export default TelegramGroups;

