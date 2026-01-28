import React, { useState, useEffect } from 'react';
import Layout from '../components/Layout';
import billingService from '../services/billingService';
import botService from '../services/botService';
import RefreshButton from '../components/RefreshButton';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPaperPlane } from '@fortawesome/free-solid-svg-icons';
import { Line } from 'react-chartjs-2';
import DatePicker, { registerLocale } from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";
import ptBR from 'date-fns/locale/pt-BR';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import './Billing.css';

// Registra o locale pt-BR para o DatePicker
registerLocale('pt-BR', ptBR);

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const Billing = () => {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [monthlyBilling, setMonthlyBilling] = useState(null);
  const [billingData, setBillingData] = useState(null);
  const [chartData, setChartData] = useState(null);
  const [bots, setBots] = useState([]);
  const [resendingLink, setResendingLink] = useState({});
  
  // Filtros
  const [filters, setFilters] = useState({
    start_date: '',
    end_date: '',
    month: '',
    bot_id: '',
    payment_method: '',
    gateway: ''
  });

  useEffect(() => {
    loadInitialData();
  }, []);

  useEffect(() => {
    if (filters.start_date || filters.end_date || filters.month || filters.bot_id || filters.payment_method || filters.gateway) {
      loadBillingData();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters]);

  const loadInitialData = async () => {
    try {
      setLoading(true);
      const [monthly, chart, botsData] = await Promise.all([
        billingService.getMonthlyBilling(),
        billingService.getChartData(12),
        botService.getAllBots()
      ]);
      
      setMonthlyBilling(monthly);
      setChartData(chart.data);
      setBots(botsData);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar dados de faturamento');
    } finally {
      setLoading(false);
    }
  };

  const loadBillingData = async () => {
    try {
      setLoading(true);
      const data = await billingService.getBilling(filters);
      setBillingData(data);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar dados filtrados');
    } finally {
      setLoading(false);
    }
  };

  const handleRefresh = async () => {
    await loadInitialData();
    if (filters.start_date || filters.end_date || filters.month || filters.bot_id || filters.payment_method || filters.gateway) {
      await loadBillingData();
    }
  };

  const handleFilterChange = (field, value) => {
    setFilters(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const clearFilters = () => {
    setFilters({
      start_date: '',
      end_date: '',
      month: '',
      bot_id: '',
      payment_method: '',
      gateway: ''
    });
    setBillingData(null);
  };

  const handleResendGroupLink = async (transactionId) => {
    try {
      setResendingLink(prev => ({ ...prev, [transactionId]: true }));
      setError('');
      setSuccess('');
      
      const response = await billingService.resendGroupLink(transactionId);
      
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

  const formatCurrency = (value) => {
    if (value === null || value === undefined || isNaN(value)) {
      return 'R$ 0,00';
    }
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(value);
  };


  const getChartConfig = () => {
    if (!chartData || chartData.length === 0) return null;

    return {
      labels: chartData.map(item => item.month_label),
      datasets: [
        {
          label: 'Faturamento Mensal (R$)',
          data: chartData.map(item => item.total || 0),
          borderColor: '#22c55e',
          backgroundColor: 'rgba(34, 197, 94, 0.1)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#22c55e',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
          pointRadius: 5,
          pointHoverRadius: 7
        }
      ]
    };
  };

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        position: 'top',
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return `R$ ${context.parsed.y.toFixed(2)}`;
          }
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          callback: function(value) {
            return 'R$ ' + value.toFixed(2);
          }
        }
      }
    }
  };

  if (loading && !monthlyBilling) {
    return (
      <Layout>
        <div className="billing-page">
          <div className="loading-container">Carregando dados de faturamento...</div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="billing-page">
        <div className="billing-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h1>Faturamento</h1>
          <RefreshButton onRefresh={handleRefresh} loading={loading} className="compact" />
          <p>Visualize e gerencie seus pagamentos e faturamento</p>
        </div>

        {error && <div className="alert alert-error">{error}</div>}
        {success && <div className="alert alert-success">{success}</div>}

        {/* Resumo Mensal */}
        {monthlyBilling && (
          <div className="billing-summary">
            <div className="summary-card">
              <div className="summary-icon monthly">📅</div>
              <div className="summary-content">
                <div className="summary-label">Faturamento do Mês</div>
                <div className="summary-value">
                  {formatCurrency(monthlyBilling.total || 0)}
                </div>
                <div className="summary-period">{monthlyBilling.period.month}</div>
              </div>
            </div>
            <div className="summary-card">
              <div className="summary-icon transactions">💳</div>
              <div className="summary-content">
                <div className="summary-label">Transações</div>
                <div className="summary-value">{monthlyBilling.transaction_count}</div>
                <div className="summary-period">Este mês</div>
              </div>
            </div>
          </div>
        )}

        {/* Filtros */}
        <div className="billing-filters">
          <h2>Filtros de Consulta</h2>
          <div className="filters-grid">
            <div className="filter-group">
              <label>Período por Mês</label>
              <DatePicker
                selected={filters.month ? new Date(filters.month + '-01T12:00:00') : null}
                onChange={(date) => {
                  handleFilterChange('month', date ? date.toISOString().slice(0, 7) : '');
                  handleFilterChange('start_date', '');
                  handleFilterChange('end_date', '');
                }}
                dateFormat="MM/yyyy"
                showMonthYearPicker
                locale="pt-BR"
                placeholderText="Selecione o mês"
                className="date-picker-input"
                isClearable
              />
            </div>
            <div className="filter-group">
              <label>Data Inicial</label>
              <DatePicker
                selected={filters.start_date ? new Date(filters.start_date + 'T12:00:00') : null}
                onChange={(date) => {
                  handleFilterChange('start_date', date ? date.toISOString().split('T')[0] : '');
                  handleFilterChange('month', '');
                }}
                dateFormat="dd/MM/yyyy"
                locale="pt-BR"
                placeholderText="Selecione a data"
                className="date-picker-input"
                isClearable
                maxDate={filters.end_date ? new Date(filters.end_date + 'T12:00:00') : null}
              />
            </div>
            <div className="filter-group">
              <label>Data Final</label>
              <DatePicker
                selected={filters.end_date ? new Date(filters.end_date + 'T12:00:00') : null}
                onChange={(date) => {
                  handleFilterChange('end_date', date ? date.toISOString().split('T')[0] : '');
                  handleFilterChange('month', '');
                }}
                dateFormat="dd/MM/yyyy"
                locale="pt-BR"
                placeholderText="Selecione a data"
                className="date-picker-input"
                isClearable
                minDate={filters.start_date ? new Date(filters.start_date + 'T12:00:00') : null}
              />
            </div>
            <div className="filter-group">
              <label>Bot</label>
              <select
                value={filters.bot_id}
                onChange={(e) => handleFilterChange('bot_id', e.target.value)}
              >
                <option value="">Todos os bots</option>
                {bots.map(bot => (
                  <option key={bot.id} value={bot.id}>{bot.name}</option>
                ))}
              </select>
            </div>
            <div className="filter-group">
              <label>Método de Pagamento</label>
              <select
                value={filters.payment_method}
                onChange={(e) => handleFilterChange('payment_method', e.target.value)}
              >
                <option value="">Todos</option>
                <option value="credit_card">Cartão de Crédito</option>
                <option value="pix">PIX</option>
              </select>
            </div>
            <div className="filter-group">
              <label>Gateway</label>
              <select
                value={filters.gateway}
                onChange={(e) => handleFilterChange('gateway', e.target.value)}
              >
                <option value="">Todos</option>
                <option value="mercadopago">Mercado Pago</option>
                <option value="stripe">Stripe</option>
              </select>
            </div>
          </div>
          <div className="filters-actions">
            <button onClick={clearFilters} className="btn-clear-filters">
              Limpar Filtros
            </button>
          </div>
        </div>

        {/* Gráfico */}
        {chartData && chartData.length > 0 && (
          <div className="billing-chart-section">
            <h2>Evolução do Faturamento (Últimos 12 Meses)</h2>
            <div className="chart-container">
              {getChartConfig() && (
                <Line data={getChartConfig()} options={chartOptions} />
              )}
            </div>
          </div>
        )}

        {/* Resumo de Planos e Assinaturas */}
        {billingData && billingData.transactions && billingData.transactions.length > 0 && (
          <div className="billing-plans-section">
            <div className="section-header">
              <h2>Planos e Assinaturas</h2>
            </div>
            
            <div className="plans-subscriptions-grid">
              {/* Resumo por Plano */}
              {billingData.plans && billingData.plans.length > 0 ? (
                <div className="plans-card">
                  <h3>📦 Recebimentos por Plano</h3>
                  <div className="plans-list">
                    {billingData.plans.map((plan, index) => (
                      <div key={plan.plan_id || index} className="plan-item">
                        <div className="plan-header">
                          <span className="plan-title">{plan.plan_title}</span>
                          <span className="plan-price">{formatCurrency(plan.plan_price)}</span>
                        </div>
                        <div className="plan-stats">
                          <span className="stat-item">
                            <strong>{plan.subscription_count}</strong> assinatura(s)
                          </span>
                          <span className="stat-item">
                            Total: <strong>{formatCurrency(plan.total_revenue)}</strong>
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <div className="plans-card">
                  <h3>📦 Recebimentos por Plano</h3>
                  <div className="empty-state">
                    <p>Nenhum plano encontrado</p>
                  </div>
                </div>
              )}

              {/* Resumo por Assinatura */}
              {billingData.subscriptions && billingData.subscriptions.length > 0 ? (
                <div className="subscriptions-card">
                  <h3>👥 Assinaturas Ativas</h3>
                  <div className="subscriptions-list">
                    {billingData.subscriptions.slice(0, 10).map((subscription, index) => (
                      <div key={index} className="subscription-item">
                        <div className="subscription-header">
                          <span className="subscription-user">
                            {subscription.contact_name}
                            {subscription.contact_username && (
                              <span className="subscription-username">@{subscription.contact_username}</span>
                            )}
                          </span>
                          <span className="subscription-plan">{subscription.plan_title}</span>
                        </div>
                        <div className="subscription-details">
                          <span className="detail-item">
                            <strong>{subscription.transaction_count}</strong> pagamento(s)
                          </span>
                          <span className="detail-item">
                            Ciclo: <strong>{subscription.cycle_name}</strong> ({subscription.cycle_days} dias)
                          </span>
                          <span className="detail-item">
                            Total: <strong>{formatCurrency(subscription.total_revenue)}</strong>
                          </span>
                        </div>
                        <div className="subscription-dates">
                          <small>Primeiro pagamento: {subscription.first_payment}</small>
                          <small>Último pagamento: {subscription.last_payment}</small>
                        </div>
                      </div>
                    ))}
                    {billingData.subscriptions.length > 10 && (
                      <div className="subscriptions-more">
                        <small>E mais {billingData.subscriptions.length - 10} assinatura(s)...</small>
                      </div>
                    )}
                  </div>
                </div>
              ) : (
                <div className="subscriptions-card">
                  <h3>👥 Assinaturas Ativas</h3>
                  <div className="empty-state">
                    <p>Nenhuma assinatura encontrada</p>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Tabela de Transações */}
        {billingData && (
          <div className="billing-transactions-section">
            <div className="section-header">
              <h2>Transações</h2>
              {billingData.summary && (
                <div className="summary-badge">
                  Total: {formatCurrency(billingData.summary.total)} ({billingData.summary.transaction_count} transações)
                </div>
              )}
            </div>

            {billingData.transactions && billingData.transactions.length > 0 ? (
              <>
                <div className="transactions-table-container">
                  <table className="transactions-table">
                    <thead>
                      <tr>
                        <th>Data</th>
                        <th>Usuário</th>
                        <th>Bot</th>
                        <th>Plano</th>
                        <th>Método</th>
                        <th>Gateway</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      {billingData.transactions.map((transaction) => (
                        <tr key={transaction.id}>
                          <td>{transaction.created_at_formatted}</td>
                          <td>
                            <div className="user-cell">
                              <div className="user-name">{transaction.contact.name}</div>
                              {transaction.contact.username && (
                                <div className="user-username">@{transaction.contact.username}</div>
                              )}
                            </div>
                          </td>
                          <td>{transaction.bot.name}</td>
                          <td>
                            <div className="plan-cell">
                              <div className="plan-name">{transaction.payment_plan?.title || 'N/A'}</div>
                              <div className="plan-price-small">{formatCurrency(transaction.payment_plan?.price || 0)}</div>
                            </div>
                          </td>
                          <td>
                            <span className={`payment-method-badge ${transaction.payment_method}`}>
                              {transaction.payment_method === 'credit_card' ? '💳 Cartão' : '💰 PIX'}
                            </span>
                          </td>
                          <td>
                            <span className="gateway-badge">{transaction.gateway}</span>
                          </td>
                          <td className="amount-cell">{formatCurrency(transaction.amount)}</td>
                          <td>
                            <span className={`status-badge ${transaction.status}`}>
                              {transaction.status === 'approved' || transaction.status === 'paid' || transaction.status === 'completed' 
                                ? '✅ Aprovado' 
                                : transaction.status}
                            </span>
                          </td>
                          <td>
                            {(transaction.status === 'approved' || transaction.status === 'paid' || transaction.status === 'completed') && (
                              <button
                                className="btn btn-sm btn-success"
                                onClick={() => handleResendGroupLink(transaction.id)}
                                disabled={resendingLink[transaction.id]}
                                title="Reenviar link do grupo para o usuário"
                              >
                                <FontAwesomeIcon icon={faPaperPlane} spin={resendingLink[transaction.id]} /> 
                                {resendingLink[transaction.id] ? ' Enviando...' : ' Reenviar Link'}
                              </button>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Resumo por Método e Gateway */}
                {billingData.summary && (
                  <div className="billing-summary-details">
                    <div className="summary-detail-card">
                      <h3>Por Método de Pagamento</h3>
                      {Object.entries(billingData.summary.by_payment_method).map(([method, data]) => (
                        <div key={method} className="detail-item">
                          <span className="detail-label">
                            {method === 'credit_card' ? '💳 Cartão de Crédito' : '💰 PIX'}
                          </span>
                          <span className="detail-value">
                            {formatCurrency(data.total)} ({data.count} transações)
                          </span>
                        </div>
                      ))}
                    </div>
                    <div className="summary-detail-card">
                      <h3>Por Gateway</h3>
                      {Object.entries(billingData.summary.by_gateway).map(([gateway, data]) => (
                        <div key={gateway} className="detail-item">
                          <span className="detail-label">{gateway}</span>
                          <span className="detail-value">
                            {formatCurrency(data.total)} ({data.count} transações)
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </>
            ) : (
              <div className="empty-state">
                <p>Nenhuma transação encontrada com os filtros aplicados.</p>
              </div>
            )}
          </div>
        )}
      </div>
    </Layout>
  );
};

export default Billing;
