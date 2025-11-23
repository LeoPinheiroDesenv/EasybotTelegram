const { createProxyMiddleware } = require('http-proxy-middleware');

module.exports = function(app) {
  // URL do backend
  // Em desenvolvimento com Docker: usar o nome do serviço 'backend' na rede Docker
  // Em desenvolvimento local: usar localhost
  // Em produção: usar REACT_APP_API_URL (mas em produção não usa proxy, usa URL direta)
  
  // Se REACT_APP_API_URL está definida e não é localhost, usar ela (produção)
  // Caso contrário, em desenvolvimento, tentar usar o nome do serviço Docker primeiro
  let backendUrl;
  
  if (process.env.REACT_APP_API_URL && !process.env.REACT_APP_API_URL.includes('localhost')) {
    // Produção - usar URL configurada
    backendUrl = process.env.REACT_APP_API_URL.replace('/api', '');
  } else if (process.env.REACT_APP_DOCKER === 'true' || process.env.NODE_ENV === 'development') {
    // Desenvolvimento - tentar usar nome do serviço Docker primeiro
    // Se não funcionar, pode ser que esteja rodando localmente
    backendUrl = process.env.REACT_APP_BACKEND_URL || 'http://backend:8000';
  } else {
    // Fallback para localhost
    backendUrl = 'http://localhost:8000';
  }
  
  console.log(`[Proxy] Configurado para: ${backendUrl}`);
  console.log(`[Proxy] NODE_ENV: ${process.env.NODE_ENV}`);
  console.log(`[Proxy] REACT_APP_API_URL: ${process.env.REACT_APP_API_URL || 'não definida'}`);
  
  // Proxy apenas para requisições da API
  app.use(
    '/api',
    createProxyMiddleware({
      target: backendUrl,
      changeOrigin: true,
      logLevel: process.env.NODE_ENV === 'development' ? 'debug' : 'info',
      timeout: 30000, // 30 segundos de timeout
      proxyTimeout: 30000, // 30 segundos de timeout do proxy
      onError: (err, req, res) => {
        console.error('[Proxy Error]', err.message);
        console.error('[Proxy Error] Target:', backendUrl);
        console.error('[Proxy Error] Request URL:', req.url);
        console.error('[Proxy Error] Error Code:', err.code);
        
        // Se não conseguir conectar, tenta localhost como fallback (apenas em desenvolvimento)
        if (err.code === 'ECONNREFUSED' && process.env.NODE_ENV === 'development' && backendUrl.includes('backend')) {
          console.warn('[Proxy] Tentando fallback para localhost:8000');
        }
      },
      onProxyReq: (proxyReq, req, res) => {
        if (process.env.NODE_ENV === 'development') {
          console.log(`[Proxy] ${req.method} ${req.url} -> ${backendUrl}${req.url}`);
        }
      },
      onProxyRes: (proxyRes, req, res) => {
        if (process.env.NODE_ENV === 'development') {
          console.log(`[Proxy] Response: ${proxyRes.statusCode} for ${req.method} ${req.url}`);
        }
      }
    })
  );
  
  // Não fazer proxy de arquivos estáticos como favicon.ico
  // O React já serve esses arquivos automaticamente
};

