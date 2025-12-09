import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faUpload, faTrash } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import botService from '../services/botService';
import useConfirm from '../hooks/useConfirm';
import { useManageBot } from '../contexts/ManageBotContext';
import './WelcomeMessage.css';

const WelcomeMessage = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const { botId } = useParams();
  const isInManageBot = useManageBot();
  
  const [formData, setFormData] = useState({
    initial_message: '',
    top_message: '',
    button_message: '',
    activate_cta: false,
    media_1_url: '',
    media_2_url: '',
    media_3_url: ''
  });
  
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    if (botId) {
      loadBot();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoadingData(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [botId]);

  // Função para normalizar URLs (remove espaços e codifica)
  const normalizeUrl = (url) => {
    if (!url) return '';
    // Codifica espaços e caracteres especiais na URL
    try {
      // Se a URL já está completa, apenas codifica os espaços
      return url.replace(/\s+/g, '%20');
    } catch (e) {
      return url;
    }
  };

  const loadBot = async () => {
    try {
      setLoadingData(true);
      const bot = await botService.getBotById(botId);
      setFormData({
        initial_message: bot.initial_message || '',
        top_message: bot.top_message || '',
        button_message: bot.button_message || '',
        activate_cta: bot.activate_cta || false,
        media_1_url: normalizeUrl(bot.media_1_url),
        media_2_url: normalizeUrl(bot.media_2_url),
        media_3_url: normalizeUrl(bot.media_3_url)
      });
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar bot');
    } finally {
      setLoadingData(false);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData({
      ...formData,
      [name]: type === 'checkbox' ? checked : value
    });
    setError('');
  };

  const handleToggle = (field) => {
    setFormData({
      ...formData,
      [field]: !formData[field]
    });
  };

  const handleMediaUpdate = (mediaNumber) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*,video/*,application/pdf,.doc,.docx';
    input.onchange = async (e) => {
      const file = e.target.files[0];
      if (!file) return;

      // Valida tamanho do arquivo (10MB)
      if (file.size > 10 * 1024 * 1024) {
        setError('Arquivo muito grande. Tamanho máximo: 10MB');
        setTimeout(() => setError(''), 5000);
        return;
      }

      setLoading(true);
      setError('');
      setSuccess('');

      try {
        const result = await botService.uploadMedia(botId, file, mediaNumber);
        if (result.success) {
          const fieldName = `media_${mediaNumber}_url`;
          setFormData({
            ...formData,
            [fieldName]: result.url
          });
          setSuccess(`Mídia ${mediaNumber} enviada com sucesso!`);
          setTimeout(() => setSuccess(''), 3000);
        } else {
          setError(result.error || 'Erro ao enviar mídia');
        }
      } catch (err) {
        setError(err.response?.data?.error || 'Erro ao enviar mídia');
      } finally {
        setLoading(false);
      }
    };
    input.click();
  };

  const handleMediaDelete = async (mediaNumber) => {
    const confirmed = await confirm({
      message: `Tem certeza que deseja remover a mídia ${mediaNumber}?`,
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    setLoading(true);
    setError('');
    setSuccess('');

    try {
      const result = await botService.deleteMedia(botId, mediaNumber);
      if (result.success) {
        const fieldName = `media_${mediaNumber}_url`;
        setFormData({
          ...formData,
          [fieldName]: ''
        });
        setSuccess(`Mídia ${mediaNumber} removida com sucesso!`);
        setTimeout(() => setSuccess(''), 3000);
      } else {
        setError(result.error || 'Erro ao remover mídia');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao remover mídia');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!botId) {
      setError('Bot não selecionado');
      return;
    }

    setError('');
    setSuccess('');
    setLoading(true);

    try {
      await botService.updateBot(botId, formData);
      setSuccess('Mensagens de boas-vindas salvas com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar mensagens');
    } finally {
      setLoading(false);
    }
  };

  const content = (
    <>
      <DialogComponent />
      <div className="welcome-message-page">
        {loadingData ? (
          <div className="loading-container">Carregando...</div>
        ) : !botId ? (
          <div className="error-container">
            <p>{error}</p>
            <button onClick={() => navigate('/')} className="btn btn-primary">
              Voltar ao Dashboard
            </button>
          </div>
        ) : (
          <>
        <div className="welcome-message-content">
          <div className="welcome-header">
            <p className="welcome-description">
              Aqui, você tem o poder de personalizar os botões iniciais que os usuários visualizarão ao digitar "start" no seu bot.
            </p>
            <p className="welcome-description">
              Sinta-se à vontade para liberar sua criatividade na edição!
            </p>
          </div>

          {error && <div className="alert alert-error">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          {/* Media Update Buttons */}
          <div className="media-section">
            {[1, 2, 3].map((mediaNumber) => {
              const fieldName = `media_${mediaNumber}_url`;
              const mediaUrl = formData[fieldName];
              const isImage = mediaUrl && /\.(jpg|jpeg|png|gif|webp)$/i.test(mediaUrl);
              const isVideo = mediaUrl && /\.(mp4|avi|mov|webm)$/i.test(mediaUrl);

              // Codifica a URL para lidar com espaços e caracteres especiais
              const encodedMediaUrl = mediaUrl ? (() => {
                try {
                  // Tenta criar um objeto URL
                  const url = new URL(mediaUrl);
                  // Codifica cada segmento do pathname separadamente
                  // Isso preserva as barras mas codifica espaços e caracteres especiais
                  const pathSegments = url.pathname.split('/').map(segment => {
                    if (!segment) return segment; // Preserva barras vazias
                    return encodeURIComponent(segment);
                  });
                  url.pathname = pathSegments.join('/');
                  return url.toString();
                } catch (e) {
                  // Se não é uma URL válida (URL relativa ou malformada), 
                  // codifica apenas os espaços mantendo a estrutura
                  return mediaUrl.replace(/\s+/g, '%20');
                }
              })() : null;

              return (
                <div key={mediaNumber} className="media-item">
                  <div className="media-preview">
                    {mediaUrl && isImage && (
                      <>
                        <img 
                          src={encodedMediaUrl} 
                          alt={`Mídia ${mediaNumber}`} 
                          className="media-preview-image"
                          onError={(e) => {
                            const errorDiv = e.target.parentElement.querySelector('.media-error-fallback');
                            if (errorDiv) {
                              e.target.style.display = 'none';
                              errorDiv.style.display = 'flex';
                            }
                          }}
                        />
                        <div className="media-preview-file media-error-fallback" style={{ display: 'none' }}>
                          <FontAwesomeIcon icon={faUpload} />
                          <span>Erro ao carregar imagem</span>
                          <a href={encodedMediaUrl} target="_blank" rel="noopener noreferrer" className="media-link">
                            Abrir em nova aba
                          </a>
                        </div>
                      </>
                    )}
                    {mediaUrl && isVideo && (
                      <>
                        <video 
                          src={encodedMediaUrl} 
                          className="media-preview-video" 
                          controls
                          onError={(e) => {
                            const errorDiv = e.target.parentElement.querySelector('.media-error-fallback');
                            if (errorDiv) {
                              e.target.style.display = 'none';
                              errorDiv.style.display = 'flex';
                            }
                          }}
                        />
                        <div className="media-preview-file media-error-fallback" style={{ display: 'none' }}>
                          <FontAwesomeIcon icon={faUpload} />
                          <span>Erro ao carregar vídeo</span>
                          <a href={encodedMediaUrl} target="_blank" rel="noopener noreferrer" className="media-link">
                            Abrir em nova aba
                          </a>
                        </div>
                      </>
                    )}
                    {mediaUrl && !isImage && !isVideo && (
                      <div className="media-preview-file">
                        <FontAwesomeIcon icon={faUpload} />
                        <span>Arquivo anexado</span>
                        <a href={encodedMediaUrl} target="_blank" rel="noopener noreferrer" className="media-link">
                          Ver arquivo
                        </a>
                      </div>
                    )}
                    {!mediaUrl && (
                      <div className="media-preview-file">
                        <FontAwesomeIcon icon={faUpload} />
                        <span>Nenhuma mídia adicionada</span>
                      </div>
                    )}
                  </div>
                  <div className="media-actions">
                    <button
                      onClick={() => handleMediaUpdate(mediaNumber)}
                      className={`btn-media ${mediaUrl ? 'has-media' : ''}`}
                      disabled={loading}
                    >
                      <FontAwesomeIcon icon={faUpload} />
                      {mediaUrl ? 'Atualizar mídia' : 'Adicionar mídia'}
                    </button>
                    {mediaUrl && (
                      <button
                        onClick={() => handleMediaDelete(mediaNumber)}
                        className="btn-delete-media"
                        title="Remover mídia"
                        disabled={loading}
                      >
                        <FontAwesomeIcon icon={faTrash} />
                      </button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>

          {/* Mensagem inicial */}
          <div className="message-section">
            <label className="section-label">Mensagem inicial</label>
            <textarea
              name="initial_message"
              value={formData.initial_message}
              onChange={handleChange}
              className="message-input"
              placeholder="Digite a mensagem inicial que será exibida quando o usuário digitar /start"
              rows="3"
            />
          </div>

          {/* Mensagem superior */}
          <div className="message-section">
            <label className="section-label">Mensagem superior</label>
            <textarea
              name="top_message"
              value={formData.top_message}
              onChange={handleChange}
              className="message-input"
              placeholder="Digite a mensagem que aparecerá no topo"
              rows="4"
            />
          </div>

          {/* Mensagem do botão */}
          <div className="message-section">
            <label className="section-label">Mensagem do botão</label>
            <input
              type="text"
              name="button_message"
              value={formData.button_message}
              onChange={handleChange}
              className="message-input"
              placeholder="Digite o texto do botão"
            />
          </div>

          {/* Footer with CTA toggle and Save button */}
          <div className="welcome-footer">
            <div className="cta-toggle">
              <span className="toggle-label">Ativar CTA</span>
              <button
                type="button"
                className={`toggle-switch ${formData.activate_cta ? 'active' : ''}`}
                onClick={() => handleToggle('activate_cta')}
              >
                <span className="toggle-slider"></span>
              </button>
            </div>
            <button
              onClick={handleSave}
              className="btn btn-save"
              disabled={loading}
            >
              {loading ? 'Salvando...' : 'Salvar'}
            </button>
          </div>
        </div>
          </>
        )}
      </div>
    </>
  );

  if (isInManageBot) {
    return content;
  }

  return <Layout>{content}</Layout>;
};

export default WelcomeMessage;

