import React, { useState, useEffect } from 'react';
import Layout from '../components/Layout';
import api from '../services/api';
import useConfirm from '../hooks/useConfirm';
import './ArtisanCommands.css';

const ArtisanCommands = () => {
  const { confirm, DialogComponent } = useConfirm();
  const [commands, setCommands] = useState({});
  const [loading, setLoading] = useState(false);
  const [loadingCommands, setLoadingCommands] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [results, setResults] = useState(null);

  useEffect(() => {
    loadAvailableCommands();
  }, []);

  const loadAvailableCommands = async () => {
    try {
      setLoadingCommands(true);
      setError('');
      const response = await api.get('/artisan/commands');
      setCommands(response.data.commands || {});
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar comandos disponíveis');
    } finally {
      setLoadingCommands(false);
    }
  };

  const executeCommand = async (command) => {
    const confirmed = await confirm({
      message: `Tem certeza que deseja executar: ${commands[command]}?`,
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      setLoading(true);
      setError('');
      setSuccess('');
      setResults(null);
      
      const response = await api.post('/artisan/execute', { command });
      
      if (response.data.success) {
        setSuccess(response.data.message);
        setResults({
          command: response.data.command,
          output: response.data.output,
        });
        setTimeout(() => setSuccess(''), 5000);
      } else {
        setError(response.data.error || 'Erro ao executar comando');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao executar comando');
    } finally {
      setLoading(false);
    }
  };

  const clearAllCaches = async () => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja limpar TODOS os caches? Esta ação não pode ser desfeita.',
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      setLoading(true);
      setError('');
      setSuccess('');
      setResults(null);
      
      const response = await api.post('/artisan/clear-all-caches');
      
      if (response.data.success) {
        setSuccess(response.data.message);
        setResults({
          command: 'clear-all-caches',
          results: response.data.results,
        });
        setTimeout(() => setSuccess(''), 5000);
      } else {
        setError(response.data.error || 'Erro ao limpar caches');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao limpar caches');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Layout>
      <DialogComponent />
      <div className="artisan-commands-page">
        <div className="artisan-commands-header">
          <h1>Comandos Artisan</h1>
          <p>Execute comandos do Laravel Artisan através da interface web</p>
        </div>

        {error && <div className="alert alert-error">{error}</div>}
        {success && <div className="alert alert-success">{success}</div>}

        {loadingCommands ? (
          <div className="loading">Carregando comandos disponíveis...</div>
        ) : (
          <>
            <div className="artisan-commands-section">
              <h2>Ações Rápidas</h2>
              <div className="quick-actions">
                <button
                  className="btn btn-primary btn-large"
                  onClick={clearAllCaches}
                  disabled={loading}
                >
                  {loading ? 'Executando...' : 'Limpar Todos os Caches'}
                </button>
                <p className="help-text">
                  Limpa todos os caches: aplicação, configuração, rotas e views
                </p>
              </div>
            </div>

            <div className="artisan-commands-section">
              <h2>Comandos Disponíveis</h2>
              <div className="commands-list">
                {Object.entries(commands).map(([command, description]) => (
                  <div key={command} className="command-item">
                    <div className="command-info">
                      <h3>{description}</h3>
                      <code className="command-name">{command}</code>
                    </div>
                    <button
                      className="btn btn-secondary"
                      onClick={() => executeCommand(command)}
                      disabled={loading}
                    >
                      {loading ? 'Executando...' : 'Executar'}
                    </button>
                  </div>
                ))}
              </div>
            </div>

            {results && (
              <div className="artisan-commands-section">
                <h2>Resultado da Execução</h2>
                {results.results ? (
                  <div className="results-list">
                    {results.results.map((result, index) => (
                      <div
                        key={index}
                        className={`result-item ${result.success ? 'success' : 'error'}`}
                      >
                        <div className="result-header">
                          <strong>{result.description}</strong>
                          <span className={`status-badge ${result.success ? 'success' : 'error'}`}>
                            {result.success ? '✓ Sucesso' : '✗ Erro'}
                          </span>
                        </div>
                        <code className="result-output">
                          {result.success ? result.output : result.error}
                        </code>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="result-item success">
                    <div className="result-header">
                      <strong>Comando: {results.command}</strong>
                      <span className="status-badge success">✓ Executado</span>
                    </div>
                    <code className="result-output">{results.output}</code>
                  </div>
                )}
              </div>
            )}
          </>
        )}
      </div>
    </Layout>
  );
};

export default ArtisanCommands;

