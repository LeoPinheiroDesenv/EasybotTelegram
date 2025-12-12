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
  faSignOutAlt,
  faCreditCard
} from '@fortawesome/free-solid-svg-icons';
import { AuthContext } from '../contexts/AuthContext';
import billingService from '../services/billingService';
import useAlert from '../hooks/useAlert';
import './Sidebar.css';

const Sidebar = ({ isOpen, onClose }) => {
  const { alert, DialogComponent: AlertDialog } = useAlert();
  const location = useLocation();
  const navigate = useNavigate();
  const { isAdmin, logout, user, isSuperAdmin } = useContext(AuthContext);
  const [botMenuOpen, setBotMenuOpen] = useState(location.pathname.startsWith('/bot'));
  const [resultsMenuOpen, setResultsMenuOpen] = useState(location.pathname.startsWith('/results'));
  const [marketingMenuOpen, setMarketingMenuOpen] = useState(location.pathname.startsWith('/marketing'));
  const [settingsMenuOpen, setSettingsMenuOpen] = useState(
    location.pathname.startsWith('/settings') || 
    location.pathname === '/users' || 
    location.pathname === '/user-groups' || 
    location.pathname === '/logs' ||
    location.pathname === '/ftp' ||
    location.pathname === '/settings/artisan' ||
    location.pathname === '/settings/cron-jobs' ||
    location.pathname === '/settings/laravel-logs' ||
    location.pathname === '/settings/profile' ||
    location.pathname.includes('/botfather')
  );
  // eslint-disable-next-line no-unused-vars
  const [monthlyBilling, setMonthlyBilling] = useState({ total: 0, transaction_count: 0 });
  // eslint-disable-next-line no-unused-vars
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
    // Expande menu de Configurações para rotas de settings e também para Usuários, Grupos de Usuários, Logs e FTP
    if (location.pathname.startsWith('/settings') || 
        location.pathname === '/users' || 
        location.pathname === '/user-groups' || 
        location.pathname === '/logs' ||
        location.pathname === '/ftp' ||
        location.pathname.includes('/botfather')) {
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
    { path: '/bot/list', label: 'Listar bots' },
    { path: '/bot/create', label: 'Criar novo bot' },
    { path: '/bot/manage', label: 'Gerenciar Bot', requiresBot: true },
  ];

  const isBillingActive = location.pathname === '/billing';
  const isPaymentStatusActive = location.pathname.startsWith('/payment-status');

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
  const isResultsActive = location.pathname.startsWith('/results');
  const isMarketingActive = location.pathname.startsWith('/marketing');
  // Menu de Configurações está ativo para /settings, /users, /user-groups, /logs, /ftp e /botfather
  const isSettingsActive = location.pathname.startsWith('/settings') || 
    location.pathname === '/users' || 
    location.pathname === '/user-groups' || 
    location.pathname === '/logs' ||
    location.pathname === '/ftp' ||
    location.pathname.includes('/botfather');

  const handleLogout = () => {
    logout();
    localStorage.removeItem('selectedBotId');
    navigate('/login');
  };

  return (
    <>
      <AlertDialog />
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
               location.pathname.includes('/bot/manage/') || 
               location.pathname.startsWith('/bot/welcome/') ||
               location.pathname.startsWith('/bot/payment-plans/') ||
               location.pathname.startsWith('/bot/redirect/') ||
               location.pathname.startsWith('/bot/commands/') ||
               location.pathname.startsWith('/bot/administrators/') ||
               location.pathname.startsWith('/bot/telegram-groups/') ||
               (location.pathname.includes('/bot/') && location.pathname.includes('/botfather')) ? 'Gerenciar Bot' :
               location.pathname.startsWith('/marketing/alerts') ? 'Alertas' :
               location.pathname.startsWith('/marketing/downsell') ? 'Downsell' :
               location.pathname.startsWith('/marketing') ? 'Marketing' :
               location.pathname === '/users' ? 'Usuários' :
               location.pathname === '/user-groups' ? 'Grupos de Usuários' :
               location.pathname === '/logs' ? 'Logs' :
               location.pathname.startsWith('/settings/payment-cycles') ? 'Ciclos de Pagamento' :
               location.pathname.startsWith('/settings/payment-gateways') ? 'Gateways de Pagamento' :
               location.pathname.startsWith('/settings/security') ? 'Segurança (2FA)' :
               location.pathname.startsWith('/settings/storage') ? 'Storage' :
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

          {hasMenuAccess('billing') && (
            <Link
              to={`/payment-status${localStorage.getItem('selectedBotId') ? `/${localStorage.getItem('selectedBotId')}` : ''}`}
              className={`sidebar-item ${isPaymentStatusActive ? 'active' : ''}`}
              onClick={onClose}
            >
              <span className="sidebar-icon">
                <FontAwesomeIcon icon={faCreditCard} />
              </span>
              <span className="sidebar-label">Status de Pagamentos</span>
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
                      if (item.path === '/bot/manage') {
                        path = `/bot/manage/${selectedBotId}/welcome`;
                      }
                    } else {
                      // If no bot selected, navigate to dashboard first
                      path = '/';
                    }
                  }
                  
                  // More precise active detection
                  let isActive = false;
                  if (item.requiresBot) {
                    // For manage bot, check if we're on any manage bot route
                    if (item.path === '/bot/manage') {
                      isActive = location.pathname.includes('/bot/manage/') || 
                                 location.pathname.includes('/bot/welcome/') ||
                                 location.pathname.includes('/bot/payment-plans/') ||
                                 location.pathname.includes('/bot/redirect/') ||
                                 location.pathname.includes('/bot/commands/') ||
                                 location.pathname.includes('/bot/administrators/') ||
                                 location.pathname.includes('/bot/telegram-groups/') ||
                                 (location.pathname.includes('/bot/') && location.pathname.includes('/botfather'));
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
                      onClick={async (e) => {
                        if (item.requiresBot && !localStorage.getItem('selectedBotId')) {
                          e.preventDefault();
                          await alert('Por favor, selecione um bot primeiro.', 'Atenção', 'info');
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
          {/* Link "Meu Perfil" sempre visível para todos os usuários autenticados */}
          <div className="sidebar-menu-group">
            <Link
              to="/settings/profile"
              className={`sidebar-item ${location.pathname === '/settings/profile' ? 'active' : ''}`}
              onClick={onClose}
            >
              <span className="sidebar-icon">{getIcon('settings')}</span>
              <span className="sidebar-label">Meu Perfil</span>
            </Link>
          </div>
          
          {/* Menu de Configurações completo apenas para admins com acesso */}
          {isAdmin && hasMenuAccess('settings') && (
            <div className="sidebar-menu-group">
              <div
                className={`sidebar-item ${isSettingsActive && location.pathname !== '/settings/profile' ? 'active' : ''} ${settingsMenuOpen ? 'expanded' : ''}`}
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
                      <Link
                        to="/settings/security"
                        className={`sidebar-submenu-item ${location.pathname === '/settings/security' ? 'active' : ''}`}
                        onClick={onClose}
                      >
                        Segurança (2FA)
                      </Link>
                      {isSuperAdmin && (
                        <Link
                          to="/settings/storage"
                          className={`sidebar-submenu-item ${location.pathname === '/settings/storage' ? 'active' : ''}`}
                          onClick={onClose}
                        >
                          Storage
                        </Link>
                      )}
                      {/* <Link
                        to="/ftp"
                        className={`sidebar-submenu-item ${location.pathname === '/ftp' ? 'active' : ''}`}
                        onClick={onClose}
                      >
                        Gerenciador FTP
                      </Link> */}
                    </>
                  )}
                  {user?.user_type === 'super_admin' && (
                    <>
                      <Link
                        to="/settings/artisan"
                        className={`sidebar-submenu-item ${location.pathname === '/settings/artisan' ? 'active' : ''}`}
                        onClick={onClose}
                      >
                        Comandos Artisan
                      </Link>
                      <Link
                        to="/settings/cron-jobs"
                        className={`sidebar-submenu-item ${location.pathname === '/settings/cron-jobs' ? 'active' : ''}`}
                        onClick={onClose}
                      >
                        Cron Jobs
                      </Link>
                      <Link
                        to="/settings/laravel-logs"
                        className={`sidebar-submenu-item ${location.pathname === '/settings/laravel-logs' ? 'active' : ''}`}
                        onClick={onClose}
                      >
                        Logs do Laravel
                      </Link>
                    </>
                  )}
                  {/* Admins e super_admin podem ver Usuários */}
                  {isAdmin && (
                    <Link
                      to="/users"
                      className={`sidebar-submenu-item ${location.pathname === '/users' ? 'active' : ''}`}
                      onClick={onClose}
                    >
                      Usuários
                    </Link>
                  )}
                  {/* Apenas super_admin pode ver Grupos de Usuários */}
                  {(user?.user_type === 'super_admin' || user?.user_type === 'admin' || user?.role === 'admin') && (
                    <Link
                      to="/user-groups"
                      className={`sidebar-submenu-item ${location.pathname === '/user-groups' ? 'active' : ''}`}
                      onClick={onClose}
                    >
                      Grupos de Usuários
                    </Link>
                  )}
                  {user?.user_type === 'super_admin' && (
                    <Link
                      to="/logs"
                      className={`sidebar-submenu-item ${location.pathname === '/logs' ? 'active' : ''}`}
                      onClick={onClose}
                    >
                      Logs
                    </Link>
                  )}
                </div>
              )}
            </div>
          )}
        </div>
        </div>

        <div className="sidebar-footer">

          <button
            onClick={handleLogout}
            className="sidebar-logout-btn"
          >
            <FontAwesomeIcon icon={faSignOutAlt} />
            <span>Sair</span>
          </button>
        </div>
      </div>
    </>
  );
};

export default Sidebar;
