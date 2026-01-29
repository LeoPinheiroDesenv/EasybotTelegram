import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import Layout from '../components/Layout';
import contactService from '../services/contactService';
import botService from '../services/botService';
import groupManagementService from '../services/groupManagementService';
import useConfirm from '../hooks/useConfirm';
import MoonLoader from "react-spinners/MoonLoader";
import './ContactDetails.css';

const ContactDetails = () => {
  const { confirm, DialogComponent } = useConfirm();
  const { id } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const botId = searchParams.get('botId') || localStorage.getItem('selectedBotId');
  
  const [contact, setContact] = useState(null);
  const [bot, setBot] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [memberStatus, setMemberStatus] = useState(null);
  const [loadingMemberStatus, setLoadingMemberStatus] = useState(false);
  const [contactHistory, setContactHistory] = useState(null);
  const [loadingHistory, setLoadingHistory] = useState(false);

  useEffect(() => {
    if (id && botId) {
      loadContact();
      loadBot();
    } else {
      setError('ID do contato ou bot não fornecido');
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id, botId]);

  const loadContact = async () => {
    try {
      setLoading(true);
      setError('');
      const contactData = await contactService.getContactById(id, botId);
      setContact(contactData);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar contato');
      console.error('Error loading contact:', err);
    } finally {
      setLoading(false);
    }
  };

  const loadBot = async () => {
    try {
      const botData = await botService.getBotById(botId);
      setBot(botData);
    } catch (err) {
      console.error('Error loading bot:', err);
    }
  };

  const loadMemberStatus = async () => {
    if (!botId) return;
    
    try {
      setLoadingMemberStatus(true);
      const result = await groupManagementService.checkMemberStatus(botId, id);
      setMemberStatus(result);
    } catch (err) {
      console.error('Error loading member status:', err);
    } finally {
      setLoadingMemberStatus(false);
    }
  };

  const loadContactHistory = async () => {
    if (!botId) return;
    
    try {
      setLoadingHistory(true);
      setError('');
      const response = await groupManagementService.getContactHistory(botId, id);
      // A resposta pode ser um array direto ou um objeto com history
      if (Array.isArray(response)) {
        setContactHistory(response);
      } else if (response.history) {
        setContactHistory(response.history);
      } else {
        setContactHistory([]);
      }
    } catch (err) {
      console.error('Error loading contact history:', err);
      setError('Erro ao carregar histórico');
      setContactHistory([]);
    } finally {
      setLoadingHistory(false);
    }
  };

  const handleBlock = async () => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja bloquear este contato?',
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      await contactService.blockContact(id, botId);
      await loadContact();
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao bloquear contato');
    }
  };

  const handleSendReminder = async () => {
    const confirmed = await confirm({
      message: 'Deseja enviar um lembrete de expiração para este contato?',
      type: 'info',
    });
    
    if (!confirmed) return;

    try {
      await contactService.sendExpirationReminder(id, botId);
      alert('Lembrete enviado com sucesso!');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao enviar lembrete');
    }
  };

  const handleSendGroupReminder = async () => {
    const confirmed = await confirm({
      message: 'ATENÇÃO: Isso enviará lembretes para TODOS os contatos do bot que têm planos ativos ou expirando. Deseja continuar?',
      type: 'warning',
    });
    
    if (!confirmed) return;

    try {
      await contactService.sendGroupExpirationReminder(botId);
      alert('Processo de envio em massa iniciado!');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao iniciar envio em massa');
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const getInitials = (name) => {
    if (!name) return '?';
    const parts = name.split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.charAt(0).toUpperCase();
  };

  const getFullName = () => {
    if (!contact) return 'N/A';
    const parts = [contact.first_name, contact.last_name].filter(Boolean);
    return parts.length > 0 ? parts.join(' ') : contact.username || 'Sem nome';
  };

  if (loading) {
    return (
      <Layout>
        <div className="contact-details-page">
          <div className="loading-container" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', height: '400px' }}>
            <MoonLoader color="#487fff" size={40} />
            <p style={{ marginTop: '16px', color: '#6b7280' }}>Carregando...</p>
          </div>
        </div>
      </Layout>
    );
  }

  if (error && !contact) {
    return (
      <Layout>
        <div className="contact-details-page">
          <div className="error-container">
            <p>{error}</p>
            <button onClick={() => navigate('/results/contacts')} className="btn-back">
              Voltar para Contatos
            </button>
          </div>
        </div>
      </Layout>
    );
  }

  if (!contact) {
    return (
      <Layout>
        <div className="contact-details-page">
          <div className="error-container">
            <p>Contato não encontrado</p>
            <button onClick={() => navigate('/results/contacts')} className="btn-back">
              Voltar para Contatos
            </button>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <DialogComponent />
      <div className="contact-details-page">
        <div className="contact-details-header">
          <button onClick={() => navigate('/results/contacts')} className="btn-back">
            ← Voltar
          </button>
          <h1>Detalhes do Contato</h1>
        </div>

        {error && <div className="alert alert-error">{error}</div>}

        <div className="contact-details-content">
          {/* Profile Card */}
          <div className="contact-profile-card">
            <div className="contact-avatar-large">
              {getInitials(getFullName())}
            </div>
            <div className="contact-profile-info">
              <h2>{getFullName()}</h2>
              {contact.username && (
                <p className="contact-username">@{contact.username}</p>
              )}
              <div className="contact-status-badges">
                <span className={`status-badge ${contact.is_blocked ? 'blocked' : 'active'}`}>
                  {contact.is_blocked ? 'Bloqueado' : 'Ativo'}
                </span>
                {contact.telegram_status && (
                  <span className={`status-badge telegram-${contact.telegram_status}`}>
                    Telegram: {contact.telegram_status === 'active' ? 'Ativo' : 'Inativo'}
                  </span>
                )}
              </div>
            </div>
          </div>

          {/* Contact Information */}
          <div className="contact-info-section">
            <h3>Informações do Contato</h3>
            <div className="info-grid">
              <div className="info-item">
                <label>ID do Telegram</label>
                <p>{contact.telegram_id}</p>
              </div>
              <div className="info-item">
                <label>Nome</label>
                <p>{contact.first_name || 'N/A'}</p>
              </div>
              <div className="info-item">
                <label>Sobrenome</label>
                <p>{contact.last_name || 'N/A'}</p>
              </div>
              <div className="info-item">
                <label>Username</label>
                <p>{contact.username ? `@${contact.username}` : 'N/A'}</p>
              </div>
              <div className="info-item">
                <label>E-mail</label>
                <p>{contact.email || 'N/A'}</p>
              </div>
              <div className="info-item">
                <label>Telefone</label>
                <p>{contact.phone || 'N/A'}</p>
              </div>
              <div className="info-item">
                <label>Idioma</label>
                <p>{contact.language || 'N/A'}</p>
              </div>
              <div className="info-item">
                <label>É Bot?</label>
                <p>{contact.is_bot ? 'Sim' : 'Não'}</p>
              </div>
              <div className="info-item">
                <label>Data de Criação</label>
                <p>{formatDate(contact.created_at)}</p>
              </div>
              <div className="info-item">
                <label>Última Atualização</label>
                <p>{formatDate(contact.updated_at)}</p>
              </div>
              
              {/* Informações do Plano */}
              <div className="info-item highlight-item">
                <label>Plano Atual</label>
                <p>{contact.current_plan || 'Nenhum plano ativo'}</p>
              </div>
              {contact.plan_payment_date && (
                <div className="info-item highlight-item">
                  <label>Data do Pagamento</label>
                  <p>{formatDate(contact.plan_payment_date)}</p>
                </div>
              )}
              {contact.plan_expires_at && (
                <div className="info-item highlight-item">
                  <label>Expira em</label>
                  <p>
                    {formatDate(contact.plan_expires_at)} 
                    {contact.plan_days_remaining !== null && (
                      <span className={`days-remaining ${contact.plan_days_remaining <= 3 ? 'urgent' : ''}`}>
                        ({contact.plan_days_remaining} dias restantes)
                      </span>
                    )}
                  </p>
                </div>
              )}
            </div>
          </div>

          {/* Group Membership */}
          {bot && bot.telegram_group_id && (
            <div className="contact-group-section">
              <h3>Membroship no Grupo</h3>
              <div className="group-actions">
                <button
                  onClick={loadMemberStatus}
                  className="btn-check-status"
                  disabled={loadingMemberStatus}
                >
                  {loadingMemberStatus ? <MoonLoader color="#ffffff" size={16} /> : 'Verificar Status no Grupo'}
                </button>
                {memberStatus && memberStatus.success !== false && (
                  <div className="member-status-info">
                    <p>
                      <strong>Status:</strong>{' '}
                      <span className={memberStatus.is_member ? 'member' : 'not-member'}>
                        {memberStatus.is_member ? '✓ Membro do grupo' : '✗ Não é membro do grupo'}
                      </span>
                    </p>
                    {memberStatus.status && (
                      <p><strong>Tipo:</strong> {memberStatus.status}</p>
                    )}
                    {memberStatus.error && (
                      <p className="error-text"><strong>Erro:</strong> {memberStatus.error}</p>
                    )}
                  </div>
                )}
                {memberStatus && memberStatus.success === false && (
                  <div className="member-status-info">
                    <p className="error-text">{memberStatus.error || 'Erro ao verificar status'}</p>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Contact History */}
          {bot && (
            <div className="contact-history-section">
              <h3>Histórico de Ações</h3>
              <button
                onClick={loadContactHistory}
                className="btn-load-history"
                disabled={loadingHistory}
              >
                {loadingHistory ? <MoonLoader color="#ffffff" size={16} /> : 'Carregar Histórico'}
              </button>
              {contactHistory !== null && (
                <div className="history-list">
                  {Array.isArray(contactHistory) && contactHistory.length === 0 ? (
                    <p className="no-history">Nenhum histórico disponível</p>
                  ) : Array.isArray(contactHistory) ? (
                    contactHistory.map((entry, index) => (
                      <div key={entry.id || index} className={`history-item history-${entry.action_type || 'default'}`}>
                        <div className="history-header">
                          <span className="history-action-label">
                            {entry.action_label || entry.action || 'Ação'}
                          </span>
                          {entry.status && (
                            <span className={`history-status status-${entry.status}`}>
                              {entry.status === 'pending' ? '⏳ Pendente' : 
                               entry.status === 'completed' ? '✅ Concluído' :
                               entry.status === 'failed' ? '❌ Falhou' : entry.status}
                            </span>
                          )}
                        </div>
                        <p className="history-description">
                          {entry.description || entry.message || 'Sem descrição'}
                        </p>
                        {entry.transaction && (
                          <div className="history-transaction">
                            <strong>Transação:</strong> R$ {parseFloat(entry.transaction.amount || 0).toFixed(2)}
                            {entry.transaction.plan && (
                              <span> - {entry.transaction.plan.title}</span>
                            )}
                          </div>
                        )}
                        {entry.metadata && Object.keys(entry.metadata).length > 0 && (
                          <details className="history-metadata">
                            <summary>Ver detalhes</summary>
                            <pre>{JSON.stringify(entry.metadata, null, 2)}</pre>
                          </details>
                        )}
                        {entry.created_at && (
                          <p className="history-date">
                            {entry.created_at_human || formatDate(entry.created_at)}
                          </p>
                        )}
                      </div>
                    ))
                  ) : (
                    <p className="error-text">Erro ao carregar histórico</p>
                  )}
                </div>
              )}
            </div>
          )}

          {/* Actions */}
          <div className="contact-actions-section">
            <h3>Ações</h3>
            <div className="action-buttons">
              <button
                onClick={handleBlock}
                className={`btn-block ${contact.is_blocked ? 'btn-unblock' : ''}`}
              >
                {contact.is_blocked ? 'Desbloquear' : 'Bloquear'}
              </button>
              
              {contact.current_plan && (
                <button
                  onClick={handleSendReminder}
                  className="btn-action btn-reminder"
                >
                  Enviar Lembrete de Expiração
                </button>
              )}
              
              <button
                onClick={handleSendGroupReminder}
                className="btn-action btn-group-reminder"
                style={{ backgroundColor: '#ff9800', color: 'white' }}
              >
                Enviar Lembrete para Todos (Em Massa)
              </button>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default ContactDetails;
