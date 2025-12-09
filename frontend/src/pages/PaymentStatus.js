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
  faSync,
  faBell,
  faUserMinus
} from '@fortawesome/free-solid-svg-icons';
import './PaymentStatus.css';

const PaymentStatus = () => {
  const { botId: paramBotId } = useParams();
  const [botId, setBotId] = useState(paramBotId || localStorage.getItem('selectedBotId'));
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

  useEffect(() => {
    if (botId) {
      loadBot();
      loadStatuses();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoading(false);
    }
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
              </tr>
            </thead>
            <tbody>
              {filteredStatuses.length === 0 ? (
                <tr>
                  <td colSpan="7" className="no-data">
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
                            Expirado há {Math.abs(daysRemaining)} dia(s)
                          </span>
                        ) : daysRemaining !== undefined ? (
                          <span className={`days-remaining ${daysRemaining <= 7 ? 'warning' : ''}`}>
                            {daysRemaining} dia(s)
                          </span>
                        ) : (
                          '-'
                        )}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </Layout>
  );
};

export default PaymentStatus;

