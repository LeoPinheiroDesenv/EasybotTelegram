import React, { useContext, useState, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
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
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
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
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="5"></circle>
            <line x1="12" y1="1" x2="12" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="23"></line>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
            <line x1="1" y1="12" x2="3" y2="12"></line>
            <line x1="21" y1="12" x2="23" y2="12"></line>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
          </svg>
        </button>
        <button className="header-icon-btn" title="Chat">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
        </button>
        <button className="header-icon-btn" title="Notificações">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
        </button>
        <div className="header-profile-menu">
          <button 
            className="profile-circle-btn"
            onClick={() => setShowBotMenu(!showBotMenu)}
            title="Meus bots"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <rect x="6" y="4" width="12" height="16" rx="2"></rect>
              <circle cx="10" cy="10" r="2"></circle>
              <circle cx="14" cy="10" r="2"></circle>
              <path d="M9 16h6"></path>
            </svg>
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
                    ×
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
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9333ea" strokeWidth="2">
                              <rect x="6" y="4" width="12" height="16" rx="2"></rect>
                              <circle cx="10" cy="10" r="2"></circle>
                              <circle cx="14" cy="10" r="2"></circle>
                              <path d="M9 16h6"></path>
                            </svg>
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
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                          <line x1="12" y1="5" x2="12" y2="19"></line>
                          <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
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
