import axios from 'axios';

// URL da API - deve ser definida no arquivo .env na raiz do frontend
// Em desenvolvimento, usa o proxy do React (setupProxy.js)
// Em produção, usa a URL completa do .env
const isDevelopment = process.env.NODE_ENV === 'development';
let API_URL = isDevelopment 
  ? '/api'  // Usa o proxy do React em desenvolvimento
  : (process.env.REACT_APP_API_URL || (() => {
      const error = 'REACT_APP_API_URL não está configurada. Verifique o arquivo .env na pasta frontend.';
      console.error(error);
      throw new Error(error);
    })());

// Em produção, se a página está em HTTPS mas a API está configurada como HTTP, força HTTPS
if (!isDevelopment && typeof window !== 'undefined') {
  const isPageHTTPS = window.location.protocol === 'https:';
  if (isPageHTTPS && API_URL.startsWith('http://')) {
    console.warn('Página está em HTTPS mas API está configurada como HTTP. Convertendo para HTTPS...');
    API_URL = API_URL.replace('http://', 'https://');
  }
}

const api = axios.create({
  baseURL: API_URL,
  timeout: 30000, // Aumentado para 30 segundos para evitar timeouts
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
}, (error) => {
  console.error('Request error:', error);
  return Promise.reject(error);
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response) {
      // Server responded with error status
      console.error('API Error:', error.response.status, error.response.data);
      
      // Se não autorizado, limpar token e redirecionar
      if (error.response.status === 401) {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        window.location.href = '/login';
      }
    } else if (error.request) {
      // Request was made but no response received
      console.error('Network Error:', error.request);
      console.error('Request URL:', error.config?.url);
      console.error('Request Method:', error.config?.method);
      
      // Verifica se é um erro de timeout
      if (error.code === 'ECONNABORTED' || error.message.includes('timeout')) {
        error.message = 'Tempo de espera esgotado. O servidor está demorando para responder. Tente novamente.';
      } else if (error.message.includes('504') || error.message.includes('Gateway Timeout')) {
        error.message = 'Erro de gateway timeout. Verifique se o backend está rodando e acessível.';
      } else {
        error.message = 'Não foi possível conectar ao servidor. Verifique se o backend está rodando.';
      }
    } else {
      // Something else happened
      console.error('Error:', error.message);
      console.error('Error Code:', error.code);
    }
    return Promise.reject(error);
  }
);

export default api;

