import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Layout from '../components/Layout';
import contactService from '../services/contactService';
import botService from '../services/botService';
import groupManagementService from '../services/groupManagementService';
import useConfirm from '../hooks/useConfirm';
import './Contacts.css';

const Contacts = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const [botId, setBotId] = useState(null);
  const [bots, setBots] = useState([]);
  const [contacts, setContacts] = useState([]);
  const [stats, setStats] = useState({ active_count: 0, inactive_count: 0, total_count: 0 });
  const [latestContacts, setLatestContacts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedBot, setSelectedBot] = useState('');
  const [pagination, setPagination] = useState({ page: 1, limit: 10, total: 0, totalPages: 0 });
  const [managingMember, setManagingMember] = useState(null);
  const [memberStatuses, setMemberStatuses] = useState({});
  const [showMemberModal, setShowMemberModal] = useState(false);
  const [selectedContact, setSelectedContact] = useState(null);
  const [memberAction, setMemberAction] = useState(null); // 'add' or 'remove'
  const [memberReason, setMemberReason] = useState('');
  const [syncingMembers, setSyncingMembers] = useState(false);

  useEffect(() => {
    loadBots();
    const storedBotId = localStorage.getItem('selectedBotId');
    if (storedBotId) {
      setBotId(storedBotId);
      setSelectedBot(storedBotId);
      loadContacts(storedBotId);
      loadStats(storedBotId);
      loadLatestContacts(storedBotId);
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const loadBots = async () => {
    try {
      const data = await botService.getAllBots();
      setBots(data);
    } catch (err) {
      console.error('Error loading bots:', err);
    }
  };

  const loadContacts = async (id, page = 1) => {
    try {
      setLoading(true);
      const filters = {
        search: searchTerm || undefined
      };
      const result = await contactService.getAllContacts(id, filters, { page, limit: 10 });
      setContacts(result.contacts);
      setPagination(result.pagination);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar contatos');
      setContacts([]);
    } finally {
      setLoading(false);
    }
  };

  const loadStats = async (id) => {
    try {
      const statsData = await contactService.getStats(id);
      setStats(statsData);
    } catch (err) {
      console.error('Error loading stats:', err);
    }
  };

  const loadLatestContacts = async (id) => {
    try {
      const latest = await contactService.getLatest(id, 10);
      setLatestContacts(latest);
    } catch (err) {
      console.error('Error loading latest contacts:', err);
    }
  };

  const handleBotChange = (e) => {
    const newBotId = e.target.value;
    if (newBotId) {
      setSelectedBot(newBotId);
      setBotId(newBotId);
      localStorage.setItem('selectedBotId', newBotId);
      setPagination({ ...pagination, page: 1 });
      loadContacts(newBotId, 1);
      loadStats(newBotId);
      loadLatestContacts(newBotId);
    }
  };

  const handleSearch = () => {
    if (botId) {
      loadContacts(botId, 1);
    }
  };

  const handlePageChange = (newPage) => {
    if (botId && newPage >= 1 && newPage <= pagination.totalPages) {
      loadContacts(botId, newPage);
    }
  };

  const handleBlock = async (contactId) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja bloquear este contato?',
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      await contactService.blockContact(contactId, botId);
      setSuccess('Contato bloqueado com sucesso!');
      loadContacts(botId, pagination.page);
      loadStats(botId);
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao bloquear contato');
    }
  };

  const checkMemberStatus = async (contactId) => {
    if (!botId) return;
    
    try {
      setManagingMember(contactId);
      const result = await groupManagementService.checkMemberStatus(botId, contactId);
      setMemberStatuses(prev => ({
        ...prev,
        [contactId]: result
      }));
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao verificar status do membro');
    } finally {
      setManagingMember(null);
    }
  };

  const handleManageMember = (contact, action) => {
    setSelectedContact(contact);
    setMemberAction(action);
    setMemberReason('');
    setShowMemberModal(true);
  };

  const confirmManageMember = async () => {
    if (!selectedContact || !botId) return;

    try {
      setManagingMember(selectedContact.id);
      let result;
      
      if (memberAction === 'add') {
        result = await groupManagementService.addMember(botId, selectedContact.id, memberReason || null);
      } else {
        result = await groupManagementService.removeMember(botId, selectedContact.id, memberReason || null);
      }

      setSuccess(result.message || `Membro ${memberAction === 'add' ? 'adicionado' : 'removido'} com sucesso!`);
      setShowMemberModal(false);
      setSelectedContact(null);
      setMemberAction(null);
      setMemberReason('');
      
      // Atualiza status do membro
      await checkMemberStatus(selectedContact.id);
      
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || `Erro ao ${memberAction === 'add' ? 'adicionar' : 'remover'} membro`);
    } finally {
      setManagingMember(null);
    }
  };

  const loadContactHistory = async (contactId) => {
    if (!botId) return;
    
    try {
      await groupManagementService.getContactHistory(botId, contactId);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar histórico');
    }
  };

  const handleExport = () => {
    // TODO: Implementar exportação
    setSuccess('Exportação iniciada...');
    setTimeout(() => setSuccess(''), 3000);
  };

  const handleSyncGroupMembers = async () => {
    if (!botId) {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      return;
    }

    const confirmed = await confirm({
      message: 'Deseja sincronizar os membros do grupo? Isso irá buscar os administradores do grupo e salvá-los como contatos.',
      type: 'info',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      setSyncingMembers(true);
      setError('');
      const result = await contactService.syncGroupMembers(botId);
      
      if (result.success) {
        setSuccess(result.message || `${result.synced_count} membro(s) sincronizado(s) com sucesso!`);
        // Recarrega contatos, estatísticas e últimos contatos
        await loadContacts(botId, pagination.page);
        await loadStats(botId);
        await loadLatestContacts(botId);
        setTimeout(() => setSuccess(''), 5000);
      } else {
        setError(result.error || 'Erro ao sincronizar membros');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao sincronizar membros do grupo');
    } finally {
      setSyncingMembers(false);
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
  };

  const getInitials = (contact) => {
    if (!contact) return '?';
    const firstName = contact.first_name || '';
    const lastName = contact.last_name || '';
    if (firstName && lastName) {
      return (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();
    }
    if (firstName) {
      return firstName.charAt(0).toUpperCase();
    }
    if (contact.username) {
      return contact.username.charAt(0).toUpperCase();
    }
    return '?';
  };

  const getContactName = (contact) => {
    if (!contact) return 'N/A';
    const parts = [contact.first_name, contact.last_name].filter(Boolean);
    if (parts.length > 0) {
      return parts.join(' ');
    }
    if (contact.username) {
      return `@${contact.username}`;
    }
    return 'Sem nome';
  };

  // Calculate chart percentages
  const activePercentage = stats.total_count > 0 
    ? (stats.active_count / stats.total_count) * 100 
    : 0;
  const inactivePercentage = stats.total_count > 0 
    ? (stats.inactive_count / stats.total_count) * 100 
    : 0;

  // Generate SVG path for donut chart
  const radius = 40;
  const circumference = 2 * Math.PI * radius;
  const activeOffset = circumference - (activePercentage / 100) * circumference;
  const inactiveOffset = circumference - (inactivePercentage / 100) * circumference;

  if (loading && !botId) {
    return (
      <Layout>
        <div className="contacts-page">
          <div className="loading-container">Carregando...</div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <DialogComponent />
      <div className="contacts-page">
        <div className="contacts-main">
          <div className="contacts-content">
            {/* Search and Filter Section */}
            <div className="contacts-filters">
              <div className="search-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <circle cx="11" cy="11" r="8"></circle>
                  <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input
                  type="text"
                  placeholder="Pesquisar"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                />
                <span className="search-label">Procure um membro por id</span>
              </div>
              
              <div className="filter-box">
                <label>Selecionar por bot</label>
                <select value={selectedBot} onChange={handleBotChange}>
                  <option value="">Selecione um bot</option>
                  {bots.map((bot) => (
                    <option key={bot.id} value={bot.id}>
                      {bot.name}
                    </option>
                  ))}
                </select>
              </div>

              <button onClick={handleSearch} className="btn-apply">
                Aplicar
              </button>

              <button 
                onClick={handleSyncGroupMembers} 
                className="btn-sync-members"
                disabled={!botId || syncingMembers}
                title="Sincronizar membros do grupo do Telegram"
              >
                {syncingMembers ? 'Sincronizando...' : '🔄 Sincronizar Membros'}
              </button>
            </div>

            {error && <div className="alert alert-error">{error}</div>}
            {success && <div className="alert alert-success">{success}</div>}

            {/* Contacts Table */}
            <div className="contacts-table-container">
              <table className="contacts-table">
                <thead>
                  <tr>
                    <th>Nome</th>
                    <th>ID</th>
                    <th>E-mail</th>
                    <th>Telefone</th>
                    <th>Expira em</th>
                    <th>Telegram</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr>
                      <td colSpan="7" className="loading-cell">Carregando contatos...</td>
                    </tr>
                  ) : contacts.length === 0 ? (
                    <tr>
                      <td colSpan="7" className="empty-cell">Nenhum contato encontrado</td>
                    </tr>
                  ) : (
                    contacts.map((contact) => (
                      <tr key={contact.id}>
                        <td>
                          <div className="contact-name-cell">
                            <div className={`contact-avatar ${contact.is_bot ? 'bot-avatar' : ''}`}>
                              {getInitials(contact)}
                            </div>
                            <div className="contact-name-info">
                              <span>{getContactName(contact)}</span>
                              {contact.is_bot && (
                                <span className="bot-badge" title="Bot">🤖 Bot</span>
                              )}
                            </div>
                          </div>
                        </td>
                        <td>{contact.telegram_id}</td>
                        <td>{contact.email || 'N/A'}</td>
                        <td>{contact.phone || 'N/A'}</td>
                        <td>{formatDate(contact.expires_at)}</td>
                        <td>
                          <span className={`telegram-status ${contact.telegram_status || 'inactive'}`}>
                            {contact.telegram_status === 'active' ? 'Ativo' : 'Inativo'}
                          </span>
                        </td>
                        <td>
                          <div className="action-buttons">
                            <button
                              onClick={() => navigate(`/results/contacts/${contact.id}?botId=${botId}`)}
                              className="btn-details"
                              title="Detalhes"
                            >
                              Detalhes
                            </button>
                            {memberStatuses[contact.id] && (
                              <span className={`member-status-badge ${memberStatuses[contact.id].is_member ? 'member' : 'not-member'}`}>
                                {memberStatuses[contact.id].is_member ? '✓ No grupo' : '✗ Fora'}
                              </span>
                            )}
                            {managingMember === contact.id ? (
                              <span className="loading-spinner">...</span>
                            ) : (
                              <>
                            {!contact.is_bot && (
                              <>
                                <button
                                  onClick={() => checkMemberStatus(contact.id)}
                                  className="btn-check-status"
                                  title="Verificar status no grupo"
                                >
                                  Verificar
                                </button>
                                <button
                                  onClick={() => loadContactHistory(contact.id)}
                                  className="btn-history"
                                  title="Ver histórico de ações"
                                >
                                  Histórico
                                </button>
                                <button
                                  onClick={() => handleManageMember(contact, 'add')}
                                  className="btn-add-member"
                                  title="Adicionar ao grupo"
                                  disabled={!botId}
                                >
                                  + Grupo
                                </button>
                                <button
                                  onClick={() => handleManageMember(contact, 'remove')}
                                  className="btn-remove-member"
                                  title="Remover do grupo"
                                  disabled={!botId}
                                >
                                  - Grupo
                                </button>
                              </>
                            )}
                            {contact.is_bot && (
                              <span className="bot-indicator" title="Este é um bot - ações de grupo não disponíveis">
                                Bot
                              </span>
                            )}
                              </>
                            )}
                            {!contact.is_bot && (
                              <button
                                onClick={() => handleBlock(contact.id)}
                                className="btn-block"
                                title="Bloquear"
                              >
                                Bloquear
                              </button>
                            )}
                            {contact.is_bot && (
                              <span className="bot-indicator" title="Este é um bot">Bot</span>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination and Export */}
            <div className="contacts-footer">
              <div className="contacts-info">
                <span>Total de usuários: {pagination.total || 0}</span>
                <div className="pagination">
                  <button
                    onClick={() => handlePageChange(pagination.page - 1)}
                    disabled={pagination.page <= 1}
                    className="pagination-btn"
                  >
                    &lt;
                  </button>
                  <span>{pagination.page}/{pagination.totalPages || 1}</span>
                  <button
                    onClick={() => handlePageChange(pagination.page + 1)}
                    disabled={pagination.page >= pagination.totalPages}
                    className="pagination-btn"
                  >
                    &gt;
                  </button>
                </div>
              </div>
              <button onClick={handleExport} className="btn-export">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="7 10 12 15 17 10"></polyline>
                  <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Exportar relatório
              </button>
            </div>
          </div>

          {/* Right Sidebar */}
          <div className="contacts-sidebar">
            {/* Stats Card */}
            <div className="stats-card">
              <h3>Seus resultados</h3>
              <div className="donut-chart">
                <svg width="120" height="120" viewBox="0 0 120 120">
                  <circle
                    cx="60"
                    cy="60"
                    r={radius}
                    fill="none"
                    stroke="#e5e7eb"
                    strokeWidth="12"
                  />
                  <circle
                    cx="60"
                    cy="60"
                    r={radius}
                    fill="none"
                    stroke="#ec4899"
                    strokeWidth="12"
                    strokeDasharray={circumference}
                    strokeDashoffset={inactiveOffset}
                    strokeLinecap="round"
                    transform="rotate(-90 60 60)"
                    opacity={activePercentage > 0 ? 1 : 0}
                  />
                  <circle
                    cx="60"
                    cy="60"
                    r={radius}
                    fill="none"
                    stroke="#9333ea"
                    strokeWidth="12"
                    strokeDasharray={circumference}
                    strokeDashoffset={activeOffset}
                    strokeLinecap="round"
                    transform="rotate(-90 60 60)"
                  />
                </svg>
                <div className="chart-center">
                  <span className="chart-percentage">
                    {activePercentage > 0 ? Math.round(activePercentage) : 0}%
                  </span>
                </div>
              </div>
              <div className="chart-legend">
                <div className="legend-item">
                  <span className="legend-dot active-dot"></span>
                  <span>Membros ativos ({stats.active_count || 0})</span>
                </div>
                <div className="legend-item">
                  <span className="legend-dot inactive-dot"></span>
                  <span>Membros inativos ({stats.inactive_count || 0})</span>
                </div>
              </div>
            </div>

            {/* Latest Members Card */}
            <div className="latest-members-card">
              <h3>Seus últimos membros</h3>
              <div className="latest-members-list">
                {latestContacts.map((contact) => (
                  <div key={contact.id} className="latest-member-item">
                    <div className={`member-avatar ${contact.is_bot ? 'bot-avatar' : ''}`}>
                      {getInitials(contact)}
                    </div>
                    <div className="member-info">
                      <div className="member-name-row">
                        <span className="member-name">{getContactName(contact)}</span>
                        {contact.is_bot && (
                          <span className="bot-badge-small" title="Bot">🤖</span>
                        )}
                      </div>
                      <span className="member-date">{formatDate(contact.created_at)}</span>
                    </div>
                  </div>
                ))}
                {latestContacts.length === 0 && (
                  <div className="empty-latest">Nenhum membro recente</div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Modal de Gerenciamento de Membro */}
        {showMemberModal && selectedContact && (
          <div className="modal-overlay" onClick={() => setShowMemberModal(false)}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>
                  {memberAction === 'add' ? 'Adicionar ao Grupo' : 'Remover do Grupo'}
                </h2>
                <button className="modal-close" onClick={() => setShowMemberModal(false)}>
                  ×
                </button>
              </div>
              <div className="modal-body">
                <p>
                  {memberAction === 'add' 
                    ? `Deseja adicionar ${selectedContact.first_name || selectedContact.username || 'este contato'} ao grupo?`
                    : `Deseja remover ${selectedContact.first_name || selectedContact.username || 'este contato'} do grupo?`
                  }
                </p>
                <div className="form-group">
                  <label htmlFor="memberReason">Motivo (opcional):</label>
                  <textarea
                    id="memberReason"
                    value={memberReason}
                    onChange={(e) => setMemberReason(e.target.value)}
                    placeholder="Digite o motivo da ação..."
                    rows="3"
                    maxLength="500"
                  />
                </div>
              </div>
              <div className="modal-footer">
                <button
                  className="btn-cancel"
                  onClick={() => setShowMemberModal(false)}
                  disabled={managingMember === selectedContact.id}
                >
                  Cancelar
                </button>
                <button
                  className={`btn-confirm ${memberAction === 'add' ? 'btn-add' : 'btn-remove'}`}
                  onClick={confirmManageMember}
                  disabled={managingMember === selectedContact.id}
                >
                  {managingMember === selectedContact.id 
                    ? 'Processando...' 
                    : (memberAction === 'add' ? 'Adicionar' : 'Remover')
                  }
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
};

export default Contacts;

