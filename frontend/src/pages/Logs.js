import React, { useState, useEffect, useContext } from 'react';
import { AuthContext } from '../contexts/AuthContext';
import Layout from '../components/Layout';
import logService from '../services/logService';
import './Logs.css';

const Logs = () => {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filters, setFilters] = useState({
    level: '',
    limit: 100,
    offset: 0
  });
  const [total, setTotal] = useState(0);
  const { isAdmin } = useContext(AuthContext);

  useEffect(() => {
    if (isAdmin) {
      loadLogs();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isAdmin, filters]);

  const loadLogs = async () => {
    try {
      setLoading(true);
      const data = await logService.getAllLogs(filters);
      setLogs(data.logs);
      setTotal(data.total);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar logs');
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (e) => {
    const { name, value } = e.target;
    setFilters(prev => ({
      ...prev,
      [name]: value,
      offset: 0 // Reset offset when filter changes
    }));
  };

  const handlePageChange = (newOffset) => {
    setFilters(prev => ({
      ...prev,
      offset: newOffset
    }));
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  };

  const getLevelBadgeClass = (level) => {
    if (!level) return 'badge-default';
    switch (level.toLowerCase()) {
      case 'error':
        return 'badge-error';
      case 'warn':
      case 'warning':
        return 'badge-warn';
      case 'info':
        return 'badge-info';
      case 'debug':
        return 'badge-debug';
      case 'critical':
        return 'badge-critical';
      default:
        return 'badge-default';
    }
  };

  const parseContext = (context) => {
    if (!context) return null;
    try {
      if (typeof context === 'string') {
        return JSON.parse(context);
      }
      return context;
    } catch (e) {
      return null;
    }
  };

  const parseDetails = (details) => {
    if (!details) return null;
    try {
      if (typeof details === 'string') {
        return JSON.parse(details);
      }
      return details;
    } catch (e) {
      return null;
    }
  };

  if (!isAdmin) {
    return (
      <Layout>
        <div className="container">
          <div className="card">
            <h1>Acesso Negado</h1>
            <p>Você não tem permissão para acessar esta página.</p>
          </div>
        </div>
      </Layout>
    );
  }

  const currentPage = Math.floor(filters.offset / filters.limit) + 1;
  const totalPages = Math.ceil(total / filters.limit);

  return (
    <Layout>
      <div className="logs-page">
        <div className="logs-container">
          <h1 className="logs-title">Logs da Aplicação</h1>
          
          {error && <div className="alert alert-error">{error}</div>}

          <div className="logs-filters">
            <div className="filter-group">
              <label htmlFor="level">Nível:</label>
              <select
                id="level"
                name="level"
                value={filters.level}
                onChange={handleFilterChange}
                className="filter-select"
              >
                <option value="">Todos</option>
                <option value="error">Error</option>
                <option value="warn">Warning</option>
                <option value="info">Info</option>
                <option value="debug">Debug</option>
              </select>
            </div>
            <div className="filter-group">
              <label htmlFor="limit">Itens por página:</label>
              <select
                id="limit"
                name="limit"
                value={filters.limit}
                onChange={handleFilterChange}
                className="filter-select"
              >
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
                <option value="500">500</option>
              </select>
            </div>
            <div className="filter-info">
              Total: {total} logs
            </div>
          </div>

          {loading ? (
            <div className="loading">Carregando logs...</div>
          ) : (
            <>
              <div className="logs-table-container">
                <table className="logs-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nível</th>
                      <th>Mensagem</th>
                      <th>Usuário</th>
                      <th>IP</th>
                      <th>Data/Hora</th>
                      <th>Contexto</th>
                      <th>Detalhes</th>
                    </tr>
                  </thead>
                  <tbody>
                    {logs.length === 0 ? (
                      <tr>
                        <td colSpan="8" className="text-center">
                          Nenhum log encontrado
                        </td>
                      </tr>
                    ) : (
                      logs.map((log) => {
                        const context = parseContext(log.context);
                        const details = parseDetails(log.details);
                        return (
                          <tr key={log.id}>
                            <td>{log.id}</td>
                            <td>
                              {log.level ? (
                                <span className={`badge ${getLevelBadgeClass(log.level)}`}>
                                  {log.level.toUpperCase()}
                                </span>
                              ) : (
                                <span className="badge badge-default">-</span>
                              )}
                            </td>
                            <td className="log-message">{log.message}</td>
                            <td>{log.user_email || '-'}</td>
                            <td>{log.ip_address || '-'}</td>
                            <td>{formatDate(log.created_at)}</td>
                            <td>
                              {context ? (
                                <details className="log-context">
                                  <summary>Ver contexto</summary>
                                  <pre>{JSON.stringify(context, null, 2)}</pre>
                                </details>
                              ) : (
                                '-'
                              )}
                            </td>
                            <td>
                              {details ? (
                                <details className="log-details">
                                  <summary>Ver detalhes</summary>
                                  <div className="log-details-content">
                                    {details.request && (
                                      <div className="details-section">
                                        <h4>Requisição</h4>
                                        <pre>{JSON.stringify(details.request, null, 2)}</pre>
                                      </div>
                                    )}
                                    {details.response && (
                                      <div className="details-section">
                                        <h4>Resposta</h4>
                                        <pre>{JSON.stringify(details.response, null, 2)}</pre>
                                      </div>
                                    )}
                                    {details.user && (
                                      <div className="details-section">
                                        <h4>Usuário</h4>
                                        <pre>{JSON.stringify(details.user, null, 2)}</pre>
                                      </div>
                                    )}
                                    {details.timestamp && (
                                      <div className="details-section">
                                        <h4>Timestamp</h4>
                                        <pre>{details.timestamp}</pre>
                                      </div>
                                    )}
                                  </div>
                                </details>
                              ) : (
                                '-'
                              )}
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>

              {totalPages > 1 && (
                <div className="logs-pagination">
                  <button
                    onClick={() => handlePageChange(Math.max(0, filters.offset - filters.limit))}
                    disabled={filters.offset === 0}
                    className="btn btn-secondary"
                  >
                    Anterior
                  </button>
                  <span className="pagination-info">
                    Página {currentPage} de {totalPages}
                  </span>
                  <button
                    onClick={() => handlePageChange(filters.offset + filters.limit)}
                    disabled={filters.offset + filters.limit >= total}
                    className="btn btn-secondary"
                  >
                    Próxima
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </Layout>
  );
};

export default Logs;

