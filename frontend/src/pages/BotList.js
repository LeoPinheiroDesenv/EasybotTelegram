import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faRobot, faPlus, faEdit, faCheckCircle, faTimesCircle } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import botService from '../services/botService';
import RefreshButton from '../components/RefreshButton';
import MoonLoader from "react-spinners/MoonLoader";

import './BotList.css';

const BotList = () => {
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
      setBots(data);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar bots');
    } finally {
      setLoading(false);
    }
  };

  const handleCreateBot = () => {
    navigate('/bot/create');
  };

  const handleSelectBot = (botId) => {
    localStorage.setItem('selectedBotId', botId);
    navigate(`/bot/manage/${botId}/settings`);
  };

  if (loading) {
    return (
      <Layout>
        <div className="bot-list-page">
          <div className="dashboard-loading" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', height: '400px' }}>
            <MoonLoader color="#487fff" size={40} />
            <p style={{ marginTop: '16px', color: '#6b7280' }}>Carregando bots...</p>
          </div>
        </div>

      </Layout>
    );
  }

  return (
    <Layout>
      <div className="bot-list-page">
        <div className="bot-list-header">
          
          <div className="header-actions">
            <RefreshButton onRefresh={loadBots} loading={loading} />
            <button onClick={handleCreateBot} className="btn btn-primary btn-create">
              <FontAwesomeIcon icon={faPlus} />
              Criar novo bot
            </button>
          </div>
        </div>

        {error && (
          <div className="alert alert-error">
            {error}
          </div>
        )}

        {bots.length === 0 ? (
          <div className="empty-state">
            <div className="empty-icon">
              <FontAwesomeIcon icon={faRobot} />
            </div>
            <h2>Nenhum bot criado</h2>
            <p>Comece criando seu primeiro bot do Telegram</p>
            <button onClick={handleCreateBot} className="btn btn-primary">
              <FontAwesomeIcon icon={faPlus} />
              Criar primeiro bot
            </button>
          </div>
        ) : (
          <div className="bots-cards-container">
            {bots.map((bot) => (
              <div
                key={bot.id}
                className="bot-card"
                onClick={() => handleSelectBot(bot.id)}
              >
                <div className="bot-card-header">
                  <div className="bot-card-icon">
                    <FontAwesomeIcon icon={faRobot} />
                  </div>
                  <div className="bot-card-status">
                    {bot.active ? (
                      <span className="status-badge status-active">
                        <FontAwesomeIcon icon={faCheckCircle} />
                        Ativo
                      </span>
                    ) : (
                      <span className="status-badge status-inactive">
                        <FontAwesomeIcon icon={faTimesCircle} />
                        Inativo
                      </span>
                    )}
                  </div>
                </div>
                <div className="bot-card-body">
                  <h3 className="bot-card-name">{bot.name}</h3>
                  {bot.telegram_group_id && (
                    <p className="bot-card-group">
                      Grupo: {bot.telegram_group_id}
                    </p>
                  )}
                  <div className="bot-card-info">
                    <div className="info-item">
                      <span className="info-label">Status:</span>
                      <span className={`info-value ${bot.activated ? 'activated' : 'not-activated'}`}>
                        {bot.activated ? 'Inicializado' : 'Não inicializado'}
                      </span>
                    </div>
                  </div>
                </div>
                <div className="bot-card-footer">
                  <button
                    className="btn btn-secondary btn-sm"
                    onClick={(e) => {
                      e.stopPropagation();
                      handleSelectBot(bot.id);
                    }}
                  >
                    <FontAwesomeIcon icon={faEdit} />
                    Gerenciar
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </Layout>
  );
};

export default BotList;

