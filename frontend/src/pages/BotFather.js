import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faSync, faTrash } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import { useManageBot } from '../contexts/ManageBotContext';
import botFatherService from '../services/botFatherService';
import useConfirm from '../hooks/useConfirm';
import './BotFather.css';

const BotFather = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const { botId } = useParams();
  const isInManageBot = useManageBot();
  
  const [loading, setLoading] = useState(false);
  const [loadingInfo, setLoadingInfo] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [botInfo, setBotInfo] = useState({
    name: '',
    description: '',
    short_description: '',
    about: '',
    commands: [],
    menu_button: null,
    default_administrator_rights: null,
  });

  const [formData, setFormData] = useState({
    name: '',
    description: '',
    short_description: '',
    about: '',
    menu_button_type: 'default',
    menu_button_text: '',
    menu_button_web_app_url: '',
    admin_rights: {
      can_read_all_group_messages: false,
      can_manage_chat: false,
      can_delete_messages: false,
      can_manage_video_chats: false,
      can_restrict_members: false,
      can_promote_members: false,
      can_change_info: false,
      can_invite_users: false,
      can_post_messages: false,
      can_edit_messages: false,
      can_pin_messages: false,
      can_manage_topics: false,
    },
  });

  useEffect(() => {
    if (botId) {
      loadBotInfo();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoadingInfo(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [botId]);

  const loadBotInfo = async () => {
    try {
      setLoadingInfo(true);
      const info = await botFatherService.getBotInfo(botId);
      setBotInfo(info);
      setFormData({
        name: info.name || '',
        description: info.description || '',
        short_description: info.short_description || '',
        about: info.about || '',
        menu_button_type: info.menu_button?.type || 'default',
        menu_button_text: info.menu_button?.text || '',
        menu_button_web_app_url: info.menu_button?.web_app?.url || '',
        admin_rights: {
          can_read_all_group_messages: info.default_administrator_rights?.can_read_all_group_messages || false,
          can_manage_chat: info.default_administrator_rights?.can_manage_chat || false,
          can_delete_messages: info.default_administrator_rights?.can_delete_messages || false,
          can_manage_video_chats: info.default_administrator_rights?.can_manage_video_chats || false,
          can_restrict_members: info.default_administrator_rights?.can_restrict_members || false,
          can_promote_members: info.default_administrator_rights?.can_promote_members || false,
          can_change_info: info.default_administrator_rights?.can_change_info || false,
          can_invite_users: info.default_administrator_rights?.can_invite_users || false,
          can_post_messages: info.default_administrator_rights?.can_post_messages || false,
          can_edit_messages: info.default_administrator_rights?.can_edit_messages || false,
          can_pin_messages: info.default_administrator_rights?.can_pin_messages || false,
          can_manage_topics: info.default_administrator_rights?.can_manage_topics || false,
        },
      });
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar informações do bot');
    } finally {
      setLoadingInfo(false);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    if (name.startsWith('admin_rights_')) {
      const rightName = name.replace('admin_rights_', '');
      setFormData({
        ...formData,
        admin_rights: {
          ...formData.admin_rights,
          [rightName]: checked
        }
      });
    } else {
      setFormData({
        ...formData,
        [name]: type === 'checkbox' ? checked : value
      });
    }
    setError('');
  };

  const handleSetName = async (e) => {
    e.preventDefault();
    if (!formData.name.trim()) {
      setError('O nome é obrigatório');
      return;
    }

    try {
      setLoading(true);
      setError('');
      await botFatherService.setMyName(botId, formData.name);
      setSuccess('Nome do bot atualizado com sucesso!');
      await loadBotInfo();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao atualizar nome do bot');
    } finally {
      setLoading(false);
    }
  };

  const handleSetDescription = async (e) => {
    e.preventDefault();
    if (!formData.description.trim()) {
      setError('A descrição é obrigatória');
      return;
    }

    try {
      setLoading(true);
      setError('');
      await botFatherService.setMyDescription(botId, formData.description);
      setSuccess('Descrição do bot atualizada com sucesso!');
      await loadBotInfo();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao atualizar descrição do bot');
    } finally {
      setLoading(false);
    }
  };

  const handleSetShortDescription = async (e) => {
    e.preventDefault();
    if (!formData.short_description.trim()) {
      setError('A descrição curta é obrigatória');
      return;
    }

    if (formData.short_description.length > 120) {
      setError('A descrição curta deve ter no máximo 120 caracteres');
      return;
    }

    try {
      setLoading(true);
      setError('');
      await botFatherService.setMyShortDescription(botId, formData.short_description);
      setSuccess('Descrição curta do bot atualizada com sucesso!');
      await loadBotInfo();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao atualizar descrição curta do bot');
    } finally {
      setLoading(false);
    }
  };

  const handleSetAbout = async (e) => {
    e.preventDefault();
    if (!formData.about.trim()) {
      setError('O texto "sobre" é obrigatório');
      return;
    }

    if (formData.about.length > 120) {
      setError('O texto "sobre" deve ter no máximo 120 caracteres');
      return;
    }

    try {
      setLoading(true);
      setError('');
      await botFatherService.setMyAbout(botId, formData.about);
      setSuccess('Texto "sobre" do bot atualizado com sucesso!');
      await loadBotInfo();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao atualizar texto "sobre" do bot');
    } finally {
      setLoading(false);
    }
  };

  const handleSetMenuButton = async (e) => {
    e.preventDefault();
    
    try {
      setLoading(true);
      setError('');
      
      if (formData.menu_button_type === 'web_app') {
        if (!formData.menu_button_web_app_url.trim()) {
          setError('A URL do Web App é obrigatória');
          return;
        }
      }

      await botFatherService.setChatMenuButton(
        botId,
        formData.menu_button_type,
        formData.menu_button_text || null,
        formData.menu_button_web_app_url || null
      );
      setSuccess('Botão de menu atualizado com sucesso!');
      await loadBotInfo();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao atualizar botão de menu');
    } finally {
      setLoading(false);
    }
  };

  const handleSetAdminRights = async (e) => {
    e.preventDefault();
    
    try {
      setLoading(true);
      setError('');
      
      const rights = {};
      Object.keys(formData.admin_rights).forEach(key => {
        rights[key] = formData.admin_rights[key] || false;
      });
      
      const response = await botFatherService.setMyDefaultAdministratorRights(botId, rights, false);
      
      if (response.success) {
        setSuccess('Direitos padrão de administrador atualizados com sucesso!');
        await loadBotInfo();
        setTimeout(() => setSuccess(''), 3000);
      } else {
        setError(response.error || 'Erro ao atualizar direitos de administrador');
      }
    } catch (err) {
      const errorMessage = err.response?.data?.error || err.response?.data?.errors || err.message || 'Erro ao atualizar direitos de administrador';
      setError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteCommands = async () => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja deletar todos os comandos do bot?',
      type: 'warning',
    });

    if (!confirmed) {
      return;
    }

    try {
      setLoading(true);
      setError('');
      await botFatherService.deleteMyCommands(botId);
      setSuccess('Comandos deletados com sucesso!');
      await loadBotInfo();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao deletar comandos');
    } finally {
      setLoading(false);
    }
  };

  const content = (
    <>
      <DialogComponent />
      <div className="bot-father-page">
        {loadingInfo ? (
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
            <div className="bot-father-content">
              <div className="bot-father-header">
                <div className="header-text">
                  <h1>Gerencie todas as configurações do seu bot</h1>
                  <p>Configure nome, descrição, botão de menu e direitos de administrador através da API do Telegram</p>
                </div>
                <div className="header-actions">
                  <button onClick={loadBotInfo} className="btn btn-update" disabled={loadingInfo}>
                    <FontAwesomeIcon icon={faSync} />
                    Atualizar
                  </button>
                </div>
              </div>

              {error && <div className="alert alert-error">{error}</div>}
              {success && <div className="alert alert-success">{success}</div>}

              {/* Informações Atuais */}
              <div className="bot-father-section">
                <h2 className="section-title">Informações Atuais do Bot</h2>
                <div className="info-grid">
                  <div className="info-item">
                    <label>Nome:</label>
                    <span>{botInfo.name || 'Não definido'}</span>
                  </div>
                  <div className="info-item">
                    <label>Descrição:</label>
                    <span>{botInfo.description || 'Não definida'}</span>
                  </div>
                  <div className="info-item">
                    <label>Descrição Curta:</label>
                    <span>{botInfo.short_description || 'Não definida'}</span>
                  </div>
                  <div className="info-item">
                    <label>Sobre:</label>
                    <span>{botInfo.about || 'Não definido'}</span>
                  </div>
                  <div className="info-item">
                    <label>Comandos Registrados:</label>
                    <span>{botInfo.commands?.length || 0} comando(s)</span>
                  </div>
                  <div className="info-item">
                    <label>Botão de Menu:</label>
                    <span>{botInfo.menu_button?.type || 'Padrão'}</span>
                  </div>
                </div>
              </div>

              {/* Alterar Nome */}
              <div className="bot-father-section">
                <h2 className="section-title">Alterar Nome do Bot</h2>
                <form onSubmit={handleSetName} className="bot-father-form">
                  <div className="form-group">
                    <label htmlFor="name">Nome do Bot (máx. 64 caracteres)</label>
                    <input
                      type="text"
                      id="name"
                      name="name"
                      value={formData.name}
                      onChange={handleChange}
                      maxLength={64}
                      required
                      placeholder="Digite o novo nome do bot"
                    />
                  </div>
                  <button type="submit" className="btn btn-primary" disabled={loading}>
                    {loading ? 'Atualizando...' : 'Atualizar Nome'}
                  </button>
                </form>
              </div>

              {/* Alterar Descrição */}
              <div className="bot-father-section">
                <h2 className="section-title">Alterar Descrição do Bot</h2>
                <form onSubmit={handleSetDescription} className="bot-father-form">
                  <div className="form-group">
                    <label htmlFor="description">Descrição (máx. 512 caracteres)</label>
                    <textarea
                      id="description"
                      name="description"
                      value={formData.description}
                      onChange={handleChange}
                      maxLength={512}
                      rows={4}
                      required
                      placeholder="Digite a descrição do bot"
                    />
                  </div>
                  <button type="submit" className="btn btn-primary" disabled={loading}>
                    {loading ? 'Atualizando...' : 'Atualizar Descrição'}
                  </button>
                </form>
              </div>

              {/* Alterar Descrição Curta */}
              <div className="bot-father-section">
                <h2 className="section-title">Alterar Descrição Curta do Bot</h2>
                <form onSubmit={handleSetShortDescription} className="bot-father-form">
                  <div className="form-group">
                    <label htmlFor="short_description">Descrição Curta (máx. 120 caracteres)</label>
                    <input
                      type="text"
                      id="short_description"
                      name="short_description"
                      value={formData.short_description}
                      onChange={handleChange}
                      maxLength={120}
                      required
                      placeholder="Digite a descrição curta do bot"
                    />
                    <small>{formData.short_description.length}/120 caracteres</small>
                  </div>
                  <button type="submit" className="btn btn-primary" disabled={loading}>
                    {loading ? 'Atualizando...' : 'Atualizar Descrição Curta'}
                  </button>
                </form>
              </div>

              {/* Alterar Sobre */}
              <div className="bot-father-section">
                <h2 className="section-title">Alterar Texto "Sobre" do Bot</h2>
                <form onSubmit={handleSetAbout} className="bot-father-form">
                  <div className="form-group">
                    <label htmlFor="about">Texto "Sobre" (máx. 120 caracteres)</label>
                    <input
                      type="text"
                      id="about"
                      name="about"
                      value={formData.about}
                      onChange={handleChange}
                      maxLength={120}
                      required
                      placeholder="Digite o texto 'sobre' do bot"
                    />
                    <small>{formData.about.length}/120 caracteres</small>
                  </div>
                  <button type="submit" className="btn btn-primary" disabled={loading}>
                    {loading ? 'Atualizando...' : 'Atualizar Texto "Sobre"'}
                  </button>
                </form>
              </div>

              {/* Configurar Botão de Menu */}
              <div className="bot-father-section">
                <h2 className="section-title">Configurar Botão de Menu</h2>
                <form onSubmit={handleSetMenuButton} className="bot-father-form">
                  <div className="form-group">
                    <label htmlFor="menu_button_type">Tipo de Botão</label>
                    <select
                      id="menu_button_type"
                      name="menu_button_type"
                      value={formData.menu_button_type}
                      onChange={handleChange}
                    >
                      <option value="default">Padrão</option>
                      <option value="commands">Comandos</option>
                      <option value="web_app">Web App</option>
                    </select>
                  </div>

                  {formData.menu_button_type === 'web_app' && (
                    <>
                      <div className="form-group">
                        <label htmlFor="menu_button_text">Texto do Botão</label>
                        <input
                          type="text"
                          id="menu_button_text"
                          name="menu_button_text"
                          value={formData.menu_button_text}
                          onChange={handleChange}
                          placeholder="Menu"
                        />
                      </div>
                      <div className="form-group">
                        <label htmlFor="menu_button_web_app_url">URL do Web App</label>
                        <input
                          type="url"
                          id="menu_button_web_app_url"
                          name="menu_button_web_app_url"
                          value={formData.menu_button_web_app_url}
                          onChange={handleChange}
                          placeholder="https://example.com"
                          required={formData.menu_button_type === 'web_app'}
                        />
                      </div>
                    </>
                  )}

                  <button type="submit" className="btn btn-primary" disabled={loading}>
                    {loading ? 'Atualizando...' : 'Atualizar Botão de Menu'}
                  </button>
                </form>
              </div>

              {/* Direitos Padrão de Administrador */}
              <div className="bot-father-section">
                <h2 className="section-title">Direitos Padrão de Administrador</h2>
                <p className="section-description">
                  Configure os direitos padrão que o bot terá quando for adicionado como administrador de um grupo.
                </p>
                <form onSubmit={handleSetAdminRights} className="bot-father-form">
                  <div className="admin-rights-grid">
                    {Object.keys(formData.admin_rights).map((key) => (
                      <div key={key} className="permission-item">
                        <label>
                          <input
                            type="checkbox"
                            name={`admin_rights_${key}`}
                            checked={formData.admin_rights[key]}
                            onChange={handleChange}
                          />
                          <span>{key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                        </label>
                      </div>
                    ))}
                  </div>
                  <button type="submit" className="btn btn-primary" disabled={loading}>
                    {loading ? 'Atualizando...' : 'Atualizar Direitos de Administrador'}
                  </button>
                </form>
              </div>

              {/* Comandos */}
              <div className="bot-father-section">
                <h2 className="section-title">Comandos do Bot</h2>
                {botInfo.commands && botInfo.commands.length > 0 ? (
                  <>
                    <div className="commands-list">
                      <table className="commands-table">
                        <thead>
                          <tr>
                            <th>Comando</th>
                            <th>Descrição</th>
                          </tr>
                        </thead>
                        <tbody>
                          {botInfo.commands.map((cmd, index) => (
                            <tr key={index}>
                              <td>/{cmd.command}</td>
                              <td>{cmd.description || '-'}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                    <button
                      onClick={handleDeleteCommands}
                      className="btn btn-danger"
                      disabled={loading}
                      style={{ marginTop: '16px' }}
                    >
                      <FontAwesomeIcon icon={faTrash} />
                      {loading ? 'Deletando...' : 'Deletar Todos os Comandos'}
                    </button>
                  </>
                ) : (
                  <p>Nenhum comando registrado.</p>
                )}
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

export default BotFather;
