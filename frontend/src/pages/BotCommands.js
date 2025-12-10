import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEdit, faTrash, faPlus, faCheck, faTimes, faSync } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import { useManageBot } from '../contexts/ManageBotContext';
import botCommandService from '../services/botCommandService';
import useConfirm from '../hooks/useConfirm';
import './BotCommands.css';

const BotCommands = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const { botId } = useParams();
  const isInManageBot = useManageBot();

  const [commands, setCommands] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editingCommand, setEditingCommand] = useState(null);
  const [registering, setRegistering] = useState(false);
  const [deletingTelegramCommands, setDeletingTelegramCommands] = useState(false);
  const [telegramCommands, setTelegramCommands] = useState([]);
  const [showTelegramCommands, setShowTelegramCommands] = useState(false);
  const [formData, setFormData] = useState({
    command: '',
    response: '',
    description: '',
    active: true
  });

  useEffect(() => {
    if (botId) {
      loadCommands();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoadingData(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [botId]);

  const loadCommands = async () => {
    try {
      setLoadingData(true);
      setError('');
      const commandsData = await botCommandService.getCommands(botId);
      setCommands(commandsData);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar comandos');
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

  const handleCreate = () => {
    setEditingCommand(null);
    setFormData({
      command: '',
      response: '',
      description: '',
      active: true
    });
    setShowModal(true);
    setError('');
  };

  const handleEdit = (command) => {
    setEditingCommand(command);
    setFormData({
      command: command.command,
      response: command.response,
      description: command.description || '',
      active: command.active
    });
    setShowModal(true);
    setError('');
  };

  const handleDelete = async (commandId) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja remover este comando?',
      type: 'warning',
    });

    if (!confirmed) return;

    setLoading(true);
    setError('');
    setSuccess('');

    try {
      await botCommandService.deleteCommand(botId, commandId);
      setSuccess('Comando removido com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
      loadCommands();
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao remover comando');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!formData.command.trim()) {
      setError('O nome do comando é obrigatório');
      return;
    }

    if (!formData.response.trim()) {
      setError('A resposta do comando é obrigatória');
      return;
    }

    const commandRegex = /^[a-zA-Z0-9_]+$/;
    if (!commandRegex.test(formData.command.trim())) {
      setError('O comando deve conter apenas letras, números e underscore (sem espaços ou caracteres especiais)');
      return;
    }

    setError('');
    setSuccess('');
    setLoading(true);

    try {
      if (editingCommand) {
        await botCommandService.updateCommand(botId, editingCommand.id, {
          response: formData.response,
          description: formData.description,
          active: formData.active
        });
        setSuccess('Comando atualizado com sucesso!');
      } else {
        await botCommandService.createCommand(botId, {
          command: formData.command.trim(),
          response: formData.response,
          description: formData.description,
          active: formData.active
        });
        setSuccess('Comando criado com sucesso!');
      }
      setTimeout(() => setSuccess(''), 3000);
      setShowModal(false);
      loadCommands();
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.errors?.command?.[0] || 'Erro ao salvar comando');
    } finally {
      setLoading(false);
    }
  };

  const handleRegisterCommands = async () => {
    const confirmed = await confirm({
      message: 'Deseja registrar os comandos no Telegram? Isso atualizará a lista de comandos disponíveis no bot.',
      type: 'info',
    });

    if (!confirmed) return;

    try {
      setRegistering(true);
      setError('');
      await botCommandService.registerCommands(botId);
      setSuccess('Comandos registrados no Telegram com sucesso!');
      if (showTelegramCommands) {
        await loadTelegramCommands();
      }
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao registrar comandos no Telegram');
    } finally {
      setRegistering(false);
    }
  };

  const loadTelegramCommands = async () => {
    try {
      const commands = await botCommandService.getTelegramCommands(botId);
      setTelegramCommands(commands);
    } catch (err) {
      console.error('Erro ao carregar comandos do Telegram:', err);
    }
  };

  const handleDeleteTelegramCommand = async (commandName) => {
    const confirmed = await confirm({
      message: `Tem certeza que deseja deletar o comando "/${commandName}" do Telegram?`,
      type: 'warning',
    });

    if (!confirmed) return;

    setDeletingTelegramCommands(true);
    setError('');
    setSuccess('');

    try {
      await botCommandService.deleteTelegramCommand(botId, commandName);
      setSuccess(`Comando "/${commandName}" deletado do Telegram com sucesso!`);
      await loadTelegramCommands();
      setTimeout(() => setSuccess(''), 5000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao deletar comando do Telegram');
    } finally {
      setDeletingTelegramCommands(false);
    }
  };

  const content = (
    <>
      <DialogComponent />
      <div className="bot-commands-page">
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
            <div className="bot-commands-content">
              <div className="bot-commands-header">
                <p className="bot-commands-description">
                  Gerencie os comandos do seu bot. Crie comandos personalizados que serão executados quando os usuários digitarem no Telegram.
                </p>
                <p className="bot-commands-description">
                  Após criar os comandos, registre-os no Telegram para que apareçam na lista de comandos do bot.
                </p>
              </div>

              {error && <div className="alert alert-error">{error}</div>}
              {success && <div className="alert alert-success">{success}</div>}

              {/* Actions */}
              <div className="bot-commands-actions">
                <button
                  onClick={loadCommands}
                  className="btn btn-update"
                  disabled={loading}
                >
                  <FontAwesomeIcon icon={faSync} />
                  Atualizar
                </button>
                <button
                  onClick={handleRegisterCommands}
                  className="btn btn-register"
                  disabled={registering || commands.length === 0}
                >
                  <FontAwesomeIcon icon={faSync} spin={registering} />
                  {registering ? ' Registrando...' : ' Registrar no Telegram'}
                </button>
                <button
                  onClick={() => {
                    setShowTelegramCommands(!showTelegramCommands);
                    if (!showTelegramCommands) {
                      loadTelegramCommands();
                    }
                  }}
                  className="btn btn-secondary"
                >
                  {showTelegramCommands ? 'Ocultar' : 'Ver'} Comandos no Telegram
                </button>
                <button onClick={handleCreate} className="btn btn-primary">
                  <FontAwesomeIcon icon={faPlus} />
                  Adicionar Comando
                </button>
              </div>

              {/* Telegram Commands Section */}
              {showTelegramCommands && (
                <div className="telegram-commands-section">
                  <h2 className="section-title">Comandos Registrados no Telegram</h2>
                  {telegramCommands.length === 0 ? (
                    <div className="empty-state">
                      <p>Nenhum comando registrado no Telegram.</p>
                    </div>
                  ) : (
                    <div className="table-wrapper">
                      <table className="bot-commands-table">
                        <thead>
                          <tr>
                            <th>Comando</th>
                            <th>Descrição</th>
                            <th>Ações</th>
                          </tr>
                        </thead>
                        <tbody>
                          {telegramCommands.map((cmd, index) => (
                            <tr key={index}>
                              <td>
                                <code>/{cmd.command}</code>
                              </td>
                              <td>{cmd.description || '-'}</td>
                              <td>
                                <div className="action-buttons">
                                  <button
                                    onClick={() => handleDeleteTelegramCommand(cmd.command)}
                                    className="btn-icon btn-delete"
                                    disabled={deletingTelegramCommands}
                                    title="Deletar comando do Telegram"
                                  >
                                    <FontAwesomeIcon icon={faTrash} />
                                  </button>
                                </div>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              )}

              {/* Commands List */}
              <div className="bot-commands-section">
                <h2 className="section-title">Comandos do Bot</h2>
                {commands.length === 0 ? (
                  <div className="empty-state">
                    <p>Nenhum comando cadastrado ainda.</p>
                    <p>Clique em "Adicionar Comando" para criar o primeiro comando do bot.</p>
                  </div>
                ) : (
                  <div className="table-wrapper">
                    <table className="bot-commands-table">
                      <thead>
                        <tr>
                          <th>Comando</th>
                          <th>Descrição</th>
                          <th>Resposta</th>
                          <th>Status</th>
                          <th>Uso</th>
                          <th>Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        {commands.map((command) => (
                          <tr key={command.id}>
                            <td>
                              <code>/{command.command}</code>
                            </td>
                            <td>{command.description || '-'}</td>
                            <td className="response-cell">
                              {command.response.length > 50
                                ? `${command.response.substring(0, 50)}...`
                                : command.response}
                            </td>
                            <td>
                              <span className={`status-badge ${command.active ? 'active' : 'inactive'}`}>
                                {command.active ? (
                                  <>
                                    <FontAwesomeIcon icon={faCheck} /> Ativo
                                  </>
                                ) : (
                                  <>
                                    <FontAwesomeIcon icon={faTimes} /> Inativo
                                  </>
                                )}
                              </span>
                            </td>
                            <td>{command.usage_count || 0}</td>
                            <td>
                              <div className="action-buttons">
                                <button
                                  onClick={() => handleEdit(command)}
                                  className="btn-icon btn-edit"
                                  title="Editar comando"
                                  disabled={loading}
                                >
                                  <FontAwesomeIcon icon={faEdit} />
                                </button>
                                <button
                                  onClick={() => handleDelete(command.id)}
                                  className="btn-icon btn-delete"
                                  title="Remover comando"
                                  disabled={loading}
                                >
                                  <FontAwesomeIcon icon={faTrash} />
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>

            {/* Modal */}
            {showModal && (
              <div className="modal-overlay" onClick={() => setShowModal(false)}>
                <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                  <div className="modal-header">
                    <h2>{editingCommand ? 'Editar Comando' : 'Adicionar Novo Comando'}</h2>
                    <button className="modal-close" onClick={() => setShowModal(false)}>
                      ×
                    </button>
                  </div>
                  <form className="modal-form" onSubmit={handleSubmit}>
                    <div className="form-group">
                      <label>
                        Nome do Comando <span className="required">*</span>
                      </label>
                      <input
                        type="text"
                        name="command"
                        value={formData.command}
                        onChange={handleChange}
                        placeholder="Ex: info, ajuda, sobre"
                        disabled={!!editingCommand}
                        required
                        pattern="[a-zA-Z0-9_]+"
                        title="Apenas letras, números e underscore (sem espaços)"
                      />
                      <small>O comando será usado como /{formData.command || 'comando'}</small>
                    </div>

                    <div className="form-group">
                      <label>Descrição</label>
                      <input
                        type="text"
                        name="description"
                        value={formData.description}
                        onChange={handleChange}
                        placeholder="Descrição do comando (opcional)"
                        maxLength={255}
                      />
                      <small>Descrição que aparecerá na lista de comandos do Telegram</small>
                    </div>

                    <div className="form-group">
                      <label>
                        Resposta <span className="required">*</span>
                      </label>
                      <textarea
                        name="response"
                        value={formData.response}
                        onChange={handleChange}
                        placeholder="Resposta que o bot enviará quando o comando for executado"
                        rows={6}
                        required
                      />
                    </div>

                    <div className="form-group">
                      <label className="checkbox-label">
                        <input
                          type="checkbox"
                          name="active"
                          checked={formData.active}
                          onChange={handleChange}
                        />
                        <span>Comando ativo</span>
                      </label>
                      <small>Comandos inativos não serão registrados no Telegram</small>
                    </div>

                    <div className="modal-footer">
                      <button
                        type="button"
                        className="btn btn-cancel"
                        onClick={() => setShowModal(false)}
                      >
                        Cancelar
                      </button>
                      <button type="submit" className="btn btn-primary" disabled={loading}>
                        {loading ? 'Salvando...' : editingCommand ? 'Atualizar' : 'Criar'}
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            )}
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

export default BotCommands;
