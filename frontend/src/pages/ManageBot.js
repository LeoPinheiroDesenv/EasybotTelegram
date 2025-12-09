import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Outlet, useLocation } from 'react-router-dom';
import Layout from '../components/Layout';
import botService from '../services/botService';
import useAlert from '../hooks/useAlert';
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
        navigate(`/bot/manage/${storedBotId}`, { replace: true });
      } else {
        setError('Nenhum bot selecionado');
        setLoading(false);
      }
    }
  }, [botId, navigate]);

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
      id: 'welcome', 
      label: 'Mensagens de Boas Vindas', 
      path: `/bot/manage/${botId}/welcome`,
      route: `/bot/welcome/${botId}`
    },
    { 
      id: 'payment-plans', 
      label: 'Planos de Pagamento', 
      path: `/bot/manage/${botId}/payment-plans`,
      route: `/bot/payment-plans/${botId}`
    },
    { 
      id: 'redirect', 
      label: 'Botões de redirecionamento', 
      path: `/bot/manage/${botId}/redirect`,
      route: `/bot/redirect/${botId}`
    },
    { 
      id: 'commands', 
      label: 'Comandos', 
      path: `/bot/manage/${botId}/commands`,
      route: `/bot/commands/${botId}`
    },
    { 
      id: 'administrators', 
      label: 'Administradores', 
      path: `/bot/manage/${botId}/administrators`,
      route: `/bot/administrators/${botId}`
    },
    { 
      id: 'telegram-groups', 
      label: 'Grupos e Canais', 
      path: `/bot/manage/${botId}/telegram-groups`,
      route: `/bot/telegram-groups/${botId}`
    },
    { 
      id: 'botfather', 
      label: 'Gerenciamento via BotFather', 
      path: `/bot/manage/${botId}/botfather`,
      route: `/bot/${botId}/botfather`
    },
  ];

  // Determina a aba ativa baseada na rota atual
  const getActiveTab = () => {
    const currentPath = location.pathname;
    
    // Verifica se está em uma rota de gerenciamento
    if (currentPath.includes('/bot/manage/')) {
      const tabId = currentPath.split('/').pop();
      return tabId || 'welcome';
    }
    
    // Mapeia rotas antigas para abas
    if (currentPath.includes('/bot/welcome/')) return 'welcome';
    if (currentPath.includes('/bot/payment-plans/')) return 'payment-plans';
    if (currentPath.includes('/bot/redirect/')) return 'redirect';
    if (currentPath.includes('/bot/commands/')) return 'commands';
    if (currentPath.includes('/bot/administrators/')) return 'administrators';
    if (currentPath.includes('/bot/telegram-groups/')) return 'telegram-groups';
    if (currentPath.includes('/botfather')) return 'botfather';
    
    return 'welcome';
  };

  const activeTab = getActiveTab();

  const handleTabClick = (tab) => {
    navigate(tab.route);
  };

  if (loading) {
    return (
      <Layout>
        <div className="manage-bot-container">
          <div className="loading">Carregando bot...</div>
        </div>
      </Layout>
    );
  }

  if (error || !bot) {
    return (
      <Layout>
        <div className="manage-bot-container">
          <div className="error-container">
            <p>{error || 'Bot não encontrado'}</p>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <DialogComponent />
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
          <Outlet />
        </div>
      </div>
    </Layout>
  );
};

export default ManageBot;

