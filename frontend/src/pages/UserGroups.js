import React, { useState, useEffect, useContext } from 'react';
import { AuthContext } from '../contexts/AuthContext';
import Layout from '../components/Layout';
import userGroupService from '../services/userGroupService';
import useConfirm from '../hooks/useConfirm';
import RefreshButton from '../components/RefreshButton';
import './UserGroups.css';

const UserGroups = () => {
  const { confirm, DialogComponent } = useConfirm();
  const [groups, setGroups] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingGroup, setEditingGroup] = useState(null);
  const [availableMenus, setAvailableMenus] = useState([]);
  const [availableBots, setAvailableBots] = useState([]);
  const { user: currentUser } = useContext(AuthContext);

  const [formData, setFormData] = useState({
    name: '',
    description: '',
    active: true,
    menu_permissions: [],
    bot_permissions: []
  });

  useEffect(() => {
    loadGroups();
    loadAvailableMenus();
    loadAvailableBots();
  }, []);

  const loadGroups = async () => {
    try {
      setLoading(true);
      const data = await userGroupService.getAllGroups();
      setGroups(data);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar grupos');
    } finally {
      setLoading(false);
    }
  };

  const loadAvailableMenus = async () => {
    try {
      const menus = await userGroupService.getAvailableMenus();
      setAvailableMenus(menus);
    } catch (err) {
      console.error('Erro ao carregar menus:', err);
    }
  };

  const loadAvailableBots = async () => {
    try {
      const bots = await userGroupService.getAvailableBots();
      setAvailableBots(bots);
    } catch (err) {
      console.error('Erro ao carregar bots:', err);
    }
  };

  const handleCreate = () => {
    setEditingGroup(null);
    setFormData({
      name: '',
      description: '',
      active: true,
      menu_permissions: [],
      bot_permissions: []
    });
    setIsModalOpen(true);
  };

  const handleEdit = (group) => {
    setEditingGroup(group);
    
    // Extrai permissões de menus
    const menuPerms = group.permissions
      ?.filter(p => p.resource_type === 'menu')
      .map(p => p.resource_id) || [];

    // Extrai permissões de bots
    const botPermsMap = {};
    group.permissions
      ?.filter(p => p.resource_type === 'bot')
      .forEach(p => {
        if (!botPermsMap[p.resource_id]) {
          botPermsMap[p.resource_id] = [];
        }
        botPermsMap[p.resource_id].push(p.permission);
      });

    const botPerms = Object.keys(botPermsMap).map(botId => ({
      bot_id: parseInt(botId),
      permissions: botPermsMap[botId]
    }));

    setFormData({
      name: group.name || '',
      description: group.description || '',
      active: group.active !== undefined ? group.active : true,
      menu_permissions: menuPerms,
      bot_permissions: botPerms
    });
    setIsModalOpen(true);
  };

  const handleDelete = async (id) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja excluir este grupo? Usuários associados a este grupo perderão suas permissões.',
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      await userGroupService.deleteGroup(id);
      setSuccess('Grupo excluído com sucesso!');
      loadGroups();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao excluir grupo');
      setTimeout(() => setError(''), 3000);
    }
  };

  const handleModalClose = () => {
    setIsModalOpen(false);
    setEditingGroup(null);
    setFormData({
      name: '',
      description: '',
      active: true,
      menu_permissions: [],
      bot_permissions: []
    });
  };

  const handleMenuToggle = (menu) => {
    setFormData(prev => {
      const menus = prev.menu_permissions.includes(menu)
        ? prev.menu_permissions.filter(m => m !== menu)
        : [...prev.menu_permissions, menu];
      return { ...prev, menu_permissions: menus };
    });
  };

  const handleBotPermissionToggle = (botId, permission) => {
    setFormData(prev => {
      const botPerms = [...prev.bot_permissions];
      const botIndex = botPerms.findIndex(bp => bp.bot_id === botId);
      
      if (botIndex === -1) {
        botPerms.push({ bot_id: botId, permissions: [permission] });
      } else {
        const permissions = botPerms[botIndex].permissions;
        if (permissions.includes(permission)) {
          botPerms[botIndex].permissions = permissions.filter(p => p !== permission);
          // Remove bot se não tiver mais permissões
          if (botPerms[botIndex].permissions.length === 0) {
            botPerms.splice(botIndex, 1);
          }
        } else {
          botPerms[botIndex].permissions = [...permissions, permission];
        }
      }
      
      return { ...prev, bot_permissions: botPerms };
    });
  };

  const getBotPermissions = (botId) => {
    const botPerm = formData.bot_permissions.find(bp => bp.bot_id === botId);
    return botPerm ? botPerm.permissions : [];
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    try {
      if (editingGroup) {
        await userGroupService.updateGroup(editingGroup.id, formData);
        setSuccess('Grupo atualizado com sucesso!');
      } else {
        await userGroupService.createGroup(formData);
        setSuccess('Grupo criado com sucesso!');
      }
      
      loadGroups();
      handleModalClose();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.errors || 'Erro ao salvar grupo');
      setTimeout(() => setError(''), 5000);
    }
  };

  const getMenuLabel = (menu) => {
    const labels = {
      dashboard: 'Dashboard',
      billing: 'Faturamento',
      bot: 'Bot',
      results: 'Resultados',
      marketing: 'Marketing',
      settings: 'Configurações'
    };
    return labels[menu] || menu;
  };

  const getPermissionLabel = (permission) => {
    const labels = {
      read: 'Leitura',
      write: 'Escrita',
      delete: 'Exclusão'
    };
    return labels[permission] || permission;
  };

  // Verifica se é admin (super admin ou admin comum)
  const isAdmin = currentUser?.role === 'admin' || currentUser?.user_type === 'admin' || currentUser?.user_type === 'super_admin';

  if (!isAdmin) {
    return (
      <Layout>
        <div className="user-groups-page">
          <div className="access-denied">
            <h1>Acesso Negado</h1>
            <p>Apenas administradores podem gerenciar grupos de usuários.</p>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <DialogComponent />
      <div className="user-groups-page">
        <div className="user-groups-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h1>Grupos de Usuários</h1>
          <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
            <RefreshButton onRefresh={() => Promise.all([loadGroups(), loadAvailableMenus(), loadAvailableBots()])} loading={loading} className="compact" />
            <button className="btn btn-primary" onClick={handleCreate}>
              + Novo Grupo
            </button>
          </div>
        </div>

        {error && <div className="alert alert-error">{error}</div>}
        {success && <div className="alert alert-success">{success}</div>}

        {loading ? (
          <div className="loading">Carregando grupos...</div>
        ) : (
          <div className="groups-table-container">
            {groups.length === 0 ? (
              <div className="empty-state">
                <p>Nenhum grupo cadastrado. Clique em "Novo Grupo" para criar o primeiro.</p>
              </div>
            ) : (
              <table className="groups-table">
                <thead>
                  <tr>
                    <th>Nome</th>
                    <th>Descrição</th>
                    <th>Usuários</th>
                    <th>Menus</th>
                    <th>Bots</th>
                    <th>Status</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {groups.map(group => (
                    <tr key={group.id}>
                      <td>{group.name}</td>
                      <td>{group.description || '-'}</td>
                      <td>{group.users?.length || 0}</td>
                      <td>
                        {group.permissions?.filter(p => p.resource_type === 'menu').length || 0}
                      </td>
                      <td>
                        {new Set(group.permissions?.filter(p => p.resource_type === 'bot').map(p => p.resource_id)).size || 0}
                      </td>
                      <td>
                        <span className={`status-badge ${group.active ? 'active' : 'inactive'}`}>
                          {group.active ? 'Ativo' : 'Inativo'}
                        </span>
                      </td>
                      <td>
                        <div className="actions">
                          <button
                            className="btn btn-sm btn-secondary"
                            onClick={() => handleEdit(group)}
                          >
                            Editar
                          </button>
                          <button
                            className="btn btn-sm btn-danger"
                            onClick={() => handleDelete(group.id)}
                            disabled={group.users?.length > 0}
                            title={group.users?.length > 0 ? 'Não é possível excluir grupo com usuários' : ''}
                          >
                            Excluir
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}

        {/* Modal de Criar/Editar Grupo */}
        {isModalOpen && (
          <div className="modal-overlay" onClick={handleModalClose}>
            <div className="modal-content large" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>{editingGroup ? 'Editar Grupo' : 'Novo Grupo'}</h2>
                <button className="modal-close" onClick={handleModalClose}>×</button>
              </div>

              <form onSubmit={handleSubmit}>
                <div className="form-section">
                  <h3>Informações Básicas</h3>
                  
                  <div className="form-group">
                    <label htmlFor="name">Nome do Grupo *</label>
                    <input
                      type="text"
                      id="name"
                      name="name"
                      value={formData.name}
                      onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                      required
                      placeholder="Ex: Equipe de Vendas"
                    />
                  </div>

                  <div className="form-group">
                    <label htmlFor="description">Descrição</label>
                    <textarea
                      id="description"
                      name="description"
                      value={formData.description}
                      onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                      rows="3"
                      placeholder="Descrição do grupo..."
                    />
                  </div>

                  <div className="form-group checkbox-group">
                    <label>
                      <input
                        type="checkbox"
                        name="active"
                        checked={formData.active}
                        onChange={(e) => setFormData({ ...formData, active: e.target.checked })}
                      />
                      Grupo Ativo
                    </label>
                  </div>
                </div>

                <div className="form-section">
                  <h3>Permissões de Menus</h3>
                  <p className="section-description">Selecione os menus que este grupo pode acessar:</p>
                  
                  <div className="permissions-grid">
                    {availableMenus.map(menu => (
                      <label key={menu} className="permission-item">
                        <input
                          type="checkbox"
                          checked={formData.menu_permissions.includes(menu)}
                          onChange={() => handleMenuToggle(menu)}
                        />
                        <span>{getMenuLabel(menu)}</span>
                      </label>
                    ))}
                  </div>
                </div>

                <div className="form-section">
                  <h3>Permissões de Bots</h3>
                  <p className="section-description">Selecione os bots e as permissões que este grupo pode ter:</p>
                  
                  {availableBots.length === 0 ? (
                    <p className="no-bots">Nenhum bot disponível</p>
                  ) : (
                    <div className="bots-permissions">
                      {availableBots.map(bot => (
                        <div key={bot.id} className="bot-permission-card">
                          <div className="bot-name">{bot.name}</div>
                          <div className="bot-permissions">
                            {['read', 'write', 'delete'].map(permission => (
                              <label key={permission} className="permission-checkbox">
                                <input
                                  type="checkbox"
                                  checked={getBotPermissions(bot.id).includes(permission)}
                                  onChange={() => handleBotPermissionToggle(bot.id, permission)}
                                />
                                <span>{getPermissionLabel(permission)}</span>
                              </label>
                            ))}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {error && <div className="error">{error}</div>}

                <div className="modal-actions">
                  <button
                    type="button"
                    onClick={handleModalClose}
                    className="btn btn-secondary"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    className="btn btn-primary"
                  >
                    {editingGroup ? 'Atualizar' : 'Criar'} Grupo
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
};

export default UserGroups;

