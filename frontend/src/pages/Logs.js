import React, { useState, useEffect, useContext } from 'react';
import { AuthContext } from '../contexts/AuthContext';
import Layout from '../components/Layout';
import logService from '../services/logService';
import botService from '../services/botService';
import useConfirm from '../hooks/useConfirm';
import useAlert from '../hooks/useAlert';
import './Logs.css';

const Logs = () => {
  const [logs, setLogs] = useState([]);
  const [bots, setBots] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedLog, setSelectedLog] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [filters, setFilters] = useState({
    level: '',
    bot_id: '',
    limit: 100,
    offset: 0,
    startDate: '',
    endDate: '',
    user_email: '',
    message: ''
  });
  const [total, setTotal] = useState(0);
  const [deleting, setDeleting] = useState(false);
  const { isAdmin } = useContext(AuthContext);
  const { confirm, DialogComponent } = useConfirm();
  const { alert, DialogComponent: AlertDialog } = useAlert();

  useEffect(() => {
    if (isAdmin) {
      loadBots();
      loadLogs();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isAdmin, filters]);

  const loadBots = async () => {
    try {
      const data = await botService.getAllBots();
      setBots(data);
    } catch (err) {
      console.error('Erro ao carregar bots:', err);
    }
  };

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

  const handleViewDetails = (log) => {
    setSelectedLog(log);
    setShowModal(true);
  };

  const handleCloseModal = () => {
    setShowModal(false);
    setSelectedLog(null);
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

  const formatDateFull = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      timeZoneName: 'short'
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

  const formatJson = (obj) => {
    if (!obj) return null;
    try {
      if (typeof obj === 'string') {
        const parsed = JSON.parse(obj);
        return JSON.stringify(parsed, null, 2);
      }
      return JSON.stringify(obj, null, 2);
    } catch (e) {
      return String(obj);
    }
  };


  const clearFilters = () => {
    setFilters({
      level: '',
      bot_id: '',
      limit: 100,
      offset: 0,
      startDate: '',
      endDate: '',
      user_email: '',
      message: ''
    });
  };

  const handleDeleteAll = async () => {
    const confirmed = await confirm(
      'Remover todos os logs?',
      `Tem certeza que deseja remover todos os ${total} registro(s) de log? Esta ação não pode ser desfeita.`,
      'warning'
    );

    if (!confirmed) {
      return;
    }

    try {
      setDeleting(true);
      setError('');
      
      const result = await logService.deleteAllLogs();
      
      if (result.success) {
        // Recarrega os logs após deletar
        await loadLogs();
        // Mostra mensagem de sucesso
        setError('');
        // Mostra alerta de sucesso
        await alert(result.message || 'Todos os logs foram removidos com sucesso!', 'Sucesso', 'success');
      } else {
        throw new Error(result.error || 'Erro ao remover logs');
      }
    } catch (err) {
      setError(err.response?.data?.error || err.message || 'Erro ao remover logs');
    } finally {
      setDeleting(false);
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
  const hasActiveFilters = filters.level || filters.bot_id || filters.startDate || filters.endDate || filters.user_email || filters.message;

  return (
    <Layout>
      <DialogComponent />
      <AlertDialog />
      <div className="logs-page">
        <div className="logs-container">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
            <h1 className="logs-title" style={{ margin: 0 }}>Logs da Aplicação</h1>
            {total > 0 && (
              <button
                onClick={handleDeleteAll}
                disabled={deleting || loading}
                className="btn btn-danger"
                style={{ 
                  padding: '10px 20px',
                  fontSize: '14px',
                  fontWeight: '500'
                }}
              >
                {deleting ? 'Removendo...' : '🗑️ Remover Todos os Logs'}
              </button>
            )}
          </div>
          
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
                <option value="critical">Critical</option>
              </select>
            </div>

            <div className="filter-group">
              <label htmlFor="bot_id">Bot:</label>
              <select
                id="bot_id"
                name="bot_id"
                value={filters.bot_id}
                onChange={handleFilterChange}
                className="filter-select"
              >
                <option value="">Todos os bots</option>
                {bots.map(bot => (
                  <option key={bot.id} value={bot.id}>
                    {bot.name} {bot.username ? `(@${bot.username})` : ''}
                  </option>
                ))}
              </select>
            </div>

            <div className="filter-group">
              <label htmlFor="startDate">Data Inicial:</label>
              <input
                type="date"
                id="startDate"
                name="startDate"
                value={filters.startDate}
                onChange={handleFilterChange}
                className="filter-input"
              />
            </div>

            <div className="filter-group">
              <label htmlFor="endDate">Data Final:</label>
              <input
                type="date"
                id="endDate"
                name="endDate"
                value={filters.endDate}
                onChange={handleFilterChange}
                className="filter-input"
              />
            </div>

            <div className="filter-group">
              <label htmlFor="user_email">Usuário:</label>
              <input
                type="text"
                id="user_email"
                name="user_email"
                value={filters.user_email}
                onChange={handleFilterChange}
                placeholder="Filtrar por email"
                className="filter-input"
              />
            </div>

            <div className="filter-group">
              <label htmlFor="message">Mensagem:</label>
              <input
                type="text"
                id="message"
                name="message"
                value={filters.message}
                onChange={handleFilterChange}
                placeholder="Buscar na mensagem"
                className="filter-input"
              />
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

            {hasActiveFilters && (
              <button
                onClick={clearFilters}
                className="btn btn-secondary btn-clear-filters"
              >
                Limpar Filtros
              </button>
            )}

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
                      <th>Bot</th>
                      <th>Mensagem</th>
                      <th>Usuário</th>
                      <th>IP</th>
                      <th>Data/Hora</th>
                      <th>Ações</th>
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
                        const context = log.context;
                        const details = log.details;
                        const hasDetails = context || details || log.request || log.response;
                        
                        return (
                          <tr key={log.id} className={log.level === 'error' ? 'log-row-error' : ''}>
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
                            <td>
                              {log.bot_name ? (
                                <div className="bot-info">
                                  <div className="bot-name">{log.bot_name}</div>
                                  {log.bot_username && (
                                    <div className="bot-username">@{log.bot_username}</div>
                                  )}
                                </div>
                              ) : (
                                <span className="text-muted">-</span>
                              )}
                            </td>
                            <td className="log-message" title={log.message}>
                              {log.message ? (log.message.length > 100 ? `${log.message.substring(0, 100)}...` : log.message) : '-'}
                            </td>
                            <td>{log.user_email || '-'}</td>
                            <td>{log.ip_address || '-'}</td>
                            <td>{formatDate(log.created_at)}</td>
                            <td>
                              {hasDetails && (
                                <button
                                  onClick={() => handleViewDetails(log)}
                                  className="btn btn-sm btn-primary"
                                >
                                  Ver Detalhes
                                </button>
                              )}
                              {!hasDetails && <span className="text-muted">-</span>}
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

      {/* Modal de Detalhes */}
      {showModal && selectedLog && (
        <div className="modal-overlay" onClick={handleCloseModal}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h2>Detalhes do Log #{selectedLog.id}</h2>
              <button className="modal-close" onClick={handleCloseModal}>×</button>
            </div>
            <div className="modal-body">
              <div className="log-detail-section">
                <h3>Informações Básicas</h3>
                <div className="detail-grid">
                  <div className="detail-item">
                    <label>ID:</label>
                    <span>{selectedLog.id}</span>
                  </div>
                  <div className="detail-item">
                    <label>Nível:</label>
                    <span className={`badge ${getLevelBadgeClass(selectedLog.level)}`}>
                      {selectedLog.level?.toUpperCase() || '-'}
                    </span>
                  </div>
                  <div className="detail-item">
                    <label>Data/Hora:</label>
                    <span>{formatDateFull(selectedLog.created_at)}</span>
                  </div>
                  <div className="detail-item">
                    <label>Bot:</label>
                    <span>
                      {selectedLog.bot_name ? (
                        <>
                          {selectedLog.bot_name}
                          {selectedLog.bot_username && ` (@${selectedLog.bot_username})`}
                        </>
                      ) : '-'}
                    </span>
                  </div>
                  <div className="detail-item">
                    <label>Usuário:</label>
                    <span>{selectedLog.user_email || '-'}</span>
                  </div>
                  <div className="detail-item">
                    <label>IP:</label>
                    <span>{selectedLog.ip_address || '-'}</span>
                  </div>
                </div>
              </div>

              <div className="log-detail-section">
                <h3>Mensagem</h3>
                <div className="log-message-full">
                  {selectedLog.message || '-'}
                </div>
              </div>

              {selectedLog.context && (
                <div className="log-detail-section">
                  <h3>Contexto</h3>
                  <div className="json-viewer">
                    <pre className="json-pre">{formatJson(selectedLog.context)}</pre>
                  </div>
                </div>
              )}

              {selectedLog.details && (
                <div className="log-detail-section">
                  <h3>Detalhes</h3>
                  <div className="json-viewer">
                    <pre className="json-pre">{formatJson(selectedLog.details)}</pre>
                  </div>
                </div>
              )}

              {selectedLog.request && (
                <div className="log-detail-section">
                  <h3>Request</h3>
                  <div className="json-viewer">
                    <pre className="json-pre">{formatJson(selectedLog.request)}</pre>
                  </div>
                </div>
              )}

              {selectedLog.response && (
                <div className="log-detail-section">
                  <h3>Response</h3>
                  <div className="json-viewer">
                    <pre className="json-pre">{formatJson(selectedLog.response)}</pre>
                  </div>
                </div>
              )}
            </div>
            <div className="modal-footer">
              <button className="btn btn-secondary" onClick={handleCloseModal}>
                Fechar
              </button>
            </div>
          </div>
        </div>
      )}
    </Layout>
  );
};

export default Logs;
