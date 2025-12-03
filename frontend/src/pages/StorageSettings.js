import React, { useState, useEffect } from 'react';
import Layout from '../components/Layout';
import api from '../services/api';
import './StorageSettings.css';

const StorageSettings = () => {
  const [linkStatus, setLinkStatus] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    checkLinkStatus();
  }, []);

  const checkLinkStatus = async () => {
    try {
      setLoading(true);
      setError('');
      const response = await api.get('/storage/link/status');
      setLinkStatus(response.data);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao verificar status do link');
    } finally {
      setLoading(false);
    }
  };

  const createLink = async () => {
    if (!window.confirm('Tem certeza que deseja criar o link simbólico do storage? Isso pode substituir um link existente.')) {
      return;
    }

    try {
      setLoading(true);
      setError('');
      setSuccess('');
      
      const response = await api.post('/storage/link/create');
      
      if (response.data.success) {
        setSuccess(response.data.message || 'Link criado com sucesso!');
        await checkLinkStatus();
        setTimeout(() => setSuccess(''), 5000);
      } else {
        setError(response.data.error || 'Erro ao criar link');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao criar link simbólico');
    } finally {
      setLoading(false);
    }
  };

  const testStorage = async () => {
    try {
      setLoading(true);
      setError('');
      setSuccess('');
      
      const response = await api.post('/storage/test');
      
      if (response.data.success) {
        setSuccess(response.data.message || 'Storage está funcionando corretamente!');
        setTimeout(() => setSuccess(''), 5000);
      } else {
        setError(response.data.error || 'Erro ao testar storage');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao testar storage');
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadge = (status) => {
    const badges = {
      'ok': { class: 'status-ok', text: '✓ OK', icon: '✓' },
      'not_created': { class: 'status-error', text: '✗ Não Criado', icon: '✗' },
      'broken': { class: 'status-warning', text: '⚠ Quebrado', icon: '⚠' },
      'directory_exists': { class: 'status-warning', text: '⚠ Diretório Existe', icon: '⚠' }
    };
    
    return badges[status] || badges['not_created'];
  };

  return (
    <Layout>
      <div className="storage-settings-page">
        <div className="storage-settings-header">
          <h1>Configurações de Storage</h1>
          <p>Gerencie o link simbólico do storage público</p>
        </div>

        {error && <div className="alert alert-error">{error}</div>}
        {success && <div className="alert alert-success">{success}</div>}

        <div className="storage-status-card">
          <h2>Status do Link Simbólico</h2>
          
          {loading && !linkStatus && (
            <div className="loading-container">
              <div className="spinner"></div>
              <p>Verificando status...</p>
            </div>
          )}

          {linkStatus && (
            <div className="status-info">
              <div className="status-badge-container">
                <span className={`status-badge ${getStatusBadge(linkStatus.status).class}`}>
                  {getStatusBadge(linkStatus.status).icon} {getStatusBadge(linkStatus.status).text}
                </span>
              </div>
              
              <p className="status-message">{linkStatus.message}</p>

              <div className="status-details">
                <div className="detail-item">
                  <label>Caminho Público:</label>
                  <code>{linkStatus.public_path}</code>
                </div>
                <div className="detail-item">
                  <label>Caminho do Storage:</label>
                  <code>{linkStatus.storage_path}</code>
                </div>
                {linkStatus.link_target && (
                  <div className="detail-item">
                    <label>Link Aponta Para:</label>
                    <code>{linkStatus.link_target}</code>
                  </div>
                )}
                <div className="detail-item">
                  <label>Storage Existe:</label>
                  <span className={linkStatus.storage_exists ? 'text-success' : 'text-error'}>
                    {linkStatus.storage_exists ? '✓ Sim' : '✗ Não'}
                  </span>
                </div>
                <div className="detail-item">
                  <label>Public Existe:</label>
                  <span className={linkStatus.public_exists ? 'text-success' : 'text-error'}>
                    {linkStatus.public_exists ? '✓ Sim' : '✗ Não'}
                  </span>
                </div>
                <div className="detail-item">
                  <label>É um Link:</label>
                  <span className={linkStatus.is_link ? 'text-success' : 'text-error'}>
                    {linkStatus.is_link ? '✓ Sim' : '✗ Não'}
                  </span>
                </div>
              </div>
            </div>
          )}

          <div className="action-buttons">
            <button
              onClick={checkLinkStatus}
              className="btn btn-secondary"
              disabled={loading}
            >
              {loading ? 'Verificando...' : '🔄 Verificar Status'}
            </button>
            
            <button
              onClick={createLink}
              className="btn btn-primary"
              disabled={loading || (linkStatus && linkStatus.status === 'ok')}
            >
              {loading ? 'Criando...' : '🔗 Criar Link Simbólico'}
            </button>

            <button
              onClick={testStorage}
              className="btn btn-info"
              disabled={loading}
            >
              {loading ? 'Testando...' : '🧪 Testar Storage'}
            </button>
          </div>
        </div>

        <div className="storage-info-card">
          <h2>Informações</h2>
          <div className="info-content">
            <p>
              O link simbólico conecta o diretório <code>public/storage</code> ao diretório 
              <code>storage/app/public</code>, permitindo que arquivos salvos no storage sejam 
              acessíveis publicamente via URL.
            </p>
            <p>
              <strong>Importante:</strong> Este link é necessário para que imagens e outros 
              arquivos enviados sejam exibidos corretamente na aplicação.
            </p>
            <p>
              Se o link não existir ou estiver quebrado, os arquivos não serão acessíveis 
              publicamente, mesmo que estejam salvos no storage.
            </p>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default StorageSettings;

