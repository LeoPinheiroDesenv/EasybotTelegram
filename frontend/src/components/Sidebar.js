import React, { useContext, useState, useEffect } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { 
  faThLarge,
  faRobot,
  faUsers,
  faChevronRight,
  faChevronDown,
  faChartBar,
  faUserFriends,
  faBullhorn,
  faFileAlt,
  faCog,
  faSignOutAlt
} from '@fortawesome/free-solid-svg-icons';
import { AuthContext } from '../contexts/AuthContext';
import billingService from '../services/billingService';
import './Sidebar.css';

const Sidebar = ({ isOpen, onClose }) => {
  const location = useLocation();
  const navigate = useNavigate();
  const { isAdmin, logout, user } = useContext(AuthContext);
  const [botMenuOpen, setBotMenuOpen] = useState(location.pathname.startsWith('/bot'));
  const [resultsMenuOpen, setResultsMenuOpen] = useState(location.pathname.startsWith('/results'));
  const [marketingMenuOpen, setMarketingMenuOpen] = useState(location.pathname.startsWith('/marketing'));
  const [settingsMenuOpen, setSettingsMenuOpen] = useState(
    location.pathname.startsWith('/settings') || 
    location.pathname === '/users' || 
    location.pathname === '/user-groups' || 
    location.pathname === '/logs'
  );
  const [monthlyBilling, setMonthlyBilling] = useState({ total: 0, transaction_count: 0 });
  const [loadingBilling, setLoadingBilling] = useState(true);

  // Verifica se o usuário tem acesso a um menu específico
  const hasMenuAccess = (menuName) => {
    // Super admin tem acesso a tudo
    if (user?.user_type === 'super_admin') {
      return true;
    }
    
    const accessibleMenus = user?.accessible_menus || [];
    return accessibleMenus.includes('*') || accessibleMenus.includes(menuName);
  };
  
  // Auto-expand menus when on a submenu page
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
    // Expande menu de Configurações para rotas de settings e também para Usuários, Grupos de Usuários e Logs
    if (location.pathname.startsWith('/settings') || 
        location.pathname === '/users' || 
        location.pathname === '/user-groups' || 
        location.pathname === '/logs') {
      setSettingsMenuOpen(true);
    }
  }, [location.pathname]);

  // Carrega faturamento mensal
  useEffect(() => {
    const loadBilling = async () => {
      try {
        setLoadingBilling(true);
        const billing = await billingService.getMonthlyBilling();
        setMonthlyBilling(billing);
      } catch (err) {
        console.error('Erro ao carregar faturamento:', err);
        // Mantém valores padrão em caso de erro
        setMonthlyBilling({ total: 0, transaction_count: 0 });
      } finally {
        setLoadingBilling(false);
      }
    };

    loadBilling();
    // Atualiza a cada 5 minutos
    const interval = setInterval(loadBilling, 5 * 60 * 1000);
    return () => clearInterval(interval);
  }, []);

  const botSubmenuItems = [
    { path: '/bot/create', label: 'Criar novo bot' },
    { path: '/bot/update', label: 'Atualizar bot', requiresBot: true },
    { path: '/bot/welcome', label: 'Mensagem de boas-vindas', requiresBot: true },
    { path: '/bot/payment-plans', label: 'Planos de pagamento', requiresBot: true },
    { path: '/bot/redirect', label: 'Botões de redirecionamento', requiresBot: true },
    { path: '/bot/administrators', label: 'Administradores', requiresBot: true },
    { path: '/bot/groups', label: 'Grupos e Canais', requiresBot: true },
  ];

  const isBillingActive = location.pathname === '/billing';

  const getIcon = (iconType) => {
    const icons = {
      grid: <FontAwesomeIcon icon={faThLarge} />,
      robot: <FontAwesomeIcon icon={faRobot} />,
      users: <FontAwesomeIcon icon={faUsers} />,
      chevron: <FontAwesomeIcon icon={faChevronRight} />,
      chevronDown: <FontAwesomeIcon icon={faChevronDown} />,
      chart: <FontAwesomeIcon icon={faChartBar} />,
      contacts: <FontAwesomeIcon icon={faUserFriends} />,
      megaphone: <FontAwesomeIcon icon={faBullhorn} />,
      fileText: <FontAwesomeIcon icon={faFileAlt} />,
      settings: <FontAwesomeIcon icon={faCog} />
    };
    return icons[iconType] || null;
  };

  // Check if we're on a specific bot submenu page (not just /bot/create)
  // Detecta qualquer rota de bot exceto /bot/create
  const isBotSubmenuActive = location.pathname.startsWith('/bot') && 
    !location.pathname.match(/^\/bot\/create$/);
  // Item principal "Bot" fica ativo quando qualquer submenu está ativo
  const isBotActive = isBotSubmenuActive;
  const isResultsActive = location.pathname.startsWith('/results');
  const isMarketingActive = location.pathname.startsWith('/marketing');
  // Menu de Configurações está ativo para /settings, /users, /user-groups e /logs
  const isSettingsActive = location.pathname.startsWith('/settings') || 
    location.pathname === '/users' || 
    location.pathname === '/user-groups' || 
    location.pathname === '/logs';

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
               location.pathname === '/users' ? 'Usuários' :
               location.pathname === '/user-groups' ? 'Grupos de Usuários' :
               location.pathname === '/logs' ? 'Logs' :
               location.pathname.startsWith('/settings/payment-cycles') ? 'Ciclos de Pagamento' :
               location.pathname.startsWith('/settings/payment-gateways') ? 'Gateways de Pagamento' :
               'Página inicial'}
            </div>
          </div>
        </div>

        <div className="sidebar-section">
          <div className="sidebar-title">MENU</div>
          
          {hasMenuAccess('dashboard') && (
            <Link
              to="/"
              className={`sidebar-item ${location.pathname === '/' ? 'active' : ''}`}
              onClick={onClose}
            >
              <span className="sidebar-icon">{getIcon('grid')}</span>
              <span className="sidebar-label">Dashboard</span>
            </Link>
          )}

          {hasMenuAccess('billing') && (
            <Link
              to="/billing"
              className={`sidebar-item ${isBillingActive ? 'active' : ''}`}
              onClick={onClose}
            >
              <span className="sidebar-icon">{getIcon('chart')}</span>
              <span className="sidebar-label">Faturamento</span>
            </Link>
          )}

          {hasMenuAccess('bot') && (
            <div className="sidebar-menu-group">
            <div
              className={`sidebar-item ${isBotSubmenuActive ? 'active' : ''} ${(botMenuOpen || isBotSubmenuActive) ? 'expanded' : ''}`}
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
          )}

          {hasMenuAccess('results') && (
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
          )}

          {hasMenuAccess('marketing') && (
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
          )}
        </div>

        <div className="sidebar-section">
          <div className="sidebar-title">CONFIGURAÇÕES</div>
          {isAdmin && hasMenuAccess('settings') && (
            <div className="sidebar-menu-group">
              <div
                className={`sidebar-item ${isSettingsActive ? 'active' : ''} ${settingsMenuOpen ? 'expanded' : ''}`}
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
                  {hasMenuAccess('settings') && (
                    <>
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
                    </>
                  )}
                  {user?.user_type === 'super_admin' && (
                    <>
                      <Link
                        to="/users"
                        className={`sidebar-submenu-item ${location.pathname === '/users' ? 'active' : ''}`}
                        onClick={onClose}
                      >
                        Usuários
                      </Link>
                      <Link
                        to="/user-groups"
                        className={`sidebar-submenu-item ${location.pathname === '/user-groups' ? 'active' : ''}`}
                        onClick={onClose}
                      >
                        Grupos de Usuários
                      </Link>
                      <Link
                        to="/logs"
                        className={`sidebar-submenu-item ${location.pathname === '/logs' ? 'active' : ''}`}
                        onClick={onClose}
                      >
                        Logs
                      </Link>
                    </>
                  )}
                </div>
              )}
            </div>
          )}
        </div>

        

        <div className="sidebar-footer">
          <Link to="/billing" className="sidebar-billing-link" onClick={onClose}>
            <div className="sidebar-billing">
              <div className="billing-header">
                <span>Faturamento</span>
                <span className="billing-value">
                  {loadingBilling ? '...' : 
                    new Intl.NumberFormat('pt-BR', {
                      style: 'currency',
                      currency: 'BRL',
                      minimumFractionDigits: 2,
                      maximumFractionDigits: 2
                    }).format(monthlyBilling.total || 0)
                  }
                </span>
              </div>
              <div className="billing-progress">
                <div 
                  className="billing-progress-bar" 
                  style={{ 
                    width: `${Math.min((monthlyBilling.total / 100000) * 100, 100)}%` 
                  }}
                ></div>
              </div>
              <div className="billing-limit">
                {monthlyBilling.transaction_count > 0 && (
                  <span className="billing-transactions">
                    {monthlyBilling.transaction_count} transação{monthlyBilling.transaction_count !== 1 ? 'ões' : ''} este mês
                  </span>
                )}
              </div>
            </div>
          </Link>

          

          <button
            onClick={handleLogout}
            className="sidebar-logout-btn"
          >
            <FontAwesomeIcon icon={faSignOutAlt} />
            <span>Sair</span>
          </button>
        </div>
      </div>
    </div>
  );
};

export default Sidebar;
