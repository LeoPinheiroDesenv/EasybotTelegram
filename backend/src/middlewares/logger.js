// Middleware para capturar logs automaticamente
const Log = require('../models/Log');

const logger = {
  async log(level, message, context = {}, req = null, details = null) {
    try {
      const logData = {
        level,
        message,
        context: context && Object.keys(context).length > 0 ? context : null,
        details: details || null
      };

      if (req) {
        logData.user_id = req.user?.id || null;
        // Tenta obter o IP de várias formas
        logData.ip_address = req.ip || 
                            req.headers['x-forwarded-for']?.split(',')[0] || 
                            req.headers['x-real-ip'] || 
                            req.connection?.remoteAddress || 
                            req.socket?.remoteAddress || 
                            null;
        logData.user_agent = req.get('user-agent') || null;
      }

      await Log.create(logData);
    } catch (error) {
      // Não queremos que erros de log quebrem a aplicação
      console.error('Error saving log:', error.message);
      console.error('Log data that failed:', { level, message, context });
    }
  },

  // Middleware para capturar requisições HTTP
  requestLogger() {
    return (req, res, next) => {
      const start = Date.now();
      
      // Captura a resposta original
      const originalSend = res.send;
      res.send = function(data) {
        const duration = Date.now() - start;
        
        // Log apenas para requisições importantes (não health checks e não logs)
        if (!req.path.includes('/api/health') && !req.path.includes('/api/logs')) {
          const logData = {
            method: req.method,
            path: req.path,
            statusCode: res.statusCode,
            duration: `${duration}ms`
          };

          // Captura detalhes completos da requisição
          const details = {
            request: {
              method: req.method,
              url: req.url,
              path: req.path,
              query: req.query || {},
              params: req.params || {},
              headers: {
                'content-type': req.get('content-type'),
                'accept': req.get('accept'),
                'authorization': req.get('authorization') ? 'Bearer ***' : null,
                'user-agent': req.get('user-agent'),
                'referer': req.get('referer'),
                'origin': req.get('origin'),
                'x-forwarded-for': req.get('x-forwarded-for'),
                'x-real-ip': req.get('x-real-ip')
              },
              body: req.body && Object.keys(req.body).length > 0 ? req.body : null,
              ip: req.ip || 
                  req.headers['x-forwarded-for']?.split(',')[0] || 
                  req.headers['x-real-ip'] || 
                  req.connection?.remoteAddress || 
                  req.socket?.remoteAddress || 
                  null
            },
            response: {
              statusCode: res.statusCode,
              statusMessage: res.statusMessage,
              duration: `${duration}ms`,
              headers: {
                'content-type': res.get('content-type'),
                'content-length': res.get('content-length')
              },
              body: null // Não capturamos o body da resposta por questões de performance e privacidade
            },
            user: req.user ? {
              id: req.user.id,
              email: req.user.email,
              role: req.user.role
            } : null,
            timestamp: new Date().toISOString()
          };

          let level = 'info';
          if (res.statusCode >= 500) {
            level = 'error';
          } else if (res.statusCode >= 400) {
            level = 'warn';
          }

          // Não espera pela conclusão do log para não bloquear a resposta
          logger.log(level, `${req.method} ${req.path} - ${res.statusCode}`, logData, req, details).catch(err => {
            // Ignora erros de log para não quebrar a aplicação
            console.error('Error logging request:', err.message || err);
          });
        }

        originalSend.call(this, data);
      };

      next();
    };
  }
};

module.exports = logger;

