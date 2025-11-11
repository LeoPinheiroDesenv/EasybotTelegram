import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Layout from '../components/Layout';
import botService from '../services/botService';
import SubscribersChart from '../components/SubscribersChart';
import BillingChart from '../components/BillingChart';
import './Dashboard.css';

const Dashboard = () => {
  const navigate = useNavigate();
  const [bots, setBots] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    loadBots();
  }, []);

  const loadBots = async () => {
    try {
      setLoading(true);
      setError('');
      const data = await botService.getAllBots();
      setBots(data || []);
    } catch (error) {
      console.error('Error loading bots:', error);
      const errorMessage = error.response?.data?.error || error.message || 'Erro ao carregar bots';
      setError(errorMessage);
      
      // Se for erro de conexão, mostrar mensagem mais clara
      if (error.code === 'ECONNREFUSED' || error.message.includes('Network Error')) {
        setError('Não foi possível conectar ao servidor. Verifique se o backend está rodando na porta 5000.');
      }
    } finally {
      setLoading(false);
    }
  };

  const handleCreateBot = () => {
    navigate('/bot/create');
  };

  const handleUpdateBot = (botId) => {
    localStorage.setItem('selectedBotId', botId.toString());
    navigate(`/bot/update/${botId}`);
  };

  return (
    <Layout>
      <div className="dashboard-container">
        {loading ? (
          <div className="dashboard-loading">Carregando...</div>
        ) : error ? (
          <div className="dashboard-error">
            <div className="error-content">
              <h2>Erro ao carregar dados</h2>
              <p>{error}</p>
              <button className="btn btn-primary" onClick={loadBots}>
                Tentar novamente
              </button>
            </div>
          </div>
        ) : bots.length === 0 ? (
          <div className="dashboard-content">
            <div className="dashboard-illustration">
              <div className="robot-illustration">
                <div className="robot-body">
                  <div className="robot-eye"></div>
                  <div className="robot-button"></div>
                </div>
                <div className="robot-antenna">
                  <div className="antenna-light"></div>
                </div>
                <div className="robot-wheels">
                  <div className="wheel"></div>
                  <div className="wheel"></div>
                </div>
                <div className="robot-magnifier">
                  <div className="magnifier-handle"></div>
                </div>
              </div>
            </div>
            <div className="dashboard-text">
              <h1 className="dashboard-title">Você não selecionou um bot!</h1>
              <p className="dashboard-description">
                Crie um bot agora mesmo clicando no botão abaixo e depois volte aqui caso precise atualizá-lo.
              </p>
              <button className="dashboard-button" onClick={handleCreateBot}>
                Criar bot agora
              </button>
            </div>
          </div>
        ) : (
          <div className="dashboard-bots">
            <div className="dashboard-header">
              <h1 className="dashboard-title">Dashboard</h1>
              <button className="btn btn-primary" onClick={handleCreateBot}>
                + Criar novo bot
              </button>
            </div>
            
            {/* Gráficos */}
            <div className="dashboard-charts">
              <SubscribersChart />
              <BillingChart />
            </div>
            
            <div className="dashboard-section">
              <h2 className="dashboard-section-title">Meus Bots</h2>
            </div>
            <div className="bots-grid">
              {bots.map((bot) => (
                <div key={bot.id} className="bot-card">
                  <div className="bot-card-header">
                    <h3 className="bot-name">{bot.name}</h3>
                    <div className={`bot-status ${bot.activated ? 'active' : 'inactive'}`}>
                      {bot.activated ? 'Ativado' : 'Desativado'}
                    </div>
                  </div>
                  <div className="bot-card-info">
                    <div className="bot-info-item">
                      <span className="bot-info-label">Token:</span>
                      <span className="bot-info-value">{bot.token.substring(0, 20)}...</span>
                    </div>
                    {bot.telegram_group_id && (
                      <div className="bot-info-item">
                        <span className="bot-info-label">Grupo ID:</span>
                        <span className="bot-info-value">{bot.telegram_group_id}</span>
                      </div>
                    )}
                    <div className="bot-info-item">
                      <span className="bot-info-label">Pagamento:</span>
                      <span className="bot-info-value">
                        {bot.payment_method === 'credit_card' ? 'Cartão' : 'Pix'}
                      </span>
                    </div>
                  </div>
                  <div className="bot-card-actions">
                    <button
                      className="btn btn-secondary"
                      onClick={() => handleUpdateBot(bot.id)}
                    >
                      Editar
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
};

export default Dashboard;
