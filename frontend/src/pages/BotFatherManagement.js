import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import Layout from '../components/Layout';
import botFatherService from '../services/botFatherService';
import botService from '../services/botService';
import useConfirm from '../hooks/useConfirm';
import useAlert from '../hooks/useAlert';
import RefreshButton from '../components/RefreshButton';
import './BotFatherManagement.css';

const BotFatherManagement = () => {
  const { botId } = useParams();
  const { confirm, DialogComponent } = useConfirm();
  const { alert, DialogComponent: AlertDialog } = useAlert();
  const [loading, setLoading] = useState(false);
  const [loadingInfo, setLoadingInfo] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [bot, setBot] = useState(null);
  const [botInfo, setBotInfo] = useState({
    name: '',
    description: '',
    short_description: '',
    about: '',
    commands: [],
    menu_button: null,
    default_administrator_rights: null,
  });

  // Form states
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
      loadBot();
      loadBotInfo();
    }
  }, [botId]);

  const loadBot = async () => {
    try {
      const data = await botService.getBotById(botId);
      setBot(data);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar bot');
    }
  };

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

  const handleRefresh = async () => {
    await loadBotInfo();
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

  const handleSetAdminRights = async (e) => {
    e.preventDefault();
    
    try {
      setLoading(true);
      setError('');
      
      // Envia TODAS as permissões (habilitadas e desabilitadas)
      // O backend irá mesclar com os direitos atuais
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
      console.error('Erro ao atualizar direitos:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleEnableReadAllMessages = async () => {
    const confirmed = await confirm({
      message: 'Isso habilitará a permissão "can_read_all_group_messages" para o bot. Quando o bot for adicionado como administrador de um grupo, ele poderá ler todas as mensagens. Deseja continuar?',
      type: 'info',
    });

    if (!confirmed) {
      return;
    }

    try {
      setLoading(true);
      setError('');
      
      // Obtém os direitos atuais e adiciona a permissão can_read_all_group_messages
      const currentRights = botInfo.default_administrator_rights || {};
      const rights = {
        ...currentRights,
        can_read_all_group_messages: true,
      };
      
      const response = await botFatherService.setMyDefaultAdministratorRights(botId, rights, false);
      
      if (response.success) {
        setSuccess('Permissão de leitura de todas as mensagens habilitada com sucesso!');
        await loadBotInfo();
        setTimeout(() => setSuccess(''), 3000);
      } else {
        setError(response.error || 'Erro ao habilitar permissão');
      }
    } catch (err) {
      const errorMessage = err.response?.data?.error || err.response?.data?.errors || err.message || 'Erro ao habilitar permissão';
      setError(errorMessage);
      console.error('Erro ao habilitar permissão:', err);
    } finally {
      setLoading(false);
    }
  };

  if (!botId) {
    return (
      <Layout>
        <div className="botfather-page">
          <div className="error-container">
            <p>Bot não selecionado. Por favor, selecione um bot primeiro.</p>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <DialogComponent />
      <AlertDialog />
      <div className="botfather-page">
        <div className="botfather-container">
          <div className="botfather-header">
            <div>
              <h1>Gerenciamento via BotFather</h1>
              <p>Gerencie todas as configurações do seu bot através da API do Telegram</p>
            </div>
            <RefreshButton onRefresh={handleRefresh} loading={loadingInfo} className="compact" />
          </div>

          {error && <div className="alert alert-error">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          {loadingInfo ? (
            <div className="loading">Carregando informações do bot...</div>
          ) : (
            <div className="botfather-content">
              {/* Informações Atuais */}
              <div className="botfather-section">
                <h2>Informações Atuais do Bot</h2>
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

              {/* Definir Nome */}
              <div className="botfather-section">
                <h2>Alterar Nome do Bot</h2>
                <form onSubmit={handleSetName} className="botfather-form">
                  <div className="form-group">
                    <label htmlFor="name">Nome do Bot (máx. 64 caracteres)</label>
                    <input
                      type="text"
                      id="name"
                      value={formData.name}
                      onChange={(e) => setFormData({ ...formData, name: e.target.value })}
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

              {/* Definir Descrição */}
              <div className="botfather-section">
                <h2>Alterar Descrição do Bot</h2>
                <form onSubmit={handleSetDescription} className="botfather-form">
                  <div className="form-group">
                    <label htmlFor="description">Descrição (máx. 512 caracteres)</label>
                    <textarea
                      id="description"
                      value={formData.description}
                      onChange={(e) => setFormData({ ...formData, description: e.target.value })}
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

              {/* Definir Descrição Curta */}
              <div className="botfather-section">
                <h2>Alterar Descrição Curta do Bot</h2>
                <form onSubmit={handleSetShortDescription} className="botfather-form">
                  <div className="form-group">
                    <label htmlFor="short_description">Descrição Curta (máx. 120 caracteres)</label>
                    <input
                      type="text"
                      id="short_description"
                      value={formData.short_description}
                      onChange={(e) => setFormData({ ...formData, short_description: e.target.value })}
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

              {/* Definir Sobre */}
              <div className="botfather-section">
                <h2>Alterar Texto "Sobre" do Bot</h2>
                <form onSubmit={handleSetAbout} className="botfather-form">
                  <div className="form-group">
                    <label htmlFor="about">Texto "Sobre" (máx. 120 caracteres)</label>
                    <input
                      type="text"
                      id="about"
                      value={formData.about}
                      onChange={(e) => setFormData({ ...formData, about: e.target.value })}
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
              <div className="botfather-section">
                <h2>Configurar Botão de Menu</h2>
                <form onSubmit={handleSetMenuButton} className="botfather-form">
                  <div className="form-group">
                    <label htmlFor="menu_button_type">Tipo de Botão</label>
                    <select
                      id="menu_button_type"
                      value={formData.menu_button_type}
                      onChange={(e) => setFormData({ ...formData, menu_button_type: e.target.value })}
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
                          value={formData.menu_button_text}
                          onChange={(e) => setFormData({ ...formData, menu_button_text: e.target.value })}
                          placeholder="Menu"
                        />
                      </div>
                      <div className="form-group">
                        <label htmlFor="menu_button_web_app_url">URL do Web App</label>
                        <input
                          type="url"
                          id="menu_button_web_app_url"
                          value={formData.menu_button_web_app_url}
                          onChange={(e) => setFormData({ ...formData, menu_button_web_app_url: e.target.value })}
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
              <div className="botfather-section">
                <h2>Direitos Padrão de Administrador</h2>
                <div className="admin-rights-info">
                  <p className="info-text">
                    Configure os direitos padrão que o bot terá quando for adicionado como administrador de um grupo.
                    <strong> Importante:</strong> A permissão "can_read_all_group_messages" permite que o bot leia todas as mensagens do grupo.
                  </p>
                  {botInfo.default_administrator_rights && (
                    <div className="current-rights">
                      <h3>Direitos Atuais:</h3>
                      <ul>
                        {Object.keys(botInfo.default_administrator_rights).map(key => (
                          botInfo.default_administrator_rights[key] && (
                            <li key={key}>
                              <span className="permission-badge">{key.replace(/_/g, ' ')}</span>
                            </li>
                          )
                        ))}
                      </ul>
                    </div>
                  )}
                  {(!botInfo.default_administrator_rights || !botInfo.default_administrator_rights.can_read_all_group_messages) && (
                    <div className="warning-box">
                      <p>
                        <strong>⚠️ Aviso:</strong> O bot não pode ler todas as mensagens do grupo. 
                        Isso impede o gerenciamento adequado do bot.
                      </p>
                      <button
                        onClick={handleEnableReadAllMessages}
                        className="btn btn-warning"
                        disabled={loading}
                        style={{ marginTop: '12px' }}
                      >
                        {loading ? 'Habilitando...' : '🔓 Habilitar Leitura de Todas as Mensagens'}
                      </button>
                    </div>
                  )}
                </div>
                <form onSubmit={handleSetAdminRights} className="botfather-form">
                  <div className="admin-rights-grid">
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_read_all_group_messages}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_read_all_group_messages: e.target.checked
                            }
                          })}
                        />
                        <span>Ler todas as mensagens do grupo</span>
                        <small>Permite que o bot leia todas as mensagens, não apenas comandos e menções</small>
                      </label>
                    </div>
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_manage_chat}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_manage_chat: e.target.checked
                            }
                          })}
                        />
                        <span>Gerenciar chat</span>
                      </label>
                    </div>
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_delete_messages}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_delete_messages: e.target.checked
                            }
                          })}
                        />
                        <span>Deletar mensagens</span>
                      </label>
                    </div>
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_manage_video_chats}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_manage_video_chats: e.target.checked
                            }
                          })}
                        />
                        <span>Gerenciar chamadas de vídeo</span>
                      </label>
                    </div>
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_restrict_members}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_restrict_members: e.target.checked
                            }
                          })}
                        />
                        <span>Restringir membros</span>
                      </label>
                    </div>
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_promote_members}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_promote_members: e.target.checked
                            }
                          })}
                        />
                        <span>Promover membros</span>
                      </label>
                    </div>
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_change_info}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_change_info: e.target.checked
                            }
                          })}
                        />
                        <span>Alterar informações do grupo</span>
                      </label>
                    </div>
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_invite_users}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_invite_users: e.target.checked
                            }
                          })}
                        />
                        <span>Convidar usuários</span>
                      </label>
                    </div>
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_post_messages}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_post_messages: e.target.checked
                            }
                          })}
                        />
                        <span>Postar mensagens (canais)</span>
                      </label>
                    </div>
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_edit_messages}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_edit_messages: e.target.checked
                            }
                          })}
                        />
                        <span>Editar mensagens (canais)</span>
                      </label>
                    </div>
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_pin_messages}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_pin_messages: e.target.checked
                            }
                          })}
                        />
                        <span>Fixar mensagens</span>
                      </label>
                    </div>
                    <div className="permission-item">
                      <label>
                        <input
                          type="checkbox"
                          checked={formData.admin_rights.can_manage_topics}
                          onChange={(e) => setFormData({
                            ...formData,
                            admin_rights: {
                              ...formData.admin_rights,
                              can_manage_topics: e.target.checked
                            }
                          })}
                        />
                        <span>Gerenciar tópicos</span>
                      </label>
                    </div>
                  </div>
                  <button type="submit" className="btn btn-primary" disabled={loading}>
                    {loading ? 'Atualizando...' : 'Atualizar Direitos de Administrador'}
                  </button>
                </form>
              </div>

              {/* Comandos */}
              <div className="botfather-section">
                <h2>Comandos do Bot</h2>
                {botInfo.commands && botInfo.commands.length > 0 ? (
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
                    <button
                      onClick={handleDeleteCommands}
                      className="btn btn-danger"
                      disabled={loading}
                      style={{ marginTop: '16px' }}
                    >
                      {loading ? 'Deletando...' : 'Deletar Todos os Comandos'}
                    </button>
                  </div>
                ) : (
                  <p>Nenhum comando registrado.</p>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </Layout>
  );
};

export default BotFatherManagement;

