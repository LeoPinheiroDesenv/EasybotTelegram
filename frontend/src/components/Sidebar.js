import React, { useContext, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { AuthContext } from '../contexts/AuthContext';
import './Sidebar.css';

const Sidebar = ({ isOpen, onClose }) => {
  const location = useLocation();
  const navigate = useNavigate();
  const { isAdmin, logout } = useContext(AuthContext);
  const [botMenuOpen, setBotMenuOpen] = useState(location.pathname.startsWith('/bot'));
  const [resultsMenuOpen, setResultsMenuOpen] = useState(location.pathname.startsWith('/results'));
  const [marketingMenuOpen, setMarketingMenuOpen] = useState(location.pathname.startsWith('/marketing'));
  const [settingsMenuOpen, setSettingsMenuOpen] = useState(location.pathname.startsWith('/settings'));
  
  // Auto-expand bot menu when on a submenu page
  React.useEffect(() => {
    if (location.pathname.startsWith('/bot') && !location.pathname.match(/^\/bot\/create$/)) {
      setBotMenuOpen(true);
    }
    if (location.pathname.startsWith('/marketing')) {
      setMarketingMenuOpen(true);
    }
    if (location.pathname.startsWith('/results')) {
      setResultsMenuOpen(true);
    }
    if (location.pathname.startsWith('/settings')) {
      setSettingsMenuOpen(true);
    }
  }, [location.pathname]);

  const botSubmenuItems = [
    { path: '/bot/create', label: 'Criar novo bot' },
    { path: '/bot/update', label: 'Atualizar bot', requiresBot: true },
    { path: '/bot/welcome', label: 'Mensagem de boas-vindas', requiresBot: true },
    { path: '/bot/payment-plans', label: 'Planos de pagamento', requiresBot: true },
    { path: '/bot/redirect', label: 'Botões de redirecionamento', requiresBot: true },
    { path: '/bot/administrators', label: 'Administradores', requiresBot: true },
    { path: '/bot/groups', label: 'Grupos e Canais', requiresBot: true },
  ];

  const getIcon = (iconType) => {
    const icons = {
      grid: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <rect x="3" y="3" width="7" height="7"></rect>
          <rect x="14" y="3" width="7" height="7"></rect>
          <rect x="3" y="14" width="7" height="7"></rect>
          <rect x="14" y="14" width="7" height="7"></rect>
        </svg>
      ),
      robot: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <rect x="6" y="4" width="12" height="16" rx="2"></rect>
          <circle cx="10" cy="10" r="2"></circle>
          <circle cx="14" cy="10" r="2"></circle>
          <path d="M9 16h6"></path>
        </svg>
      ),
      users: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
      ),
      chevron: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
      ),
      chevronDown: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      ),
      chart: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <line x1="18" y1="20" x2="18" y2="10"></line>
          <line x1="12" y1="20" x2="12" y2="4"></line>
          <line x1="6" y1="20" x2="6" y2="14"></line>
        </svg>
      ),
      contacts: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
      ),
      megaphone: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M3 11c0-1.1.9-2 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4z"></path>
          <path d="M7 11v4a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2v-4"></path>
          <path d="M13 11l4-8 4 8v6a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-6z"></path>
          <path d="M21 6l-2 4"></path>
        </svg>
      ),
      fileText: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
      ),
      settings: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"></path>
        </svg>
      )
    };
    return icons[iconType] || null;
  };

  // Check if we're on a specific bot submenu page (not just /bot/create)
  const isBotSubmenuActive = location.pathname.startsWith('/bot') && 
    !location.pathname.match(/^\/bot\/create$/);
  const isBotActive = location.pathname === '/bot/create';
  const isResultsActive = location.pathname.startsWith('/results');
  const isMarketingActive = location.pathname.startsWith('/marketing');

  const handleLogout = () => {
    logout();
    localStorage.removeItem('selectedBotId');
    navigate('/login');
  };

  return (
    <div className={`sidebar ${isOpen ? 'open' : ''}`}>
      <div className="sidebar-content">
        <div className="sidebar-logo">
          <div className="logo-circles">
            <span className="circle circle-1"></span>
            <span className="circle circle-2"></span>
            <span className="circle circle-3"></span>
          </div>
          <div className="logo-text">
            <div className="logo-title">Easy</div>
            <div className="logo-subtitle">
              {location.pathname === '/bot/create' ? 'Criar novo bot' : 
               location.pathname.startsWith('/bot/update') ? 'Atualizar bot' : 
               location.pathname.startsWith('/bot/welcome') ? 'Mensagem de boas-vindas' :
               location.pathname.startsWith('/bot/payment-plans') ? 'Planos de pagamento' :
               location.pathname.startsWith('/bot/redirect') ? 'Botões de redirecionamento' :
               location.pathname.startsWith('/bot/administrators') ? 'Administradores' :
               location.pathname.startsWith('/bot/groups') ? 'Grupos e Canais' :
               location.pathname.startsWith('/marketing/alerts') ? 'Alertas' :
               location.pathname.startsWith('/marketing/downsell') ? 'Downsell' :
               location.pathname.startsWith('/marketing') ? 'Marketing' :
               location.pathname === '/logs' ? 'Logs' :
               'Página inicial'}
            </div>
          </div>
        </div>

        <div className="sidebar-section">
          <div className="sidebar-title">MENU</div>
          
          <Link
            to="/"
            className={`sidebar-item ${location.pathname === '/' ? 'active' : ''}`}
            onClick={onClose}
          >
            <span className="sidebar-icon">{getIcon('grid')}</span>
            <span className="sidebar-label">Dashboard</span>
          </Link>

          <div className="sidebar-menu-group">
            <div
              className={`sidebar-item ${isBotActive && !isBotSubmenuActive ? 'active' : ''} ${(botMenuOpen || isBotSubmenuActive) ? 'expanded' : ''}`}
              onClick={() => setBotMenuOpen(!botMenuOpen)}
            >
              <span className="sidebar-icon">{getIcon('robot')}</span>
              <span className="sidebar-label">Bot</span>
              <span className="sidebar-chevron">
                {botMenuOpen ? getIcon('chevronDown') : getIcon('chevron')}
              </span>
            </div>
            
            {botMenuOpen && (
              <div className="sidebar-submenu">
                {botSubmenuItems.map((item) => {
                  // For items that require a bot, check if we have one selected
                  let path = item.path;
                  if (item.requiresBot) {
                    const selectedBotId = localStorage.getItem('selectedBotId');
                    if (selectedBotId) {
                      if (item.path === '/bot/update') {
                        path = `/bot/update/${selectedBotId}`;
                      } else if (item.path === '/bot/welcome') {
                        path = `/bot/welcome?botId=${selectedBotId}`;
                      } else if (item.path === '/bot/payment-plans') {
                        path = `/bot/payment-plans?botId=${selectedBotId}`;
                      } else if (item.path === '/bot/redirect') {
                        path = `/bot/redirect?botId=${selectedBotId}`;
                      } else if (item.path === '/bot/administrators') {
                        path = `/bot/administrators?botId=${selectedBotId}`;
                      } else if (item.path === '/bot/groups') {
                        path = `/bot/groups?botId=${selectedBotId}`;
                      }
                    } else {
                      // If no bot selected, navigate to dashboard first
                      path = '/';
                    }
                  }
                  
                  // More precise active detection
                  let isActive = false;
                  if (item.requiresBot) {
                    // For items with requiresBot, check if pathname matches exactly or starts with the path
                    if (item.path === '/bot/welcome') {
                      isActive = location.pathname === '/bot/welcome' || 
                                 (location.pathname.startsWith('/bot/welcome') && location.search.includes('botId'));
                    } else if (item.path === '/bot/redirect') {
                      isActive = location.pathname === '/bot/redirect' || 
                                 (location.pathname.startsWith('/bot/redirect') && location.search.includes('botId'));
                    } else if (item.path === '/bot/administrators') {
                      isActive = location.pathname === '/bot/administrators' || 
                                 (location.pathname.startsWith('/bot/administrators') && location.search.includes('botId'));
                    } else if (item.path === '/bot/groups') {
                      isActive = location.pathname === '/bot/groups' || 
                                 (location.pathname.startsWith('/bot/groups') && location.search.includes('botId'));
                    } else {
                      isActive = location.pathname.startsWith(item.path) && 
                                 location.pathname !== '/bot/create';
                    }
                  } else {
                    isActive = location.pathname === item.path;
                  }
                  
                  return (
                    <Link
                      key={item.path}
                      to={path}
                      className={`sidebar-submenu-item ${isActive ? 'active' : ''}`}
                      onClick={(e) => {
                        if (item.requiresBot && !localStorage.getItem('selectedBotId')) {
                          e.preventDefault();
                          alert('Por favor, selecione um bot primeiro.');
                        } else {
                          onClose();
                        }
                      }}
                    >
                      {item.label}
                    </Link>
                  );
                })}
              </div>
            )}
          </div>

          <div className="sidebar-menu-group">
            <div
              className={`sidebar-item ${isResultsActive ? 'active' : ''} ${resultsMenuOpen ? 'expanded' : ''}`}
              onClick={() => setResultsMenuOpen(!resultsMenuOpen)}
            >
              <span className="sidebar-icon">{getIcon('chart')}</span>
              <span className="sidebar-label">Resultados</span>
              <span className="sidebar-chevron">
                {resultsMenuOpen ? getIcon('chevronDown') : getIcon('chevron')}
              </span>
            </div>
            
            {resultsMenuOpen && (
              <div className="sidebar-submenu">
                <Link
                  to="/results/contacts"
                  className={`sidebar-submenu-item ${location.pathname === '/results/contacts' ? 'active' : ''}`}
                  onClick={onClose}
                >
                  Contatos
                </Link>
              </div>
            )}
          </div>

          <div className="sidebar-menu-group">
            <div
              className={`sidebar-item ${isMarketingActive ? 'active' : ''} ${marketingMenuOpen ? 'expanded' : ''}`}
              onClick={() => setMarketingMenuOpen(!marketingMenuOpen)}
            >
              <span className="sidebar-icon">{getIcon('megaphone')}</span>
              <span className="sidebar-label">Marketing</span>
              <span className="sidebar-chevron">
                {marketingMenuOpen ? getIcon('chevronDown') : getIcon('chevron')}
              </span>
            </div>
            
            {marketingMenuOpen && (
              <div className="sidebar-submenu">
                <Link
                  to="/marketing/alerts"
                  className={`sidebar-submenu-item ${location.pathname === '/marketing/alerts' ? 'active' : ''}`}
                  onClick={onClose}
                >
                  Alertas
                </Link>
                <Link
                  to="/marketing/downsell"
                  className={`sidebar-submenu-item ${location.pathname === '/marketing/downsell' ? 'active' : ''}`}
                  onClick={onClose}
                >
                  Downsell
                </Link>
              </div>
            )}
          </div>
        </div>

        <div className="sidebar-section">
          <div className="sidebar-title">CONFIGURAÇÕES</div>
          {isAdmin && (
            <div className="sidebar-menu-group">
              <div
                className={`sidebar-item ${location.pathname.startsWith('/settings') ? 'active' : ''} ${settingsMenuOpen ? 'expanded' : ''}`}
                onClick={() => setSettingsMenuOpen(!settingsMenuOpen)}
              >
                <span className="sidebar-icon">{getIcon('settings')}</span>
                <span className="sidebar-label">Configurações</span>
                <span className="sidebar-chevron">
                  {settingsMenuOpen ? getIcon('chevronDown') : getIcon('chevron')}
                </span>
              </div>
              
              {settingsMenuOpen && (
                <div className="sidebar-submenu">
                  <Link
                    to="/settings/payment-cycles"
                    className={`sidebar-submenu-item ${location.pathname === '/settings/payment-cycles' ? 'active' : ''}`}
                    onClick={onClose}
                  >
                    Ciclos de Pagamento
                  </Link>
                  <Link
                    to="/settings/payment-gateways"
                    className={`sidebar-submenu-item ${location.pathname === '/settings/payment-gateways' ? 'active' : ''}`}
                    onClick={onClose}
                  >
                    Gateways de Pagamento
                  </Link>
                </div>
              )}
            </div>
          )}
        </div>

        <div className="sidebar-section">
          <div className="sidebar-title">OUTROS</div>
          {isAdmin && (
            <>
              <Link
                to="/users"
                className={`sidebar-item ${location.pathname === '/users' ? 'active' : ''}`}
                onClick={onClose}
              >
                <span className="sidebar-icon">{getIcon('users')}</span>
                <span className="sidebar-label">Usuários</span>
              </Link>
              <Link
                to="/logs"
                className={`sidebar-item ${location.pathname === '/logs' ? 'active' : ''}`}
                onClick={onClose}
              >
                <span className="sidebar-icon">{getIcon('fileText')}</span>
                <span className="sidebar-label">Logs</span>
              </Link>
            </>
          )}
        </div>

        <div className="sidebar-footer">
          <div className="sidebar-billing">
            <div className="billing-header">
              <span>Faturamento</span>
              <span className="billing-value">R$ 3.935</span>
            </div>
            <div className="billing-progress">
              <div className="billing-progress-bar" style={{ width: '3%' }}></div>
            </div>
            <div className="billing-limit">R$ 100.000</div>
          </div>

          

          <button
            onClick={handleLogout}
            className="sidebar-logout-btn"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Sair</span>
          </button>
        </div>
      </div>
    </div>
  );
};

export default Sidebar;
