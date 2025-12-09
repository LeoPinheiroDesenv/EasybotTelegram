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
    if (path === '/') return 'Dashboard';
    if (path === '/bot/create') return 'Criar novo bot';
    if (path === '/bot/update') return 'Atualizar bot';
    if (path.startsWith('/bot/update/')) return 'Atualizar bot';
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

  const handleBotSelect = async (botId) => {
    localStorage.setItem('selectedBotId', botId.toString());
    setShowBotMenu(false);
    await loadCurrentBot(); // Atualiza o bot exibido no header
    navigate(`/bot/update/${botId}`);
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
        <div className="header-logo">
          <div className="logo-circles">
            <span className="circle circle-1"></span>
            <span className="circle circle-2"></span>
            <span className="circle circle-3"></span>
          </div>
          <div className="logo-text">
            <div className="logo-title">Easy</div>
            <div className="logo-subtitle">Página inicial</div>
          </div>
          <div className="header-title">
          <div style={{ marginLeft: '20px' }}>
          <span className=""></span>
            <h1>
                {getPageTitle()}
                </h1>
          </div>
        </div>
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
                          className={`bot-menu-item ${location.pathname === `/bot/update/${bot.id}` ? 'active' : ''}`}
                          onClick={() => handleBotSelect(bot.id)}
                        >
                          <div className="bot-menu-icon">
                            <FontAwesomeIcon icon={faRobot} style={{ color: '#9333ea' }} />
                          </div>
                          <span className="bot-menu-name">{bot.name}</span>
                          {location.pathname === `/bot/update/${bot.id}` && (
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
