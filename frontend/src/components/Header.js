import React, { useContext, useState, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { 
  faBars, 
  faSun, 
  faComment, 
  faBell, 
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
  const { user } = useContext(AuthContext);
  const [bots, setBots] = useState([]);
  const [showBotMenu, setShowBotMenu] = useState(false);
  const [loadingBots, setLoadingBots] = useState(false);

  useEffect(() => {
    loadBots();
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

  const getPageTitle = () => {
    const path = location.pathname;
    if (path === '/') return 'Dashboard';
    if (path === '/bot/create') return 'Criar novo bot';
    if (path === '/bot/update') return 'Atualizar bot';
    if (path.startsWith('/bot/update/')) return 'Atualizar bot';
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

  const handleBotSelect = (botId) => {
    localStorage.setItem('selectedBotId', botId.toString());
    navigate(`/bot/update/${botId}`);
    setShowBotMenu(false);
    // Reload page to update sidebar
    window.location.reload();
  };

  const handleCreateBot = () => {
    navigate('/bot/create');
    setShowBotMenu(false);
  };

  const handleLogout = () => {
    // Implementar logout
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
        </div>
      </div>

      <div className="header-center">
        <div className="header-title">
          <span className="header-back-icon">←</span>
          <div>
            <h1>{getPageTitle()}</h1>
            <p>{getPageTitle()}</p>
          </div>
        </div>
      </div>

      <div className="header-right">
        <button className="header-icon-btn" title="Tema">
          <FontAwesomeIcon icon={faSun} />
        </button>
        <button className="header-icon-btn" title="Chat">
          <FontAwesomeIcon icon={faComment} />
        </button>
        <button className="header-icon-btn" title="Notificações">
          <FontAwesomeIcon icon={faBell} />
        </button>
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
