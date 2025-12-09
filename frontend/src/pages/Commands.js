import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEdit, faTrash, faPlus, faCheck, faTimes, faSync } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import botCommandService from '../services/botCommandService';
import useConfirm from '../hooks/useConfirm';
import RefreshButton from '../components/RefreshButton';
import './Commands.css';

const Commands = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const { botId } = useParams();
  
  const [commands, setCommands] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
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
  }, [botId]);

  const loadCommands = async () => {
    try {
      setLoadingData(true);
      const commandsData = await botCommandService.getCommands(botId);
      setCommands(commandsData);
      setError('');
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

  const handleAdd = () => {
    setEditingCommand(null);
    setFormData({
      command: '',
      response: '',
      description: '',
      active: true
    });
    setShowAddModal(true);
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
    setShowAddModal(true);
    setError('');
  };

  const handleDelete = async (commandId) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja excluir este comando?',
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      setLoading(true);
      await botCommandService.deleteCommand(botId, commandId);
      setSuccess('Comando excluído com sucesso!');
      loadCommands();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao excluir comando');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Validação
    if (!formData.command.trim()) {
      setError('O nome do comando é obrigatório');
      return;
    }

    if (!formData.response.trim()) {
      setError('A resposta do comando é obrigatória');
      return;
    }

    // Valida formato do comando (apenas letras, números e underscore)
    const commandRegex = /^[a-zA-Z0-9_]+$/;
    if (!commandRegex.test(formData.command.trim())) {
      setError('O comando deve conter apenas letras, números e underscore (sem espaços ou caracteres especiais)');
      return;
    }

    try {
      setLoading(true);
      setError('');

      if (editingCommand) {
        // Atualizar comando existente (não permite alterar o nome do comando)
        await botCommandService.updateCommand(botId, editingCommand.id, {
          response: formData.response,
          description: formData.description,
          active: formData.active
        });
        setSuccess('Comando atualizado com sucesso!');
      } else {
        // Criar novo comando
        await botCommandService.createCommand(botId, {
          command: formData.command.trim(),
          response: formData.response,
          description: formData.description,
          active: formData.active
        });
        setSuccess('Comando criado com sucesso!');
      }

      setShowAddModal(false);
      loadCommands();
      setTimeout(() => setSuccess(''), 3000);
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
    
    if (!confirmed) {
      return;
    }

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
    
    if (!confirmed) {
      return;
    }

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

  const handleCloseModal = () => {
    setShowAddModal(false);
    setEditingCommand(null);
    setFormData({
      command: '',
      response: '',
      description: '',
      active: true
    });
    setError('');
  };

  if (loadingData) {
    return (
      <Layout>
        <div className="commands-container">
          <div className="loading">Carregando comandos...</div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <DialogComponent />
      <div className="commands-container">
        <div className="commands-content">
          <div className="commands-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h1>Comandos do Bot</h1>
          <RefreshButton onRefresh={loadCommands} loading={loadingData} className="compact" />
          <div className="commands-actions">
            <button
              className="btn btn-register"
              onClick={handleRegisterCommands}
              disabled={registering || commands.length === 0}
              title="Registrar comandos no Telegram"
            >
              <FontAwesomeIcon icon={faSync} spin={registering} />
              {registering ? ' Registrando...' : ' Registrar no Telegram'}
            </button>
            <button
              className="btn btn-secondary"
              onClick={() => {
                setShowTelegramCommands(!showTelegramCommands);
                if (!showTelegramCommands) {
                  loadTelegramCommands();
                }
              }}
              title="Ver comandos registrados no Telegram"
            >
              {showTelegramCommands ? 'Ocultar' : 'Ver'} Comandos no Telegram
            </button>
            <button
              className="btn btn-primary"
              onClick={handleAdd}
              disabled={loading}
            >
              <FontAwesomeIcon icon={faPlus} />
              Adicionar Comando
            </button>
          </div>
        </div>

        {error && (
          <div className="alert alert-error">
            {error}
          </div>
        )}

        {success && (
          <div className="alert alert-success">
            {success}
          </div>
        )}

        {showTelegramCommands && (
          <div className="telegram-commands-section">
            <div className="section-header">
              <h3>Comandos Registrados no Telegram</h3>
            </div>
            {telegramCommands.length === 0 ? (
              <div className="empty-state">
                <p>Nenhum comando registrado no Telegram.</p>
              </div>
            ) : (
              <div className="telegram-commands-list">
                <table className="commands-table">
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
                              className="btn-icon btn-delete"
                              onClick={() => handleDeleteTelegramCommand(cmd.command)}
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

        {commands.length === 0 ? (
          <div className="empty-state">
            <p>Nenhum comando cadastrado ainda.</p>
            <p>Clique em "Adicionar Comando" para criar o primeiro comando do bot.</p>
          </div>
        ) : (
          <div className="commands-list">
            <table className="commands-table">
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
                          className="btn-icon btn-edit"
                          onClick={() => handleEdit(command)}
                          title="Editar comando"
                          disabled={loading}
                        >
                          <FontAwesomeIcon icon={faEdit} />
                        </button>
                        <button
                          className="btn-icon btn-delete"
                          onClick={() => handleDelete(command.id)}
                          title="Excluir comando"
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

        {showAddModal && (
          <div className="modal-overlay" onClick={handleCloseModal}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>{editingCommand ? 'Editar Comando' : 'Adicionar Comando'}</h2>
                <button className="modal-close" onClick={handleCloseModal}>
                  <FontAwesomeIcon icon={faTimes} />
                </button>
              </div>

              <form onSubmit={handleSubmit}>
                <div className="form-group">
                  <label htmlFor="command">
                    Nome do Comando <span className="required">*</span>
                  </label>
                  <input
                    type="text"
                    id="command"
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
                  <label htmlFor="description">Descrição</label>
                  <input
                    type="text"
                    id="description"
                    name="description"
                    value={formData.description}
                    onChange={handleChange}
                    placeholder="Descrição do comando (opcional)"
                    maxLength={255}
                  />
                  <small>Descrição que aparecerá na lista de comandos do Telegram</small>
                </div>

                <div className="form-group">
                  <label htmlFor="response">
                    Resposta <span className="required">*</span>
                  </label>
                  <textarea
                    id="response"
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

                {error && (
                  <div className="alert alert-error">
                    {error}
                  </div>
                )}

                <div className="modal-actions">
                  <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={handleCloseModal}
                    disabled={loading}
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={loading}
                  >
                    {loading ? 'Salvando...' : editingCommand ? 'Atualizar' : 'Criar'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
        </div>
      </div>
    </Layout>
  );
};

export default Commands;

