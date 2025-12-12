import React, { useState, useEffect } from 'react';
import Layout from '../components/Layout';
import api from '../services/api';
import botService from '../services/botService';
import useConfirm from '../hooks/useConfirm';
import './ArtisanCommands.css';

const ArtisanCommands = () => {
  const { confirm, DialogComponent } = useConfirm();
  const [commands, setCommands] = useState({});
  const [bots, setBots] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingCommands, setLoadingCommands] = useState(true);
  const [loadingBots, setLoadingBots] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [results, setResults] = useState(null);
  const [showParamsModal, setShowParamsModal] = useState(false);
  const [selectedCommand, setSelectedCommand] = useState(null);
  const [commandParams, setCommandParams] = useState({});

  useEffect(() => {
    loadAvailableCommands();
    loadBots();
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

  const loadBots = async () => {
    try {
      setLoadingBots(true);
      const botsList = await botService.getAllBots();
      setBots(botsList || []);
    } catch (err) {
      console.error('Erro ao carregar bots:', err);
    } finally {
      setLoadingBots(false);
    }
  };

  const handleExecuteCommand = (command) => {
    const commandInfo = commands[command];
    const hasParameters = commandInfo?.parameters && Object.keys(commandInfo.parameters).length > 0;

    if (hasParameters) {
      // Abre modal para configurar parâmetros
      setSelectedCommand(command);
      setCommandParams({});
      setShowParamsModal(true);
    } else {
      // Executa diretamente
      executeCommand(command, {});
    }
  };

  const executeCommand = async (command, parameters = {}) => {
    const confirmed = await confirm({
      message: `Tem certeza que deseja executar: ${commands[command]?.description || command}?`,
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
      setShowParamsModal(false);
      
      const response = await api.post('/artisan/execute', { 
        command,
        parameters 
      });
      
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

  const handleParamChange = (paramName, value) => {
    setCommandParams(prev => ({
      ...prev,
      [paramName]: value
    }));
  };

  const handleExecuteWithParams = () => {
    if (selectedCommand) {
      executeCommand(selectedCommand, commandParams);
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
                {Object.entries(commands).map(([command, commandInfo]) => {
                  const description = typeof commandInfo === 'string' ? commandInfo : (commandInfo?.description || command);
                  const hasParameters = commandInfo?.parameters && Object.keys(commandInfo.parameters).length > 0;
                  
                  return (
                    <div key={command} className="command-item">
                      <div className="command-info">
                        <h3>
                          {description}
                          {hasParameters && (
                            <span className="command-badge">Com parâmetros</span>
                          )}
                        </h3>
                        <code className="command-name">{command}</code>
                      </div>
                      <button
                        className="btn btn-secondary"
                        onClick={() => handleExecuteCommand(command)}
                        disabled={loading}
                      >
                        {loading ? 'Executando...' : 'Executar'}
                      </button>
                    </div>
                  );
                })}
              </div>
            </div>

            {showParamsModal && selectedCommand && (
              <div className="modal-overlay" onClick={() => setShowParamsModal(false)}>
                <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                  <div className="modal-header">
                    <h2>Configurar Parâmetros</h2>
                    <button 
                      className="modal-close" 
                      onClick={() => setShowParamsModal(false)}
                    >
                      ×
                    </button>
                  </div>
                  <div className="modal-body">
                    <p className="command-description">
                      {typeof commands[selectedCommand] === 'string' 
                        ? commands[selectedCommand] 
                        : (commands[selectedCommand]?.description || selectedCommand)}
                    </p>
                    <code className="command-name">{selectedCommand}</code>
                    
                    <div className="params-form">
                      {Object.entries(commands[selectedCommand]?.parameters || {}).map(([paramName, paramInfo]) => (
                        <div key={paramName} className="form-group">
                          <label htmlFor={paramName}>
                            {paramInfo.description || paramName}
                            {!paramInfo.required && <span className="optional"> (opcional)</span>}
                          </label>
                          
                          {paramInfo.type === 'boolean' ? (
                            <label className="checkbox-label">
                              <input
                                type="checkbox"
                                id={paramName}
                                checked={commandParams[paramName] || false}
                                onChange={(e) => handleParamChange(paramName, e.target.checked)}
                              />
                              <span>{paramInfo.description || paramName}</span>
                            </label>
                          ) : paramInfo.type === 'integer' && paramName === 'bot_id' ? (
                            <select
                              id={paramName}
                              value={commandParams[paramName] || ''}
                              onChange={(e) => handleParamChange(paramName, e.target.value ? parseInt(e.target.value) : null)}
                            >
                              <option value="">Todos os bots</option>
                              {bots.map(bot => (
                                <option key={bot.id} value={bot.id}>
                                  {bot.name} (ID: {bot.id})
                                </option>
                              ))}
                            </select>
                          ) : paramInfo.options ? (
                            <select
                              id={paramName}
                              value={commandParams[paramName] || paramInfo.default || ''}
                              onChange={(e) => handleParamChange(paramName, e.target.value)}
                            >
                              {paramInfo.options.map(option => (
                                <option key={option} value={option}>
                                  {option}
                                </option>
                              ))}
                            </select>
                          ) : (
                            <input
                              type={paramInfo.type === 'integer' ? 'number' : 'text'}
                              id={paramName}
                              value={commandParams[paramName] || paramInfo.default || ''}
                              onChange={(e) => handleParamChange(
                                paramName, 
                                paramInfo.type === 'integer' ? parseInt(e.target.value) || null : e.target.value
                              )}
                              placeholder={paramInfo.default ? `Padrão: ${paramInfo.default}` : ''}
                            />
                          )}
                          
                          {paramInfo.default && (
                            <small className="help-text">Valor padrão: {paramInfo.default}</small>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>
                  <div className="modal-footer">
                    <button 
                      className="btn btn-secondary" 
                      onClick={() => setShowParamsModal(false)}
                    >
                      Cancelar
                    </button>
                    <button 
                      className="btn btn-primary" 
                      onClick={handleExecuteWithParams}
                      disabled={loading}
                    >
                      {loading ? 'Executando...' : 'Executar'}
                    </button>
                  </div>
                </div>
              </div>
            )}

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

