import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import Layout from '../components/Layout';
import contactService from '../services/contactService';
import botService from '../services/botService';
import groupManagementService from '../services/groupManagementService';
import './ContactDetails.css';

const ContactDetails = () => {
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
      const history = await groupManagementService.getContactHistory(botId, id);
      setContactHistory(history);
    } catch (err) {
      console.error('Error loading contact history:', err);
    } finally {
      setLoadingHistory(false);
    }
  };

  const handleBlock = async () => {
    if (!window.confirm('Tem certeza que deseja bloquear este contato?')) {
      return;
    }

    try {
      await contactService.blockContact(id, botId);
      await loadContact();
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao bloquear contato');
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
          <div className="loading-container">Carregando...</div>
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
              {contact.expires_at && (
                <div className="info-item">
                  <label>Expira em</label>
                  <p>{formatDate(contact.expires_at)}</p>
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
                  {loadingMemberStatus ? 'Verificando...' : 'Verificar Status no Grupo'}
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
                {loadingHistory ? 'Carregando...' : 'Carregar Histórico'}
              </button>
              {contactHistory && (
                <div className="history-list">
                  {Array.isArray(contactHistory) && contactHistory.length === 0 ? (
                    <p>Nenhum histórico disponível</p>
                  ) : Array.isArray(contactHistory) ? (
                    contactHistory.map((entry, index) => (
                      <div key={index} className="history-item">
                        <p><strong>{entry.action || entry.type || 'Ação'}</strong></p>
                        <p>{entry.message || entry.description || 'Sem mensagem'}</p>
                        {entry.created_at && (
                          <p className="history-date">{formatDate(entry.created_at)}</p>
                        )}
                      </div>
                    ))
                  ) : (
                    <p>Erro ao carregar histórico</p>
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
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default ContactDetails;

