import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import Layout from '../components/Layout';
import botService from '../services/botService';
import groupManagementService from '../services/groupManagementService';
import './GroupManagement.css';

const GroupManagement = () => {
  const { botId } = useParams();
  const [bot, setBot] = useState(null);
  const [statistics, setStatistics] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedPeriod, setSelectedPeriod] = useState(30);

  useEffect(() => {
    if (botId) {
      loadBot();
      loadStatistics();
    }
  }, [botId, selectedPeriod]);

  const loadBot = async () => {
    try {
      const botData = await botService.getBotById(botId);
      setBot(botData);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar bot');
    }
  };

  const loadStatistics = async () => {
    try {
      setLoading(true);
      const stats = await groupManagementService.getStatistics(botId, selectedPeriod);
      setStatistics(stats);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar estatísticas');
    } finally {
      setLoading(false);
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
  };

  if (loading && !statistics) {
    return (
      <Layout>
        <div className="group-management-page">
          <div className="loading-container">Carregando estatísticas...</div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="group-management-page">
        <div className="group-management-header">
          <h1>Gerenciamento de Grupo</h1>
          {bot && <p className="bot-name">Bot: {bot.name}</p>}
        </div>

        {error && <div className="alert alert-error">{error}</div>}

        {/* Filtro de Período */}
        <div className="period-filter">
          <label>Período:</label>
          <select value={selectedPeriod} onChange={(e) => setSelectedPeriod(Number(e.target.value))}>
            <option value={7}>Últimos 7 dias</option>
            <option value={30}>Últimos 30 dias</option>
            <option value={60}>Últimos 60 dias</option>
            <option value={90}>Últimos 90 dias</option>
          </select>
        </div>

        {statistics && (
          <>
            {/* Resumo */}
            <div className="stats-summary">
              <div className="stat-card">
                <div className="stat-icon add">+</div>
                <div className="stat-content">
                  <div className="stat-value">{statistics.summary.total_additions}</div>
                  <div className="stat-label">Adições</div>
                </div>
              </div>
              <div className="stat-card">
                <div className="stat-icon remove">-</div>
                <div className="stat-content">
                  <div className="stat-value">{statistics.summary.total_removals}</div>
                  <div className="stat-label">Remoções</div>
                </div>
              </div>
              <div className="stat-card">
                <div className="stat-icon net">
                  {statistics.summary.net_change >= 0 ? '+' : ''}
                  {statistics.summary.net_change}
                </div>
                <div className="stat-content">
                  <div className="stat-value">{statistics.summary.net_change}</div>
                  <div className="stat-label">Variação Líquida</div>
                </div>
              </div>
              <div className="stat-card">
                <div className="stat-icon auto">⚙</div>
                <div className="stat-content">
                  <div className="stat-value">{statistics.summary.automatic_actions}</div>
                  <div className="stat-label">Automáticas</div>
                </div>
              </div>
              <div className="stat-card">
                <div className="stat-icon manual">👤</div>
                <div className="stat-content">
                  <div className="stat-value">{statistics.summary.manual_actions}</div>
                  <div className="stat-label">Manuais</div>
                </div>
              </div>
            </div>

            {/* Gráfico Diário */}
            <div className="daily-chart-section">
              <h2>Atividades Diárias</h2>
              <div className="daily-chart">
                {statistics.daily_stats.map((day, index) => {
                  const maxTotal = Math.max(...statistics.daily_stats.map(d => d.total), 1);
                  const addHeight = (day.additions / maxTotal) * 100;
                  const removeHeight = (day.removals / maxTotal) * 100;
                  
                  return (
                    <div key={index} className="chart-bar-group">
                      <div className="chart-bars">
                        <div 
                          className="chart-bar add-bar" 
                          style={{ height: `${addHeight}%` }}
                          title={`${day.additions} adições`}
                        />
                        <div 
                          className="chart-bar remove-bar" 
                          style={{ height: `${removeHeight}%` }}
                          title={`${day.removals} remoções`}
                        />
                      </div>
                      <div className="chart-label">{formatDate(day.date).split('/')[0]}</div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Motivos */}
            {Object.keys(statistics.reasons).length > 0 && (
              <div className="reasons-section">
                <h2>Motivos das Ações</h2>
                <div className="reasons-list">
                  {Object.entries(statistics.reasons).map(([reason, count]) => (
                    <div key={reason} className="reason-item">
                      <span className="reason-name">{reason}</span>
                      <span className="reason-count">{count}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Transações */}
            <div className="transactions-section">
              <h2>Transações Relacionadas</h2>
              <div className="transactions-stats">
                <div className="transaction-stat">
                  <span className="transaction-label">Pagamentos Aprovados:</span>
                  <span className="transaction-value success">{statistics.summary.paid_transactions}</span>
                </div>
                <div className="transaction-stat">
                  <span className="transaction-label">Pagamentos Expirados/Cancelados:</span>
                  <span className="transaction-value error">{statistics.summary.expired_transactions}</span>
                </div>
              </div>
            </div>
          </>
        )}
      </div>
    </Layout>
  );
};

export default GroupManagement;

