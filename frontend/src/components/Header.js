import React, { useContext, useState, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { 
  faBars, 
  faRobot,
  faPlus,
  faTimes
} from '@fortawesome/free-solid-svg-icons';
import { AuthContext } from '../contexts/AuthContext';
import botService from '../services/botService';
import './Header.css';

const Header = ({ onMenuClick }) => {
  const location = useLocation();
  const navigate = useNavigate();
  useContext(AuthContext);
  const [bots, setBots] = useState([]);
  const [showBotMenu, setShowBotMenu] = useState(false);
  const [loadingBots, setLoadingBots] = useState(false);
  const [currentBot, setCurrentBot] = useState(null);
  const [loadingCurrentBot, setLoadingCurrentBot] = useState(false);

  useEffect(() => {
    loadBots();
    loadCurrentBot();
  }, [location.pathname]);

  // Recarrega o bot atual quando o selectedBotId mudar (de outras abas)
  useEffect(() => {
    const handleStorageChange = (e) => {
      if (e.key === 'selectedBotId') {
        loadCurrentBot();
      }
    };
    
    window.addEventListener('storage', handleStorageChange);
    
    return () => {
      window.removeEventListener('storage', handleStorageChange);
    };
  }, []);

  const loadBots = async () => {
    try {
      setLoadingBots(true);
      const data = await botService.getAllBots();
      setBots(data);
    } catch (err) {
      console.error('Error loading bots:', err);
    } finally {
      setLoadingBots(false);
    }
  };

  const loadCurrentBot = async () => {
    try {
      const storedBotId = localStorage.getItem('selectedBotId');
      if (storedBotId) {
        setLoadingCurrentBot(true);
        const bot = await botService.getBotById(storedBotId);
        
        // Tenta obter o username do bot_info se disponível
        try {
          const status = await botService.getBotStatus(storedBotId);
          if (status.bot_info && status.bot_info.username) {
            bot.username = status.bot_info.username;
          }
        } catch (statusErr) {
          // Se não conseguir obter o status, continua sem username
          console.warn('Could not load bot status for username:', statusErr);
        }
        
        setCurrentBot(bot);
      } else {
        setCurrentBot(null);
      }
    } catch (err) {
      console.error('Error loading current bot:', err);
      setCurrentBot(null);
    } finally {
      setLoadingCurrentBot(false);
    }
  };

  const getPageTitle = () => {
    const path = location.pathname;
    if (path === '/') return 'Dashboard - Financeiro';
    if (path === '/billing') return 'Faturamento';
    if (path.startsWith('/payment-status/')) return 'Status de Pagamentos';
    if (path === '/bot/create') return 'Criar novo bot';
    if (path === '/bot/update') return 'Atualizar bot';
    if (path.startsWith('/bot/update/')) return 'Atualizar bot';
    if (path.startsWith('/bot/list')) return 'Meus bots';
    if (path.startsWith('/settings/profile')) return 'Meu Perfil';
    if (path.startsWith('/settings/payment-cycles')) return 'Ciclos de Pagamento';
    if (path.startsWith('/settings/payment-gateways')) return 'Gateways de Pagamento';
    if (path.startsWith('/settings/security')) return 'Segurança (2FA)';
    if (path.startsWith('/settings/storage')) return 'Storage';
    if (path.startsWith('/settings/artisan')) return 'Comandos Artisan';
    if (path.startsWith('/settings/cron-jobs')) return 'Cron Jobs';
    if (path.startsWith('/settings/laravel-logs')) return 'Logs do Laravel';
    if (path === '/users') return 'Usuários';
    if (path === '/user-groups') return 'Grupos de Usuários';
    if (path === '/logs') return 'Logs';
    if (path === '/ftp') return 'Gerenciador FTP';

    if (path.includes('/bot/manage/') || 
        path.startsWith('/bot/welcome/') ||
        path.startsWith('/bot/payment-plans/') ||
        path.startsWith('/bot/redirect/') ||
        path.startsWith('/bot/commands/') ||
        path.startsWith('/bot/administrators/') ||
        path.startsWith('/bot/telegram-groups/') ||
        (path.includes('/bot/') && path.includes('/botfather'))) {
      return 'Gerenciar Bot';
    }
    if (path === '/bot/welcome') return 'Mensagem de boas-vindas';
    if (path === '/bot/payment-plans') return 'Planos de pagamento';
    if (path === '/bot/redirect') return 'Botões de redirecionamento';
    if (path === '/bot/administrators') return 'Administradores';
    if (path === '/bot/groups') return 'Grupos e Canais';
    if (path === '/results/contacts') return 'Contatos';
    if (path === '/marketing/alerts') return 'Alertas';
    if (path === '/marketing/downsell') return 'Downsell';
    if (path === '/marketing') return 'Marketing';
    if (path === '/users') return 'Usuários';
    return 'Página Inicial';
  };

  const getPageSubtitle = () => {
    const path = location.pathname;
    if (path === '/') return 'Visão geral dos seus recebimentos e transações';
    if (path === '/billing') return 'Consulte e gerencie seus pagamentos';
    if (path.startsWith('/payment-status/')) return 'Detalhes do status do pagamento';
    if (path === '/bot/create') return 'Preencha os detalhes para criar um novo bot';
    if (path.startsWith('/bot/update/')) return 'Atualize os detalhes do seu bot';
    if (path.startsWith('/bot/list')) return 'Veja e gerencie todos os seus bots';
    if (path === '/results/contacts') return 'Visualize e gerencie seus contatos';
    if (path === '/settings/payment-cycles') return 'Gerencie os ciclos de pagamento';
    if (path === '/settings/payment-gateways') return 'Gerencie os gateways de pagamento';
    if (path === '/settings/security') return 'Configure a autenticação de dois fatores para maior segurança';
    if (path === '/settings/storage') return 'Gerencie seus arquivos e armazenamento';
    if (path === '/settings/artisan') return 'Execute comandos Artisan diretamente do painel';
    if (path === '/settings/cron-jobs') return 'Configure e monitore seus trabalhos cron';
    if (path === '/settings/laravel-logs') return 'Visualize e gerencie os logs do Laravel';
    if (path === '/users') return 'Gerencie os usuários da sua plataforma';
    if (path === '/user-groups') return 'Organize usuários em grupos para melhor gerenciamento';
    if (path === '/logs') return 'Revise as atividades e eventos do sistema';
    if (path === '/ftp') return 'Acesse e gerencie seus arquivos via FTP';


    if (path.startsWith('/settings/profile')) return 'Atualize suas informações pessoais e preferências';
    if (path.includes('/bot/manage/') ||
        path.startsWith('/bot/welcome/') ||
        path.startsWith('/bot/payment-plans/') ||
        path.startsWith('/bot/redirect/') ||
        path.startsWith('/bot/commands/') ||
        path.startsWith('/bot/administrators/') ||
        path.startsWith('/bot/telegram-groups/') ||
        (path.includes('/bot/') && path.includes('/botfather'))) {
      return 'Gerencie as configurações e funcionalidades do seu bot';
    }
    return 'Informações e ações da página atual';
  };

  const handleBotSelect = async (botId) => {
    localStorage.setItem('selectedBotId', botId.toString());
    setShowBotMenu(false);
    await loadCurrentBot(); // Atualiza o bot exibido no header
    navigate(`/bot/manage/${botId}/settings`);
    // Dispara evento customizado para atualizar outros componentes
    window.dispatchEvent(new Event('botSelected'));
  };

  const handleCreateBot = () => {
    navigate('/bot/create');
    setShowBotMenu(false);
  };


  return (
    <div className="header">
      <div className="header-left">
        <button className="mobile-menu-btn" onClick={onMenuClick} aria-label="Menu">
          <FontAwesomeIcon icon={faBars} />
        </button>

        <div>
          <h1 className="dashboard-main-title">{getPageTitle()}</h1>
          <p className="dashboard-subtitle">{getPageSubtitle()}</p>
        </div>


      </div>
      <div className="header-right">
        {loadingCurrentBot ? (
          <div className="current-bot-info loading">
            <div className="current-bot-icon">
              <FontAwesomeIcon icon={faRobot} />
            </div>
            <div className="current-bot-details">
              <div className="current-bot-name">Carregando...</div>
            </div>
          </div>
        ) : currentBot ? (
          <div className="current-bot-info">
            <div className="current-bot-icon">
              <FontAwesomeIcon icon={faRobot} />
            </div>
            <div className="current-bot-details">
              <div className="current-bot-name">{currentBot.name}</div>
              {currentBot.username && (
                <div className="current-bot-username">@{currentBot.username}</div>
              )}
            </div>
          </div>
        ) : (
          <div className="current-bot-info no-bot">
            <div className="current-bot-icon">
              <FontAwesomeIcon icon={faRobot} />
            </div>
            <div className="current-bot-details">
              <div className="current-bot-name">Nenhum bot selecionado</div>
            </div>
          </div>
        )}
        <div className="header-profile-menu">
          <button 
            className="profile-circle-btn"
            onClick={() => setShowBotMenu(!showBotMenu)}
            title="Meus bots"
          >
            <FontAwesomeIcon icon={faRobot} />
          </button>

          {showBotMenu && (
            <>
              <div 
                className="menu-overlay" 
                onClick={() => setShowBotMenu(false)}
              ></div>
              <div className="bots-dropdown-menu">
                <div className="dropdown-header">
                  <h3>Meus bots</h3>
                  <button 
                    className="close-btn"
                    onClick={() => setShowBotMenu(false)}
                  >
                    <FontAwesomeIcon icon={faTimes} />
                  </button>
                </div>

                {loadingBots ? (
                  <div className="dropdown-loading">Carregando...</div>
                ) : bots.length === 0 ? (
                  <div className="dropdown-empty">
                    <p>Nenhum bot criado</p>
                    <button onClick={handleCreateBot} className="btn-create-in-menu">
                      + Criar bot
                    </button>
                  </div>
                ) : (
                  <>
                    <div className="bots-dropdown-list">
                      {bots.map((bot) => (
                        <button
                          key={bot.id}
                          className={`bot-menu-item ${location.pathname === `/bot/manage/${bot.id}/settings` ? 'active' : ''}`}
                          onClick={() => handleBotSelect(bot.id)}
                        >
                          <div className="bot-menu-icon">
                            <FontAwesomeIcon icon={faRobot} style={{ color: '#9333ea' }} />
                          </div>
                          <span className="bot-menu-name">{bot.name}</span>
                          {location.pathname === `/bot/manage/${bot.id}/settings` && (
                            <span className="bot-menu-active">Ativo</span>
                          )}
                        </button>
                      ))}
                    </div>
                    <div className="dropdown-footer">
                      <button onClick={handleCreateBot} className="btn-create-new-menu">
                        <FontAwesomeIcon icon={faPlus} />
                        Criar novo bot
                      </button>
                    </div>
                  </>
                )}
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
};

export default Header;
