import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Outlet, useLocation } from 'react-router-dom';
import Layout from '../components/Layout';
import botService from '../services/botService';
import useAlert from '../hooks/useAlert';
import { ManageBotProvider } from '../contexts/ManageBotContext';
import './ManageBot.css';

const ManageBot = () => {
  const { botId } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const { alert, DialogComponent: AlertDialog } = useAlert();
  const [bot, setBot] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (botId) {
      loadBot();
    } else {
      const storedBotId = localStorage.getItem('selectedBotId');
      if (storedBotId) {
        navigate(`/bot/manage/${storedBotId}/settings`, { replace: true });
      } else {
        setError('Nenhum bot selecionado');
        setLoading(false);
      }
    }
  }, [botId, navigate]);

  useEffect(() => {
    // Redireciona para a primeira aba se estiver na rota base
    if (botId && location.pathname === `/bot/manage/${botId}`) {
      navigate(`/bot/manage/${botId}/settings`, { replace: true });
    }
  }, [botId, location.pathname, navigate]);

  const loadBot = async () => {
    try {
      setLoading(true);
      setError('');
      const data = await botService.getBotById(botId);
      setBot(data);
      localStorage.setItem('selectedBotId', botId);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar bot');
      await alert('Erro ao carregar bot. Por favor, selecione um bot primeiro.', 'Erro', 'error');
      navigate('/');
    } finally {
      setLoading(false);
    }
  };

  // Define as abas disponíveis
  const tabs = [
    { 
      id: 'settings', 
      label: 'Configurações', 
      route: `/bot/manage/${botId}/settings`
    },
    { 
      id: 'welcome', 
      label: 'Mensagens Iniciais', 
      route: `/bot/manage/${botId}/welcome`
    },
    { 
      id: 'payment-plans', 
      label: 'Planos de Pagamento', 
      route: `/bot/manage/${botId}/payment-plans`
    },
    { 
      id: 'redirect', 
      label: 'Botões de redirecionamento', 
      route: `/bot/manage/${botId}/redirect`
    },
    { 
      id: 'commands', 
      label: 'Comandos', 
      route: `/bot/manage/${botId}/commands`
    },
    { 
      id: 'administrators', 
      label: 'Administradores', 
      route: `/bot/manage/${botId}/administrators`
    },
    { 
      id: 'telegram-groups', 
      label: 'Grupos e Canais', 
      route: `/bot/manage/${botId}/telegram-groups`
    },
    { 
      id: 'botfather', 
      label: 'BotFather', 
      route: `/bot/manage/${botId}/botfather`
    },
  ];

  // Determina a aba ativa baseada na rota atual
  const getActiveTab = () => {
    const currentPath = location.pathname;
    
    // Verifica se está em uma rota de gerenciamento
    if (currentPath.includes('/bot/manage/')) {
      const parts = currentPath.split('/');
      const tabId = parts[parts.length - 1];
      return tabId || 'settings';
    }
    
    // Mapeia rotas antigas para abas
    if (currentPath.includes('/bot/update/')) return 'settings';
    
    return 'settings';
  };

  const activeTab = getActiveTab();

  const handleTabClick = (tab) => {
    navigate(tab.route);
  };

  if (loading) {
    return (
      <Layout>
        <div className="manage-bot-wrapper">
          <div className="manage-bot-container">
            <div className="loading">Carregando bot...</div>
          </div>
        </div>
      </Layout>
    );
  }

  if (error || !bot) {
    return (
      <Layout>
        <div className="manage-bot-wrapper">
          <div className="manage-bot-container">
            <div className="error-container">
              <p>{error || 'Bot não encontrado'}</p>
            </div>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <AlertDialog />
      <div className="manage-bot-wrapper">
        <div className="manage-bot-container">
          <div className="manage-bot-header">
            <h1>Gerenciar Bot</h1>
            {bot && (
              <div className="bot-info">
                <span className="bot-name">{bot.name}</span>
              </div>
            )}
          </div>

          <div className="manage-bot-tabs">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                className={`manage-bot-tab ${activeTab === tab.id ? 'active' : ''}`}
                onClick={() => handleTabClick(tab)}
              >
                {tab.label}
              </button>
            ))}
          </div>

          <div className="manage-bot-content">
            <ManageBotProvider>
              <Outlet />
            </ManageBotProvider>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default ManageBot;

