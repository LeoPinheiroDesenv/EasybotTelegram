// Configuração do Express (middlewares, registro de rotas)
const express = require('express');
const cors = require('cors');
const dotenv = require('dotenv');

dotenv.config();

const app = express();

// Inicializa todos os bots ativos ao iniciar o servidor
const telegramService = require('./services/telegramService');
telegramService.initializeAllBots().then(result => {
  console.log('Telegram bots initialization:', result.message);
}).catch(error => {
  console.error('Erro ao inicializar bots:', error);
});

// Middleware CORS - Configuração permissiva para desenvolvimento local
app.use((req, res, next) => {
  const origin = req.headers.origin;
  
  // Permitir qualquer origem local em desenvolvimento
  if (!origin || origin.startsWith('http://localhost') || origin.startsWith('http://127.0.0.1')) {
    res.header('Access-Control-Allow-Origin', origin || '*');
    res.header('Access-Control-Allow-Credentials', 'true');
    res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
    res.header('Access-Control-Expose-Headers', 'Authorization');
    
    // Responder imediatamente para requisições OPTIONS (preflight)
    if (req.method === 'OPTIONS') {
      return res.sendStatus(200);
    }
  }
  
  next();
});

// Configuração adicional do CORS usando a biblioteca cors
app.use(cors({
  origin: function (origin, callback) {
    // Permitir requisições sem origem (mobile apps, Postman, etc) ou origens locais
    if (!origin || origin.startsWith('http://localhost') || origin.startsWith('http://127.0.0.1')) {
      callback(null, true);
    } else {
      // Em produção, validar origem
      const allowedOrigins = process.env.FRONTEND_URL ? [process.env.FRONTEND_URL] : [];
      if (allowedOrigins.indexOf(origin) !== -1) {
        callback(null, true);
      } else {
        callback(null, true); // Permitir em desenvolvimento
      }
    }
  },
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],
  exposedHeaders: ['Authorization'],
  optionsSuccessStatus: 200
}));

// Webhook do Stripe precisa do body raw (antes do express.json())
app.post('/api/payments/webhook/stripe', 
  express.raw({ type: 'application/json' }), 
  require('./controllers/paymentController').stripeWebhook
);

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Webhook do Mercado Pago (depois do express.json())
app.post('/api/payments/webhook/mercadopago', 
  require('./controllers/paymentController').mercadoPagoWebhook
);

// Request logger middleware
const logger = require('./middlewares/logger');
app.use(logger.requestLogger());

// Routes
app.use('/api/auth', require('./routes/auth'));
app.use('/api/users', require('./routes/userRoutes'));
app.use('/api/bots', require('./routes/botRoutes'));
app.use('/api/payment-plans', require('./routes/paymentPlanRoutes'));
app.use('/api/payment-cycles', require('./routes/paymentCycleRoutes'));
app.use('/api/payments', require('./routes/paymentRoutes'));
app.use('/api/payment-gateway-configs', require('./routes/paymentGatewayConfigRoutes'));
app.use('/api/contacts', require('./routes/contactRoutes'));
app.use('/api/logs', require('./routes/logRoutes'));

// Health check
app.get('/api/health', (req, res) => {
  res.json({ status: 'OK', message: 'Server is running' });
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(500).json({ 
    error: 'Something went wrong!',
    message: process.env.NODE_ENV === 'development' ? err.message : undefined
  });
});

module.exports = app;

