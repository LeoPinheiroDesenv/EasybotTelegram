import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import Layout from '../components/Layout';
import paymentStatusService from '../services/paymentStatusService';
import botService from '../services/botService';
import RefreshButton from '../components/RefreshButton';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { 
  faCheckCircle, 
  faExclamationTriangle, 
  faClock, 
  faTimesCircle,
  faBell,
  faUserMinus,
  faInfoCircle,
  faTimes,
  faPaperPlane,
  faSync
} from '@fortawesome/free-solid-svg-icons';
import './PaymentStatus.css';

const PaymentStatus = () => {
  const { botId: paramBotId } = useParams();
  const [botId] = useState(paramBotId || localStorage.getItem('selectedBotId'));
  const [bot, setBot] = useState(null);
  const [statuses, setStatuses] = useState([]);
  const [summary, setSummary] = useState({
    total: 0,
    active: 0,
    expired: 0,
    expiring_soon: 0,
    no_payment: 0
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [searchTerm, setSearchTerm] = useState('');
  const [processingExpired, setProcessingExpired] = useState(false);
  const [processingExpiring, setProcessingExpiring] = useState(false);
  const [showDetailsModal, setShowDetailsModal] = useState(false);
  const [selectedTransaction, setSelectedTransaction] = useState(null);
  const [transactionDetails, setTransactionDetails] = useState(null);
  const [loadingDetails, setLoadingDetails] = useState(false);
  const [resendingLink, setResendingLink] = useState({});
  const [renewingLink, setRenewingLink] = useState({});

  useEffect(() => {
    if (botId) {
      loadBot();
      loadStatuses();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [botId]);

  const loadBot = async () => {
    try {
      const botData = await botService.getBotById(botId);
      setBot(botData);
    } catch (err) {
      console.error('Erro ao carregar bot:', err);
    }
  };

  const loadStatuses = async () => {
    try {
      setLoading(true);
      setError('');
      const filters = {};
      if (statusFilter !== 'all') {
        filters.status = statusFilter;
      }
      const response = await paymentStatusService.getBotStatuses(botId, filters);
      if (response.success) {
        setStatuses(response.data || []);
        setSummary(response.summary || summary);
      } else {
        setError(response.error || 'Erro ao carregar status de pagamentos');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar status de pagamentos');
    } finally {
      setLoading(false);
    }
  };

  const handleCheckExpired = async () => {
    try {
      setProcessingExpired(true);
      setError('');
      setSuccess('');
      const response = await paymentStatusService.checkExpiredPayments(botId);
      if (response.success) {
        const { expired_count, removed_count, notified_count } = response.data;
        setSuccess(
          `Verificação concluída: ${expired_count} pagamento(s) expirado(s), ` +
          `${removed_count} usuário(s) removido(s) do grupo, ` +
          `${notified_count} usuário(s) notificado(s).`
        );
        loadStatuses();
      } else {
        setError(response.error || 'Erro ao verificar pagamentos expirados');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao verificar pagamentos expirados');
    } finally {
      setProcessingExpired(false);
    }
  };

  const handleCheckExpiring = async () => {
    try {
      setProcessingExpiring(true);
      setError('');
      setSuccess('');
      const response = await paymentStatusService.checkExpiringPayments(botId, 7);
      if (response.success) {
        const { expiring_count, notified_count } = response.data;
        setSuccess(
          `Verificação concluída: ${expiring_count} pagamento(s) próximo(s) de expirar, ` +
          `${notified_count} usuário(s) notificado(s).`
        );
        loadStatuses();
      } else {
        setError(response.error || 'Erro ao verificar pagamentos próximos de expirar');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao verificar pagamentos próximos de expirar');
    } finally {
      setProcessingExpiring(false);
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'active':
        return <FontAwesomeIcon icon={faCheckCircle} className="status-icon active" />;
      case 'expired':
        return <FontAwesomeIcon icon={faTimesCircle} className="status-icon expired" />;
      case 'expiring_soon':
        return <FontAwesomeIcon icon={faExclamationTriangle} className="status-icon expiring" />;
      case 'no_payment':
        return <FontAwesomeIcon icon={faClock} className="status-icon no-payment" />;
      default:
        return null;
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'active':
        return 'Ativo';
      case 'expired':
        return 'Expirado';
      case 'expiring_soon':
        return 'Expirando em breve';
      case 'no_payment':
        return 'Sem pagamento';
      default:
        return status;
    }
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(value);
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const filteredStatuses = statuses.filter(status => {
    if (!searchTerm) return true;
    const search = searchTerm.toLowerCase();
    const contact = status.contact || {};
    return (
      (contact.first_name || '').toLowerCase().includes(search) ||
      (contact.last_name || '').toLowerCase().includes(search) ||
      (contact.username || '').toLowerCase().includes(search) ||
      (contact.email || '').toLowerCase().includes(search) ||
      (status.payment_plan?.title || '').toLowerCase().includes(search)
    );
  });

  const handleShowDetails = async (status) => {
    if (!status.transaction?.id) {
      setError('Transação não encontrada');
      return;
    }

    setSelectedTransaction(status);
    setShowDetailsModal(true);
    setLoadingDetails(true);
    setTransactionDetails(null);

    try {
      const response = await paymentStatusService.getTransactionDetails(status.transaction.id);
      if (response.success) {
        setTransactionDetails(response.data);
      } else {
        setError(response.error || 'Erro ao carregar detalhes da transação');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar detalhes da transação');
    } finally {
      setLoadingDetails(false);
    }
  };

  const handleCloseModal = () => {
    setShowDetailsModal(false);
    setSelectedTransaction(null);
    setTransactionDetails(null);
  };

  const handleResendGroupLink = async (transactionId) => {
    try {
      setResendingLink(prev => ({ ...prev, [transactionId]: true }));
      setError('');
      setSuccess('');
      
      const response = await paymentStatusService.resendGroupLink(transactionId);
      
      if (response.success) {
        setSuccess('Link do grupo reenviado com sucesso!');
        setTimeout(() => setSuccess(''), 5000);
      } else {
        setError(response.error || 'Erro ao reenviar link do grupo');
        setTimeout(() => setError(''), 5000);
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao reenviar link do grupo');
      setTimeout(() => setError(''), 5000);
    } finally {
      setResendingLink(prev => ({ ...prev, [transactionId]: false }));
    }
  };

  const handleRenewGroupLink = async (transactionId) => {
    try {
      setRenewingLink(prev => ({ ...prev, [transactionId]: true }));
      setError('');
      setSuccess('');
      
      const response = await paymentStatusService.renewGroupLink(transactionId);
      
      if (response.success) {
        setSuccess('Link do grupo renovado com sucesso!');
        setTimeout(() => setSuccess(''), 5000);
        // Recarrega os status para atualizar a interface
        loadStatuses();
      } else {
        setError(response.error || 'Erro ao renovar link do grupo');
        setTimeout(() => setError(''), 5000);
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao renovar link do grupo');
      setTimeout(() => setError(''), 5000);
    } finally {
      setRenewingLink(prev => ({ ...prev, [transactionId]: false }));
    }
  };

  if (loading && statuses.length === 0) {
    return (
      <Layout>
        <div className="payment-status-page">
          <div className="loading-container">Carregando status de pagamentos...</div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="payment-status-page">
        <div className="payment-status-header">
          <div className="header-content">
            <h1>Status de Pagamentos</h1>
            {bot && <span className="bot-name">{bot.name}</span>}
          </div>
          <div className="header-actions">
            <RefreshButton onRefresh={loadStatuses} />
            <button
              className="btn btn-secondary"
              onClick={handleCheckExpiring}
              disabled={processingExpiring}
              title="Verificar pagamentos próximos de expirar"
            >
              <FontAwesomeIcon icon={faBell} /> Verificar Expirando
            </button>
            <button
              className="btn btn-warning"
              onClick={handleCheckExpired}
              disabled={processingExpired}
              title="Verificar e processar pagamentos expirados"
            >
              <FontAwesomeIcon icon={faUserMinus} /> 
              {processingExpired ? 'Processando...' : 'Verificar Expirados'}
            </button>
          </div>
        </div>

        {error && (
          <div className="alert alert-error">
            {error}
          </div>
        )}

        {success && (
          <div className="alert alert-success">
            {success}
          </div>
        )}

        <div className="payment-status-summary">
          <div className="summary-card total">
            <div className="summary-label">Total</div>
            <div className="summary-value">{summary.total}</div>
          </div>
          <div className="summary-card active">
            <div className="summary-label">Ativos</div>
            <div className="summary-value">{summary.active}</div>
          </div>
          <div className="summary-card expiring">
            <div className="summary-label">Expirando em breve</div>
            <div className="summary-value">{summary.expiring_soon}</div>
          </div>
          <div className="summary-card expired">
            <div className="summary-label">Expirados</div>
            <div className="summary-value">{summary.expired}</div>
          </div>
          <div className="summary-card no-payment">
            <div className="summary-label">Sem pagamento</div>
            <div className="summary-value">{summary.no_payment}</div>
          </div>
        </div>

        <div className="payment-status-filters">
          <div className="filter-group">
            <label>Filtrar por status:</label>
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                loadStatuses();
              }}
            >
              <option value="all">Todos</option>
              <option value="active">Ativos</option>
              <option value="expiring_soon">Expirando em breve</option>
              <option value="expired">Expirados</option>
              <option value="no_payment">Sem pagamento</option>
            </select>
          </div>
          <div className="filter-group">
            <label>Buscar:</label>
            <input
              type="text"
              placeholder="Nome, usuário, email ou plano..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </div>

        <div className="payment-status-table-container">
          <table className="payment-status-table">
            <thead>
              <tr>
                <th>Usuário</th>
                <th>Plano</th>
                <th>Valor</th>
                <th>Ciclo</th>
                <th>Status</th>
                <th>Expira em</th>
                <th>Dias restantes</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              {filteredStatuses.length === 0 ? (
                <tr>
                  <td colSpan="8" className="no-data">
                    {searchTerm ? 'Nenhum resultado encontrado' : 'Nenhum pagamento encontrado'}
                  </td>
                </tr>
              ) : (
                filteredStatuses.map((status) => {
                  const contact = status.contact || {};
                  const daysRemaining = status.days_until_expiration;
                  
                  return (
                    <tr key={status.contact?.id || Math.random()} className={`status-row ${status.status}`}>
                      <td>
                        <div className="user-cell">
                          <div className="user-name">
                            {contact.first_name || contact.username || 'Sem nome'}
                            {contact.last_name && ` ${contact.last_name}`}
                          </div>
                          {contact.username && (
                            <div className="user-username">@{contact.username}</div>
                          )}
                          {contact.email && (
                            <div className="user-email">{contact.email}</div>
                          )}
                        </div>
                      </td>
                      <td>{status.payment_plan?.title || '-'}</td>
                      <td className="amount-cell">
                        {status.transaction?.amount 
                          ? formatCurrency(status.transaction.amount)
                          : '-'}
                      </td>
                      <td>{status.payment_cycle?.name || '-'}</td>
                      <td>
                        <div className="status-badge-container">
                          {getStatusIcon(status.status)}
                          <span className={`status-badge ${status.status}`}>
                            {getStatusLabel(status.status)}
                          </span>
                        </div>
                      </td>
                      <td>{formatDate(status.expires_at)}</td>
                      <td>
                        {status.is_expired ? (
                          <span className="days-remaining expired">
                            Expirado há {Math.abs(Math.round(daysRemaining || 0))} dia(s)
                          </span>
                        ) : daysRemaining !== undefined && daysRemaining !== null ? (
                          <span className={`days-remaining ${Math.round(daysRemaining) <= 7 ? 'warning' : ''}`}>
                            {Math.round(daysRemaining)} dia(s)
                          </span>
                        ) : (
                          '-'
                        )}
                      </td>
                      <td>
                        <div style={{ display: 'flex', gap: '5px', flexWrap: 'wrap' }}>
                          {status.transaction?.id && (
                            <>
                              <button
                                className="btn btn-sm btn-info"
                                onClick={() => handleShowDetails(status)}
                                title="Ver detalhes da confirmação do pagamento"
                              >
                                <FontAwesomeIcon icon={faInfoCircle} /> Detalhes
                              </button>
                              {(status.status === 'active' || status.transaction?.status === 'approved' || status.transaction?.status === 'paid' || status.transaction?.status === 'completed') && (
                                <>
                                  <button
                                    className="btn btn-sm btn-success"
                                    onClick={() => handleResendGroupLink(status.transaction.id)}
                                    disabled={resendingLink[status.transaction.id] || renewingLink[status.transaction.id]}
                                    title="Reenviar link do grupo para o usuário"
                                  >
                                    <FontAwesomeIcon icon={faPaperPlane} spin={resendingLink[status.transaction.id]} /> 
                                    {resendingLink[status.transaction.id] ? ' Enviando...' : ' Reenviar'}
                                  </button>
                                  <button
                                    className="btn btn-sm btn-primary"
                                    onClick={() => handleRenewGroupLink(status.transaction.id)}
                                    disabled={resendingLink[status.transaction.id] || renewingLink[status.transaction.id]}
                                    title="Renovar link do grupo (criar novo link)"
                                  >
                                    <FontAwesomeIcon icon={faSync} spin={renewingLink[status.transaction.id]} /> 
                                    {renewingLink[status.transaction.id] ? ' Renovando...' : ' Renovar'}
                                  </button>
                                </>
                              )}
                            </>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Modal de Detalhes da Transação */}
        {showDetailsModal && (
          <div className="modal-overlay" onClick={handleCloseModal}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>Detalhes da Confirmação do Pagamento</h2>
                <button className="modal-close" onClick={handleCloseModal}>
                  <FontAwesomeIcon icon={faTimes} />
                </button>
              </div>
              <div className="modal-body">
                {loadingDetails ? (
                  <div className="loading-container">Carregando detalhes...</div>
                ) : transactionDetails ? (
                  <div className="transaction-details">
                    <div className="details-section">
                      <h3>Informações da Transação</h3>
                      <div className="details-grid">
                        <div className="detail-item">
                          <label>ID da Transação:</label>
                          <span>{transactionDetails.transaction.id}</span>
                        </div>
                        <div className="detail-item">
                          <label>Valor:</label>
                          <span>{formatCurrency(transactionDetails.transaction.amount)} {transactionDetails.transaction.currency}</span>
                        </div>
                        <div className="detail-item">
                          <label>Status:</label>
                          <span className={`status-badge ${transactionDetails.transaction.status}`}>
                            {transactionDetails.transaction.status}
                          </span>
                        </div>
                        <div className="detail-item">
                          <label>Método de Pagamento:</label>
                          <span>{transactionDetails.transaction.payment_method || '-'}</span>
                        </div>
                        <div className="detail-item">
                          <label>Gateway:</label>
                          <span>{transactionDetails.transaction.gateway || '-'}</span>
                        </div>
                        <div className="detail-item">
                          <label>Data de Criação:</label>
                          <span>{formatDate(transactionDetails.transaction.created_at)}</span>
                        </div>
                        <div className="detail-item">
                          <label>Última Atualização:</label>
                          <span>{formatDate(transactionDetails.transaction.updated_at)}</span>
                        </div>
                      </div>
                    </div>

                    {transactionDetails.gateway_data && Object.keys(transactionDetails.gateway_data).length > 0 && (
                      <div className="details-section">
                        <h3>Dados do Gateway ({transactionDetails.gateway_data.gateway})</h3>
                        <div className="details-grid">
                          {transactionDetails.gateway_data.payment_id && (
                            <div className="detail-item">
                              <label>ID do Pagamento:</label>
                              <span>{transactionDetails.gateway_data.payment_id}</span>
                            </div>
                          )}
                          {transactionDetails.gateway_data.payment_intent_id && (
                            <div className="detail-item">
                              <label>Payment Intent ID:</label>
                              <span>{transactionDetails.gateway_data.payment_intent_id}</span>
                            </div>
                          )}
                          {transactionDetails.gateway_data.charge_id && (
                            <div className="detail-item">
                              <label>Charge ID:</label>
                              <span>{transactionDetails.gateway_data.charge_id}</span>
                            </div>
                          )}
                          {transactionDetails.gateway_data.status && (
                            <div className="detail-item">
                              <label>Status no Gateway:</label>
                              <span>{transactionDetails.gateway_data.status}</span>
                            </div>
                          )}
                          {transactionDetails.gateway_data.status_detail && (
                            <div className="detail-item">
                              <label>Detalhes do Status:</label>
                              <span>{transactionDetails.gateway_data.status_detail}</span>
                            </div>
                          )}
                          {transactionDetails.gateway_data.card_brand && (
                            <div className="detail-item">
                              <label>Bandeira do Cartão:</label>
                              <span>{transactionDetails.gateway_data.card_brand}</span>
                            </div>
                          )}
                          {transactionDetails.gateway_data.card_last4 && (
                            <div className="detail-item">
                              <label>Últimos 4 dígitos:</label>
                              <span>**** {transactionDetails.gateway_data.card_last4}</span>
                            </div>
                          )}
                          {transactionDetails.gateway_data.last_webhook_update && (
                            <div className="detail-item">
                              <label>Última Atualização via Webhook:</label>
                              <span>{formatDate(transactionDetails.gateway_data.last_webhook_update)}</span>
                            </div>
                          )}
                          {transactionDetails.gateway_data.webhook_action && (
                            <div className="detail-item">
                              <label>Ação do Webhook:</label>
                              <span>{transactionDetails.gateway_data.webhook_action}</span>
                            </div>
                          )}
                          {transactionDetails.gateway_data.last_status_check && (
                            <div className="detail-item">
                              <label>Última Verificação de Status:</label>
                              <span>{formatDate(transactionDetails.gateway_data.last_status_check)}</span>
                            </div>
                          )}
                          {transactionDetails.gateway_data.expiration_date && (
                            <div className="detail-item">
                              <label>Data de Expiração (PIX):</label>
                              <span>{formatDate(transactionDetails.gateway_data.expiration_date)}</span>
                            </div>
                          )}
                          {transactionDetails.gateway_data.pix_ticket_url && (
                            <div className="detail-item">
                              <label>URL do Ticket PIX:</label>
                              <span>
                                <a href={transactionDetails.gateway_data.pix_ticket_url} target="_blank" rel="noopener noreferrer">
                                  {transactionDetails.gateway_data.pix_ticket_url}
                                </a>
                              </span>
                            </div>
                          )}
                        </div>
                      </div>
                    )}

                    {transactionDetails.payment_plan && (
                      <div className="details-section">
                        <h3>Plano de Pagamento</h3>
                        <div className="details-grid">
                          <div className="detail-item">
                            <label>Plano:</label>
                            <span>{transactionDetails.payment_plan.title}</span>
                          </div>
                          <div className="detail-item">
                            <label>Valor do Plano:</label>
                            <span>{formatCurrency(transactionDetails.payment_plan.price)}</span>
                          </div>
                        </div>
                      </div>
                    )}

                    {transactionDetails.contact && (
                      <div className="details-section">
                        <h3>Contato</h3>
                        <div className="details-grid">
                          <div className="detail-item">
                            <label>Nome:</label>
                            <span>{transactionDetails.contact.first_name} {transactionDetails.contact.last_name || ''}</span>
                          </div>
                          {transactionDetails.contact.username && (
                            <div className="detail-item">
                              <label>Usuário:</label>
                              <span>@{transactionDetails.contact.username}</span>
                            </div>
                          )}
                          {transactionDetails.contact.email && (
                            <div className="detail-item">
                              <label>Email:</label>
                              <span>{transactionDetails.contact.email}</span>
                            </div>
                          )}
                        </div>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="error-message">Erro ao carregar detalhes da transação</div>
                )}
              </div>
              <div className="modal-footer">
                {transactionDetails?.transaction?.id && 
                 (transactionDetails.transaction.status === 'approved' || 
                  transactionDetails.transaction.status === 'paid' || 
                  transactionDetails.transaction.status === 'completed') && (
                  <button
                    className="btn btn-success"
                    onClick={() => handleResendGroupLink(transactionDetails.transaction.id)}
                    disabled={resendingLink[transactionDetails.transaction.id]}
                    title="Reenviar link do grupo para o usuário"
                  >
                    <FontAwesomeIcon icon={faPaperPlane} spin={resendingLink[transactionDetails.transaction.id]} /> 
                    {resendingLink[transactionDetails.transaction.id] ? ' Enviando...' : ' Reenviar Link do Grupo'}
                  </button>
                )}
                <button className="btn btn-secondary" onClick={handleCloseModal}>
                  Fechar
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
};

export default PaymentStatus;

