import React, { useContext } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { AuthContext } from '../contexts/AuthContext';

/**
 * Mapeamento de rotas para menus
 */
const routeToMenuMap = {
  '/': 'dashboard',
  '/billing': 'billing',
  '/bot/create': 'bot',
  '/bot/update': 'bot',
  '/bot/welcome': 'bot',
  '/bot/payment-plans': 'bot',
  '/bot/redirect': 'bot',
  '/bot/administrators': 'bot',
  '/bot/groups': 'bot',
  '/bot/': 'bot', // Para rotas que começam com /bot/
  '/results/contacts': 'results',
  '/marketing': 'marketing',
  '/marketing/alerts': 'marketing',
  '/marketing/downsell': 'marketing',
  '/settings/payment-cycles': 'settings',
  '/settings/payment-gateways': 'settings',
  '/users': 'settings',
  '/user-groups': 'settings',
  '/logs': 'settings',
};

/**
 * Obtém o nome do menu baseado no path
 */
const getMenuFromPath = (pathname) => {
  // Remove query strings do path
  const pathWithoutQuery = pathname.split('?')[0];
  
  // Remove IDs numéricos do path para normalizar rotas dinâmicas
  const normalizedPath = pathWithoutQuery.replace(/\/\d+/g, '');
  
  // Verifica primeiro por correspondência exata
  if (routeToMenuMap[normalizedPath]) {
    return routeToMenuMap[normalizedPath];
  }
  
  // Verifica se começa com algum prefixo conhecido (ordem específica primeiro)
  if (normalizedPath.startsWith('/bot/update') || normalizedPath === '/bot/update') {
    return 'bot';
  }
  if (normalizedPath.startsWith('/bot/')) {
    return 'bot';
  }
  if (normalizedPath.startsWith('/results/')) {
    return 'results';
  }
  if (normalizedPath.startsWith('/marketing/')) {
    return 'marketing';
  }
  if (normalizedPath.startsWith('/settings/')) {
    return 'settings';
  }
  
  // Verifica correspondência exata novamente (caso tenha removido algo importante)
  if (routeToMenuMap[pathWithoutQuery]) {
    return routeToMenuMap[pathWithoutQuery];
  }
  
  return null;
};

/**
 * Componente de rota protegida que verifica permissões de menu
 */
const ProtectedRoute = ({ children }) => {
  const { isAuthenticated, loading, user } = useContext(AuthContext);
  const location = useLocation();

  if (loading) {
    return <div>Carregando...</div>;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  // Super admin tem acesso a tudo
  if (user?.user_type === 'super_admin') {
    return children;
  }

  // Se não tem grupo, não tem acesso (exceto se for super admin, já tratado acima)
  if (!user?.user_group_id) {
    return (
      <div style={{ padding: '20px', textAlign: 'center' }}>
        <h1>Acesso Negado</h1>
        <p>Você não tem permissão para acessar esta página.</p>
      </div>
    );
  }

  // Obtém o menu correspondente à rota atual
  const menuName = getMenuFromPath(location.pathname);

  // Se não encontrou o menu, permite acesso (pode ser uma rota que não precisa de permissão)
  if (!menuName) {
    return children;
  }

  // Verifica se o usuário tem acesso ao menu
  const accessibleMenus = user?.accessible_menus || [];
  
  // Se tem acesso a todos os menus (*) ou ao menu específico
  if (accessibleMenus.includes('*') || accessibleMenus.includes(menuName)) {
    return children;
  }

  // Não tem permissão
  return (
    <div style={{ padding: '20px', textAlign: 'center' }}>
      <h1>Acesso Negado</h1>
      <p>Você não tem permissão para acessar esta página.</p>
    </div>
  );
};

export default ProtectedRoute;

