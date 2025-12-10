import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Layout from '../components/Layout';
import botService from '../services/botService';
import billingService from '../services/billingService';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import { Bar, Line, Pie, Doughnut } from 'react-chartjs-2';
import './Dashboard.css';

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const Dashboard = () => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [stats, setStats] = useState(null);
  const [bots, setBots] = useState([]);

  useEffect(() => {
    loadDashboardData();
  }, []);

  const loadDashboardData = async () => {
    try {
      setLoading(true);
      setError('');
      const [statsData, botsData] = await Promise.all([
        billingService.getDashboardStatistics(),
        botService.getAllBots()
      ]);
      setStats(statsData);
      setBots(botsData || []);
    } catch (err) {
      console.error('Error loading dashboard:', err);
      setError(err.response?.data?.error || err.message || 'Erro ao carregar dados do dashboard');
    } finally {
      setLoading(false);
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

  const formatPercentage = (value) => {
    if (value === null || value === undefined || isNaN(value)) {
      return '0%';
    }
    const sign = value >= 0 ? '+' : '';
    return `${sign}${value.toFixed(1)}%`;
  };

  if (loading) {
    return (
      <Layout>
        <div className="dashboard-finance-container">
          <div className="dashboard-loading">Carregando dashboard...</div>
        </div>
      </Layout>
    );
  }

  if (error) {
    return (
      <Layout>
        <div className="dashboard-finance-container">
          <div className="dashboard-error">
            <div className="error-content">
              <h2>Erro ao carregar dados</h2>
              <p>{error}</p>
              <button className="btn btn-primary" onClick={loadDashboardData}>
                Tentar novamente
              </button>
            </div>
          </div>
        </div>
      </Layout>
    );
  }

  if (!stats) {
    return (
      <Layout>
        <div className="dashboard-finance-container">
          <div className="dashboard-error">
            <p>Nenhum dado disponível</p>
          </div>
        </div>
      </Layout>
    );
  }

  // Dados para gráfico de barras (estatísticas diárias)
  const dailyChartData = {
    labels: stats.daily_stats?.map(d => d.date_label) || [],
    datasets: [
      {
        label: 'Recebimentos Diários',
        data: stats.daily_stats?.map(d => d.total) || [],
        backgroundColor: 'rgba(34, 197, 94, 0.6)',
        borderColor: 'rgba(34, 197, 94, 1)',
        borderWidth: 2,
        borderRadius: 8
      }
    ]
  };

  // Dados para gráfico de pizza (por método de pagamento)
  const paymentMethodChartData = {
    labels: stats.breakdown?.by_payment_method?.map(m => 
      m.method === 'credit_card' ? 'Cartão de Crédito' : m.method === 'pix' ? 'PIX' : m.method
    ) || [],
    datasets: [
      {
        data: stats.breakdown?.by_payment_method?.map(m => m.total) || [],
        backgroundColor: [
          'rgba(59, 130, 246, 0.8)',
          'rgba(251, 191, 36, 0.8)',
          'rgba(139, 92, 246, 0.8)',
          'rgba(236, 72, 153, 0.8)'
        ],
        borderWidth: 2,
        borderColor: '#ffffff'
      }
    ]
  };

  // Dados para gráfico donut (por gateway)
  const gatewayChartData = {
    labels: stats.breakdown?.by_gateway?.map(g => 
      g.gateway === 'mercadopago' ? 'Mercado Pago' : g.gateway === 'stripe' ? 'Stripe' : g.gateway
    ) || [],
    datasets: [
      {
        data: stats.breakdown?.by_gateway?.map(g => g.total) || [],
        backgroundColor: [
          'rgba(0, 199, 190, 0.8)',
          'rgba(99, 102, 241, 0.8)',
          'rgba(251, 146, 60, 0.8)'
        ],
        borderWidth: 2,
        borderColor: '#ffffff'
      }
    ]
  };

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top',
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            if (context.dataset.label === 'Recebimentos Diários') {
              return `R$ ${context.parsed.y.toFixed(2)}`;
            }
            return `${context.label}: ${formatCurrency(context.parsed)}`;
          }
        }
      }
    }
  };

  const pieChartOptions = {
    ...chartOptions,
    plugins: {
      ...chartOptions.plugins,
      tooltip: {
        callbacks: {
          label: function(context) {
            const label = context.label || '';
            const value = context.parsed || 0;
            const total = context.dataset.data.reduce((a, b) => a + b, 0);
            const percentage = ((value / total) * 100).toFixed(1);
            return `${label}: ${formatCurrency(value)} (${percentage}%)`;
          }
        }
      }
    }
  };

  // Verifica se o usuário não tem bots
  const hasNoBots = bots.length === 0;

  return (
    <Layout>
      <div className="dashboard-finance-container">
        <div className="dashboard-header-section">
          <div>
            <h1 className="dashboard-main-title">Dashboard - Financeiro</h1>
            <p className="dashboard-subtitle">Visão geral dos seus recebimentos e transações</p>
          </div>
          <button className="btn-refresh" onClick={loadDashboardData}>
            🔄 Atualizar
          </button>
        </div>

        {hasNoBots && (
          <div className="alert alert-info" style={{ marginBottom: '24px', padding: '16px', backgroundColor: '#dbeafe', border: '1px solid #93c5fd', borderRadius: '8px', color: '#1e40af' }}>
            <strong>💡 Informação:</strong> Você ainda não criou nenhum bot. Crie um bot para começar a receber pagamentos e visualizar estatísticas aqui.
            <button 
              onClick={() => navigate('/bot/create')} 
              className="btn btn-primary" 
              style={{ marginLeft: '12px', marginTop: '8px' }}
            >
              Criar Bot
            </button>
          </div>
        )}

        {/* Cards de Métricas */}
        <div className="metrics-cards-grid">
          <div className="metric-card revenue">
            <div className="metric-icon">💰</div>
            <div className="metric-content">
              <div className="metric-label">Recebimentos do Mês</div>
              <div className="metric-value">{formatCurrency(stats.metrics?.total_revenue?.current || 0)}</div>
              <div className="metric-change positive">
                {formatPercentage(stats.metrics?.total_revenue?.growth_percentage || 0)} 
                <span className="metric-period">vs mês anterior {formatCurrency(stats.metrics?.total_revenue?.last_month || 0)}</span>
              </div>
            </div>
          </div>

          <div className="metric-card transactions">
            <div className="metric-icon">💳</div>
            <div className="metric-content">
              <div className="metric-label">Transações</div>
              <div className="metric-value">{stats.metrics?.total_transactions?.current || 0}</div>
              <div className="metric-change positive">
                {formatPercentage(stats.metrics?.total_transactions?.growth_percentage || 0)}
                <span className="metric-period">vs mês anterior {stats.metrics?.total_transactions?.last_month || 0}</span>
              </div>
            </div>
          </div>

          <div className="metric-card subscriptions">
            <div className="metric-icon">👥</div>
            <div className="metric-content">
              <div className="metric-label">Assinaturas Ativas</div>
              <div className="metric-value">{stats.metrics?.active_subscriptions?.current || 0}</div>
              <div className="metric-period">Últimos 30 dias</div>
            </div>
          </div>

          <div className="metric-card total">
            <div className="metric-icon">📊</div>
            <div className="metric-content">
              <div className="metric-label">Total Geral</div>
              <div className="metric-value">{formatCurrency(stats.metrics?.total_all_time?.current || 0)}</div>
              <div className="metric-period">{stats.metrics?.total_all_time?.transactions || 0} transações no total</div>
            </div>
          </div>
        </div>

        {/* Gráficos Principais */}
        <div className="charts-grid">
          {/* Gráfico de Barras - Estatísticas Diárias */}
          <div className="chart-card large">
            <div className="chart-header">
              <h3>Estatísticas de Recebimentos (Últimos 7 Dias)</h3>
              <select className="chart-filter">
                <option>Últimos 7 dias</option>
              </select>
            </div>
            <div className="chart-content">
              <Bar data={dailyChartData} options={chartOptions} />
            </div>
          </div>

          {/* Gráfico de Pizza - Por Método de Pagamento */}
          <div className="chart-card">
            <div className="chart-header">
              <h3>Recebimentos por Método</h3>
            </div>
            <div className="chart-content">
              {stats.breakdown?.by_payment_method?.length > 0 ? (
                <Pie data={paymentMethodChartData} options={pieChartOptions} />
              ) : (
                <div className="chart-empty">Nenhum dado disponível</div>
              )}
            </div>
          </div>

          {/* Gráfico Donut - Por Gateway */}
          <div className="chart-card">
            <div className="chart-header">
              <h3>Recebimentos por Gateway</h3>
            </div>
            <div className="chart-content">
              {stats.breakdown?.by_gateway?.length > 0 ? (
                <Doughnut data={gatewayChartData} options={pieChartOptions} />
              ) : (
                <div className="chart-empty">Nenhum dado disponível</div>
              )}
            </div>
          </div>
        </div>

        {/* Tabela de Pagamentos Recentes e Estatísticas */}
        <div className="dashboard-bottom-grid">
          {/* Tabela de Pagamentos Recentes */}
          <div className="table-card">
            <div className="table-header">
              <h3>Histórico de Pagamentos</h3>
              <button className="btn-link" onClick={() => navigate('/billing')}>
                Ver todos
              </button>
            </div>
            <div className="table-content">
              {stats.recent_transactions && stats.recent_transactions.length > 0 ? (
                <table className="payments-table">
                  <thead>
                    <tr>
                      <th>Usuário</th>
                      <th>Plano</th>
                      <th>Método</th>
                      <th>Valor</th>
                      <th>Data</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {stats.recent_transactions.map((transaction) => (
                      <tr key={transaction.id}>
                        <td>
                          <div className="user-info">
                            <div className="user-name">{transaction.contact.name}</div>
                            {transaction.contact.username && (
                              <div className="user-username">@{transaction.contact.username}</div>
                            )}
                          </div>
                        </td>
                        <td>{transaction.payment_plan?.title || 'N/A'}</td>
                        <td>
                          <span className={`method-badge ${transaction.payment_method}`}>
                            {transaction.payment_method === 'credit_card' ? '💳 Cartão' : '💰 PIX'}
                          </span>
                        </td>
                        <td className="amount">{formatCurrency(transaction.amount)}</td>
                        <td>{transaction.created_at_formatted}</td>
                        <td>
                          <span className="status-badge active">Ativo</span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <div className="table-empty">Nenhuma transação recente</div>
              )}
            </div>
          </div>

          {/* Estatísticas por Bot */}
          <div className="stats-card">
            <div className="stats-header">
              <h3>Recebimentos por Bot</h3>
            </div>
            <div className="stats-content">
              {stats.breakdown?.by_bot && stats.breakdown.by_bot.length > 0 ? (
                <div className="stats-list">
                  {stats.breakdown.by_bot.map((bot, index) => (
                    <div key={bot.bot_id || index} className="stat-item">
                      <div className="stat-item-header">
                        <span className="stat-item-label">{bot.bot_name}</span>
                        <span className="stat-item-value">{formatCurrency(bot.total)}</span>
                      </div>
                      <div className="stat-item-details">
                        <span>{bot.count} transação(ões)</span>
                        <div className="stat-item-bar">
                          <div 
                            className="stat-item-bar-fill" 
                            style={{ 
                              width: `${(bot.total / (stats.metrics?.total_revenue?.current || 1)) * 100}%` 
                            }}
                          ></div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="stats-empty">Nenhum dado disponível</div>
              )}
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default Dashboard;
