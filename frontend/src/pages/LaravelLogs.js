import React, { useState, useEffect, useRef } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
  faTrash,
  faSync,
  faSearch,
  faFilter,
  faFileAlt,
  faBroom,
  faServer,
  faCheckCircle,
  faTimesCircle,
  faSpinner,
  faDownload,
  faEye,
  faEyeSlash
} from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import api from '../services/api';
import useAlert from '../hooks/useAlert';
import useConfirm from '../hooks/useConfirm';
import './LaravelLogs.css';

const LaravelLogs = () => {
  const { alert, DialogComponent: AlertDialog } = useAlert();
  const { confirm, DialogComponent: ConfirmDialog } = useConfirm();
  const [logFiles, setLogFiles] = useState([]);
  const [selectedFile, setSelectedFile] = useState(null);
  const [logContent, setLogContent] = useState('');
  const [loading, setLoading] = useState(false);
  const [loadingContent, setLoadingContent] = useState(false);
  const [autoRefresh, setAutoRefresh] = useState(false);
  const [refreshInterval, setRefreshInterval] = useState(5); // segundos
  const [filters, setFilters] = useState({
    level: '',
    search: '',
    lines: 1000,
    tail: true
  });
  const [cpanelTest, setCpanelTest] = useState({
    loading: false,
    result: null
  });
  const [showFilters, setShowFilters] = useState(false);
  const [expandedLines, setExpandedLines] = useState(new Set());
  const logContentRef = useRef(null);
  const autoRefreshRef = useRef(null);

  // Carrega lista de arquivos de log
  const loadLogFiles = async () => {
    try {
      setLoading(true);
      const response = await api.get('/laravel-logs');
      if (response.data.success) {
        setLogFiles(response.data.log_files || []);
        // Seleciona o primeiro arquivo se não houver seleção
        if (!selectedFile && response.data.log_files && response.data.log_files.length > 0) {
          setSelectedFile(response.data.log_files[0].name);
        }
      }
    } catch (error) {
      alert('Erro ao carregar arquivos de log: ' + (error.response?.data?.error || error.message), 'error');
    } finally {
      setLoading(false);
    }
  };

  // Carrega conteúdo do arquivo de log
  const loadLogContent = async (filename = selectedFile) => {
    if (!filename) return;

    try {
      setLoadingContent(true);
      const params = {
        lines: filters.lines,
        tail: filters.tail
      };
      if (filters.level) params.level = filters.level;
      if (filters.search) params.search = filters.search;

      const response = await api.get(`/laravel-logs/${encodeURIComponent(filename)}`, { params });
      if (response.data.success) {
        setLogContent(response.data.content || '');
        // Scroll para o final se tail=true
        if (filters.tail && logContentRef.current) {
          setTimeout(() => {
            logContentRef.current?.scrollTo({
              top: logContentRef.current.scrollHeight,
              behavior: 'smooth'
            });
          }, 100);
        }
      }
    } catch (error) {
      alert('Erro ao carregar conteúdo do log: ' + (error.response?.data?.error || error.message), 'error');
      setLogContent('');
    } finally {
      setLoadingContent(false);
    }
  };

  // Deleta arquivo de log
  const deleteLogFile = async (filename) => {
    const confirmed = await confirm(
      `Tem certeza que deseja deletar o arquivo "${filename}"? Esta ação não pode ser desfeita.`
    );

    if (!confirmed) return;

    try {
      const response = await api.delete(`/laravel-logs/${encodeURIComponent(filename)}`);
      if (response.data.success) {
        alert('Arquivo de log deletado com sucesso', 'success');
        // Remove da lista
        setLogFiles(logFiles.filter(f => f.name !== filename));
        // Se era o arquivo selecionado, seleciona outro ou limpa
        if (selectedFile === filename) {
          const remaining = logFiles.filter(f => f.name !== filename);
          setSelectedFile(remaining.length > 0 ? remaining[0].name : null);
          setLogContent('');
        }
      }
    } catch (error) {
      alert('Erro ao deletar arquivo: ' + (error.response?.data?.error || error.message), 'error');
    }
  };

  // Limpa conteúdo do arquivo (sem deletar)
  const clearLogFile = async (filename) => {
    const confirmed = await confirm(
      `Tem certeza que deseja limpar o conteúdo do arquivo "${filename}"? Esta ação não pode ser desfeita.`
    );

    if (!confirmed) return;

    try {
      const response = await api.post(`/laravel-logs/${encodeURIComponent(filename)}/clear`);
      if (response.data.success) {
        alert('Arquivo de log limpo com sucesso', 'success');
        setLogContent('');
        loadLogFiles(); // Atualiza tamanho do arquivo
      }
    } catch (error) {
      alert('Erro ao limpar arquivo: ' + (error.response?.data?.error || error.message), 'error');
    }
  };

  // Testa conexão com cPanel
  const testCpanelConnection = async () => {
    try {
      setCpanelTest({ loading: true, result: null });
      const response = await api.post('/laravel-logs/test-cpanel');
      setCpanelTest({
        loading: false,
        result: response.data
      });
    } catch (error) {
      setCpanelTest({
        loading: false,
        result: {
          success: false,
          error: error.response?.data?.error || error.message
        }
      });
    }
  };

  // Auto-refresh
  useEffect(() => {
    if (autoRefresh && selectedFile) {
      autoRefreshRef.current = setInterval(() => {
        loadLogContent();
      }, refreshInterval * 1000);
    } else {
      if (autoRefreshRef.current) {
        clearInterval(autoRefreshRef.current);
        autoRefreshRef.current = null;
      }
    }

    return () => {
      if (autoRefreshRef.current) {
        clearInterval(autoRefreshRef.current);
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [autoRefresh, selectedFile, refreshInterval, filters]);

  // Carrega arquivos ao montar
  useEffect(() => {
    loadLogFiles();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Carrega conteúdo quando arquivo é selecionado ou filtros mudam
  useEffect(() => {
    if (selectedFile) {
      loadLogContent();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedFile, filters]);

  // Formata linha de log com cores
  const formatLogLine = (line, index) => {
    if (!line.trim()) return <div key={index} className="log-line-empty">&nbsp;</div>;

    const levelColors = {
      'EMERGENCY': '#cc0000',
      'ALERT': '#cc3333',
      'CRITICAL': '#cc4444',
      'ERROR': '#cc0000',
      'WARNING': '#cc6600',
      'NOTICE': '#0066cc',
      'INFO': '#006600',
      'DEBUG': '#666666'
    };

    let level = null;
    let color = '#333333';

    // Detecta nível do log
    for (const [lvl, clr] of Object.entries(levelColors)) {
      if (line.includes(`[${lvl}]`) || line.includes(`.${lvl}:`)) {
        level = lvl;
        color = clr;
        break;
      }
    }

    const isExpanded = expandedLines.has(index);
    const isLongLine = line.length > 500;

    return (
      <div
        key={index}
        className={`log-line ${level ? `log-level-${level.toLowerCase()}` : ''}`}
        style={{ color }}
      >
        {isLongLine && (
          <button
            className="log-line-toggle"
            onClick={() => {
              const newExpanded = new Set(expandedLines);
              if (isExpanded) {
                newExpanded.delete(index);
              } else {
                newExpanded.add(index);
              }
              setExpandedLines(newExpanded);
            }}
          >
            <FontAwesomeIcon icon={isExpanded ? faEyeSlash : faEye} />
          </button>
        )}
        <span className="log-line-content">
          {isLongLine && !isExpanded ? line.substring(0, 500) + '...' : line}
        </span>
      </div>
    );
  };

  // Download do arquivo
  const downloadLog = () => {
    if (!selectedFile || !logContent) return;
    
    const blob = new Blob([logContent], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = selectedFile;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  const selectedFileInfo = logFiles.find(f => f.name === selectedFile);

  return (
    <Layout>
      <div className="laravel-logs-container">
      <AlertDialog />
      <ConfirmDialog />
      
      <div className="laravel-logs-header">
        <h1>
          <FontAwesomeIcon icon={faFileAlt} /> Logs do Laravel
        </h1>
        <div className="header-actions">
          <button
            className="btn btn-secondary"
            onClick={loadLogFiles}
            disabled={loading}
            title="Recarregar lista de arquivos"
          >
            <FontAwesomeIcon icon={faSync} spin={loading} /> Recarregar
          </button>
          <button
            className={`btn ${cpanelTest.result?.success ? 'btn-success' : 'btn-primary'}`}
            onClick={testCpanelConnection}
            disabled={cpanelTest.loading}
            title="Testar conexão com cPanel"
          >
            {cpanelTest.loading ? (
              <FontAwesomeIcon icon={faSpinner} spin />
            ) : (
              <FontAwesomeIcon icon={faServer} />
            )}{' '}
            Testar cPanel
          </button>
        </div>
      </div>

      {/* Resultado do teste cPanel */}
      {cpanelTest.result && (
        <div className={`cpanel-test-result ${cpanelTest.result.success ? 'success' : 'error'}`}>
          <FontAwesomeIcon
            icon={cpanelTest.result.success ? faCheckCircle : faTimesCircle}
          />{' '}
          {cpanelTest.result.success ? (
            <span>
              {cpanelTest.result.message || 'Conexão com cPanel estabelecida com sucesso'}
              {cpanelTest.result.cron_jobs_count !== undefined && (
                <span className="cron-jobs-count">
                  {' '}({cpanelTest.result.cron_jobs_count} cron job(s) encontrado(s))
                </span>
              )}
            </span>
          ) : (
            <span>{cpanelTest.result.error || 'Erro ao conectar com cPanel'}</span>
          )}
        </div>
      )}

      <div className="laravel-logs-layout">
        {/* Lista de arquivos */}
        <div className="log-files-sidebar">
          <h3>Arquivos de Log</h3>
          {loading ? (
            <div className="loading">Carregando...</div>
          ) : logFiles.length === 0 ? (
            <div className="empty-state">Nenhum arquivo de log encontrado</div>
          ) : (
            <div className="log-files-list">
              {logFiles.map((file) => (
                <div
                  key={file.name}
                  className={`log-file-item ${selectedFile === file.name ? 'active' : ''}`}
                  onClick={() => setSelectedFile(file.name)}
                >
                  <div className="log-file-name">
                    <FontAwesomeIcon icon={faFileAlt} /> {file.name}
                  </div>
                  <div className="log-file-info">
                    <span className="log-file-size">{file.size_human}</span>
                    <span className="log-file-date">{file.modified_formatted}</span>
                  </div>
                  <div className="log-file-actions">
                    <button
                      className="btn-icon"
                      onClick={(e) => {
                        e.stopPropagation();
                        clearLogFile(file.name);
                      }}
                      title="Limpar arquivo"
                    >
                      <FontAwesomeIcon icon={faBroom} />
                    </button>
                    <button
                      className="btn-icon btn-danger"
                      onClick={(e) => {
                        e.stopPropagation();
                        deleteLogFile(file.name);
                      }}
                      title="Deletar arquivo"
                    >
                      <FontAwesomeIcon icon={faTrash} />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Conteúdo do log */}
        <div className="log-content-area">
          {selectedFile ? (
            <>
              <div className="log-content-header">
                <div className="log-file-details">
                  <h3>{selectedFile}</h3>
                  {selectedFileInfo && (
                    <div className="file-info">
                      <span>Tamanho: {selectedFileInfo.size_human}</span>
                      <span>Modificado: {selectedFileInfo.modified_formatted}</span>
                    </div>
                  )}
                </div>
                <div className="log-content-actions">
                  <button
                    className="btn btn-secondary"
                    onClick={() => setShowFilters(!showFilters)}
                    title="Mostrar/Ocultar filtros"
                  >
                    <FontAwesomeIcon icon={faFilter} /> Filtros
                  </button>
                  <button
                    className="btn btn-secondary"
                    onClick={loadLogContent}
                    disabled={loadingContent}
                    title="Recarregar conteúdo"
                  >
                    <FontAwesomeIcon icon={faSync} spin={loadingContent} />
                  </button>
                  <button
                    className="btn btn-secondary"
                    onClick={downloadLog}
                    disabled={!logContent}
                    title="Download do arquivo"
                  >
                    <FontAwesomeIcon icon={faDownload} />
                  </button>
                  <button
                    className="btn btn-secondary"
                    onClick={() => clearLogFile(selectedFile)}
                    title="Limpar arquivo"
                  >
                    <FontAwesomeIcon icon={faBroom} />
                  </button>
                  <button
                    className="btn btn-danger"
                    onClick={() => deleteLogFile(selectedFile)}
                    title="Deletar arquivo"
                  >
                    <FontAwesomeIcon icon={faTrash} />
                  </button>
                </div>
              </div>

              {/* Filtros */}
              {showFilters && (
                <div className="log-filters">
                  <div className="filter-group">
                    <label>
                      <FontAwesomeIcon icon={faFilter} /> Nível:
                      <select
                        value={filters.level}
                        onChange={(e) => setFilters({ ...filters, level: e.target.value })}
                      >
                        <option value="">Todos</option>
                        <option value="debug">DEBUG</option>
                        <option value="info">INFO</option>
                        <option value="notice">NOTICE</option>
                        <option value="warning">WARNING</option>
                        <option value="error">ERROR</option>
                        <option value="critical">CRITICAL</option>
                        <option value="alert">ALERT</option>
                        <option value="emergency">EMERGENCY</option>
                      </select>
                    </label>
                  </div>
                  <div className="filter-group">
                    <label>
                      <FontAwesomeIcon icon={faSearch} /> Buscar:
                      <input
                        type="text"
                        value={filters.search}
                        onChange={(e) => setFilters({ ...filters, search: e.target.value })}
                        placeholder="Buscar no log..."
                      />
                    </label>
                  </div>
                  <div className="filter-group">
                    <label>
                      Linhas:
                      <input
                        type="number"
                        value={filters.lines}
                        onChange={(e) => setFilters({ ...filters, lines: parseInt(e.target.value) || 1000 })}
                        min="1"
                        max="10000"
                      />
                    </label>
                  </div>
                  <div className="filter-group">
                    <label>
                      <input
                        type="checkbox"
                        checked={filters.tail}
                        onChange={(e) => setFilters({ ...filters, tail: e.target.checked })}
                      />
                      Últimas linhas (tail)
                    </label>
                  </div>
                  <div className="filter-group">
                    <label>
                      <input
                        type="checkbox"
                        checked={autoRefresh}
                        onChange={(e) => setAutoRefresh(e.target.checked)}
                      />
                      Auto-refresh
                    </label>
                    {autoRefresh && (
                      <label className="refresh-interval">
                        Intervalo (segundos):
                        <input
                          type="number"
                          value={refreshInterval}
                          onChange={(e) => setRefreshInterval(parseInt(e.target.value) || 5)}
                          min="1"
                          max="60"
                        />
                      </label>
                    )}
                  </div>
                </div>
              )}

              {/* Conteúdo */}
              <div className="log-content-wrapper" ref={logContentRef}>
                {loadingContent ? (
                  <div className="loading">Carregando conteúdo...</div>
                ) : logContent ? (
                  <div className="log-content">
                    {logContent.split('\n').map((line, index) => formatLogLine(line, index))}
                  </div>
                ) : (
                  <div className="empty-state">Nenhum conteúdo encontrado</div>
                )}
              </div>
            </>
          ) : (
            <div className="empty-state">Selecione um arquivo de log para visualizar</div>
          )}
        </div>
      </div>
    </div>
    </Layout>
  );
};

export default LaravelLogs;
