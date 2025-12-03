import React, { useState, useEffect } from 'react';
import Layout from '../components/Layout';
import ftpService from '../services/ftpService';
import './FtpManager.css';

const FtpManager = () => {
  const [files, setFiles] = useState([]);
  const [currentPath, setCurrentPath] = useState('/');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [disk, setDisk] = useState('ftp');
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [showCreateDirModal, setShowCreateDirModal] = useState(false);
  const [uploadFile, setUploadFile] = useState(null);
  const [newDirName, setNewDirName] = useState('');
  const [connectionStatus, setConnectionStatus] = useState(null);

  useEffect(() => {
    loadFiles();
    testConnection();
  }, [currentPath, disk]);

  const loadFiles = async () => {
    try {
      setLoading(true);
      setError('');
      const response = await ftpService.listFiles(currentPath, disk);
      if (response.success) {
        setFiles(response.files || []);
      } else {
        setError(response.error || 'Erro ao carregar arquivos');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar arquivos');
    } finally {
      setLoading(false);
    }
  };

  const testConnection = async () => {
    try {
      const response = await ftpService.testConnection(disk);
      setConnectionStatus(response.success);
    } catch (err) {
      setConnectionStatus(false);
    }
  };

  const handleNavigate = (path) => {
    if (path === '..') {
      const parentPath = currentPath.split('/').slice(0, -2).join('/') || '/';
      setCurrentPath(parentPath);
    } else {
      setCurrentPath(path);
    }
  };

  const handleUpload = async () => {
    if (!uploadFile) {
      setError('Selecione um arquivo para upload');
      return;
    }

    try {
      setLoading(true);
      setError('');
      const response = await ftpService.uploadFile(uploadFile, currentPath, disk);
      if (response.success) {
        setSuccess('Arquivo enviado com sucesso!');
        setShowUploadModal(false);
        setUploadFile(null);
        loadFiles();
        setTimeout(() => setSuccess(''), 3000);
      } else {
        setError(response.error || 'Erro ao fazer upload');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao fazer upload');
    } finally {
      setLoading(false);
    }
  };

  const handleDownload = async (path) => {
    try {
      setLoading(true);
      setError('');
      const blob = await ftpService.downloadFile(path, disk);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = path.split('/').pop();
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      setSuccess('Download iniciado!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao fazer download');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (path) => {
    if (!window.confirm(`Tem certeza que deseja deletar "${path.split('/').pop()}"?`)) {
      return;
    }

    try {
      setLoading(true);
      setError('');
      const response = await ftpService.deleteFile(path, disk);
      if (response.success) {
        setSuccess('Arquivo ou diretório deletado com sucesso!');
        loadFiles();
        setTimeout(() => setSuccess(''), 3000);
      } else {
        setError(response.error || 'Erro ao deletar');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao deletar');
    } finally {
      setLoading(false);
    }
  };

  const handleCreateDirectory = async () => {
    if (!newDirName.trim()) {
      setError('Digite um nome para o diretório');
      return;
    }

    try {
      setLoading(true);
      setError('');
      const response = await ftpService.createDirectory(currentPath, newDirName, disk);
      if (response.success) {
        setSuccess('Diretório criado com sucesso!');
        setShowCreateDirModal(false);
        setNewDirName('');
        loadFiles();
        setTimeout(() => setSuccess(''), 3000);
      } else {
        setError(response.error || 'Erro ao criar diretório');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao criar diretório');
    } finally {
      setLoading(false);
    }
  };

  const getFileIcon = (file) => {
    if (file.type === 'directory') {
      return '📁';
    }
    const ext = file.extension?.toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
      return '🖼️';
    }
    if (['mp4', 'avi', 'mov', 'mkv'].includes(ext)) {
      return '🎬';
    }
    if (['pdf'].includes(ext)) {
      return '📄';
    }
    if (['zip', 'rar', '7z'].includes(ext)) {
      return '📦';
    }
    return '📄';
  };

  return (
    <Layout>
      <div className="ftp-manager">
        <div className="ftp-header">
          <h1>Gerenciador FTP</h1>
          <div className="ftp-controls">
            <select
              value={disk}
              onChange={(e) => {
                setDisk(e.target.value);
                setCurrentPath('/');
              }}
              className="disk-select"
            >
              <option value="ftp">FTP</option>
              <option value="sftp">SFTP</option>
            </select>
            <div className={`connection-status ${connectionStatus ? 'connected' : 'disconnected'}`}>
              {connectionStatus ? '🟢 Conectado' : '🔴 Desconectado'}
            </div>
            <button
              onClick={testConnection}
              className="btn btn-secondary"
              disabled={loading}
            >
              Testar Conexão
            </button>
            <button
              onClick={() => setShowCreateDirModal(true)}
              className="btn btn-primary"
              disabled={loading}
            >
              + Nova Pasta
            </button>
            <button
              onClick={() => setShowUploadModal(true)}
              className="btn btn-primary"
              disabled={loading}
            >
              📤 Upload
            </button>
          </div>
        </div>

        {error && (
          <div className="alert alert-error">
            {error}
            <button onClick={() => setError('')}>×</button>
          </div>
        )}

        {success && (
          <div className="alert alert-success">
            {success}
            <button onClick={() => setSuccess('')}>×</button>
          </div>
        )}

        <div className="ftp-breadcrumb">
          <button
            onClick={() => setCurrentPath('/')}
            className="breadcrumb-item"
          >
            🏠 Raiz
          </button>
          {currentPath !== '/' && (
            <>
              <span className="breadcrumb-separator">/</span>
              {currentPath.split('/').filter(Boolean).map((segment, index, array) => {
                const path = '/' + array.slice(0, index + 1).join('/');
                return (
                  <React.Fragment key={path}>
                    <button
                      onClick={() => setCurrentPath(path)}
                      className="breadcrumb-item"
                    >
                      {segment}
                    </button>
                    {index < array.length - 1 && (
                      <span className="breadcrumb-separator">/</span>
                    )}
                  </React.Fragment>
                );
              })}
            </>
          )}
        </div>

        <div className="ftp-content">
          {loading && <div className="loading">Carregando...</div>}
          
          {!loading && (
            <div className="files-table">
              {currentPath !== '/' && (
                <div
                  className="file-row directory"
                  onClick={() => handleNavigate('..')}
                >
                  <div className="file-icon">📁</div>
                  <div className="file-name">..</div>
                  <div className="file-size">-</div>
                  <div className="file-date">-</div>
                  <div className="file-actions">-</div>
                </div>
              )}

              {files.map((file, index) => (
                <div
                  key={index}
                  className={`file-row ${file.type}`}
                >
                  <div className="file-icon">{getFileIcon(file)}</div>
                  <div
                    className="file-name"
                    onClick={() => {
                      if (file.type === 'directory') {
                        handleNavigate(file.path);
                      }
                    }}
                  >
                    {file.name}
                  </div>
                  <div className="file-size">{file.size_formatted}</div>
                  <div className="file-date">
                    {file.last_modified
                      ? new Date(file.last_modified).toLocaleString('pt-BR')
                      : '-'}
                  </div>
                  <div className="file-actions">
                    {file.type === 'file' && (
                      <>
                        <button
                          onClick={() => handleDownload(file.path)}
                          className="btn-icon"
                          title="Download"
                        >
                          ⬇️
                        </button>
                        <button
                          onClick={() => handleDelete(file.path)}
                          className="btn-icon btn-danger"
                          title="Deletar"
                        >
                          🗑️
                        </button>
                      </>
                    )}
                    {file.type === 'directory' && (
                      <button
                        onClick={() => handleDelete(file.path)}
                        className="btn-icon btn-danger"
                        title="Deletar"
                      >
                        🗑️
                      </button>
                    )}
                  </div>
                </div>
              ))}

              {files.length === 0 && !loading && (
                <div className="empty-state">
                  <p>Nenhum arquivo ou diretório encontrado</p>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Modal de Upload */}
        {showUploadModal && (
          <div className="modal-overlay" onClick={() => setShowUploadModal(false)}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>Upload de Arquivo</h2>
                <button
                  onClick={() => setShowUploadModal(false)}
                  className="modal-close"
                >
                  ×
                </button>
              </div>
              <div className="modal-body">
                <div className="form-group">
                  <label>Selecione o arquivo:</label>
                  <input
                    type="file"
                    onChange={(e) => setUploadFile(e.target.files[0])}
                    className="form-input"
                  />
                  {uploadFile && (
                    <p className="file-info">
                      Arquivo selecionado: {uploadFile.name} (
                      {(uploadFile.size / 1024 / 1024).toFixed(2)} MB)
                    </p>
                  )}
                </div>
                <div className="form-group">
                  <label>Diretório de destino:</label>
                  <input
                    type="text"
                    value={currentPath}
                    readOnly
                    className="form-input"
                  />
                </div>
              </div>
              <div className="modal-footer">
                <button
                  onClick={() => setShowUploadModal(false)}
                  className="btn btn-secondary"
                >
                  Cancelar
                </button>
                <button
                  onClick={handleUpload}
                  className="btn btn-primary"
                  disabled={!uploadFile || loading}
                >
                  {loading ? 'Enviando...' : 'Enviar'}
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Modal de Criar Diretório */}
        {showCreateDirModal && (
          <div className="modal-overlay" onClick={() => setShowCreateDirModal(false)}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>Criar Diretório</h2>
                <button
                  onClick={() => setShowCreateDirModal(false)}
                  className="modal-close"
                >
                  ×
                </button>
              </div>
              <div className="modal-body">
                <div className="form-group">
                  <label>Nome do diretório:</label>
                  <input
                    type="text"
                    value={newDirName}
                    onChange={(e) => setNewDirName(e.target.value)}
                    className="form-input"
                    placeholder="Nome do diretório"
                    onKeyPress={(e) => {
                      if (e.key === 'Enter') {
                        handleCreateDirectory();
                      }
                    }}
                  />
                </div>
                <div className="form-group">
                  <label>Diretório pai:</label>
                  <input
                    type="text"
                    value={currentPath}
                    readOnly
                    className="form-input"
                  />
                </div>
              </div>
              <div className="modal-footer">
                <button
                  onClick={() => setShowCreateDirModal(false)}
                  className="btn btn-secondary"
                >
                  Cancelar
                </button>
                <button
                  onClick={handleCreateDirectory}
                  className="btn btn-primary"
                  disabled={!newDirName.trim() || loading}
                >
                  {loading ? 'Criando...' : 'Criar'}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
};

export default FtpManager;

