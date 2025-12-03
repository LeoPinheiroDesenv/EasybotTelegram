import api from './api';

const ftpService = {
  /**
   * Lista arquivos e diretórios no FTP
   */
  listFiles: async (path = '/', disk = 'ftp') => {
    const response = await api.get('/ftp/files', {
      params: { path, disk }
    });
    return response.data;
  },

  /**
   * Faz upload de arquivo para o FTP
   */
  uploadFile: async (file, path = '/', disk = 'ftp') => {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('path', path);
    formData.append('disk', disk);

    const response = await api.post('/ftp/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    return response.data;
  },

  /**
   * Faz download de arquivo do FTP
   */
  downloadFile: async (path, disk = 'ftp') => {
    const response = await api.get('/ftp/download', {
      params: { path, disk },
      responseType: 'blob'
    });
    return response.data;
  },

  /**
   * Deleta arquivo ou diretório do FTP
   */
  deleteFile: async (path, disk = 'ftp') => {
    const response = await api.delete('/ftp/delete', {
      params: { path, disk }
    });
    return response.data;
  },

  /**
   * Cria um diretório no FTP
   */
  createDirectory: async (path, name, disk = 'ftp') => {
    const response = await api.post('/ftp/directory', {
      path,
      name,
      disk
    });
    return response.data;
  },

  /**
   * Testa conexão com o FTP
   */
  testConnection: async (disk = 'ftp') => {
    const response = await api.post('/ftp/test-connection', {
      disk
    });
    return response.data;
  }
};

export default ftpService;

