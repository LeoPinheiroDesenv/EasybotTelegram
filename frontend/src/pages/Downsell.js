import React, { useState, useEffect } from 'react';
import Layout from '../components/Layout';
import './Downsell.css';

const Downsell = () => {
  const [downsells, setDownsells] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingDownsell, setEditingDownsell] = useState(null);
  const [formData, setFormData] = useState({
    title: '',
    initial_media: null,
    message: '',
    plan_id: '',
    promotional_value: '',
    quantity_uses: '',
    trigger_after: ''
  });

  useEffect(() => {
    loadDownsells();
  }, []);

  const loadDownsells = async () => {
    try {
      setLoadingData(true);
      // TODO: Implementar API para carregar downsells
      // Por enquanto, usando dados mockados
      setDownsells([]);
      setError('');
    } catch (err) {
      console.error('Erro ao carregar downsells:', err);
    } finally {
      setLoadingData(false);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, files } = e.target;
    setFormData({
      ...formData,
      [name]: type === 'file' ? files[0] : value
    });
  };

  const handleCreate = () => {
    setEditingDownsell(null);
    setFormData({
      title: '',
      initial_media: null,
      message: '',
      plan_id: '',
      promotional_value: '',
      quantity_uses: '',
      trigger_after: ''
    });
    setShowCreateModal(true);
  };

  const handleEdit = (downsell) => {
    setEditingDownsell(downsell);
    setFormData({
      title: downsell.title || '',
      initial_media: downsell.initial_media || null,
      message: downsell.message || '',
      plan_id: downsell.plan_id || '',
      promotional_value: downsell.promotional_value || '',
      quantity_uses: downsell.quantity_uses || '',
      trigger_after: downsell.trigger_after || ''
    });
    setShowCreateModal(true);
  };

  const handleDelete = async (downsellId) => {
    if (!window.confirm('Tem certeza que deseja deletar este downsell?')) {
      return;
    }

    try {
      setLoading(true);
      // TODO: Implementar API para deletar downsell
      setDownsells(downsells.filter(downsell => downsell.id !== downsellId));
      setSuccess('Downsell deletado com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao deletar downsell');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!formData.message || !formData.plan_id) {
      alert('Por favor, preencha todos os campos obrigatórios');
      return;
    }

    setError('');
    setLoading(true);

    try {
      // TODO: Implementar API para salvar downsell
      if (editingDownsell) {
        setDownsells(downsells.map(downsell => 
          downsell.id === editingDownsell.id ? { ...downsell, ...formData } : downsell
        ));
        setSuccess('Downsell atualizado com sucesso!');
      } else {
        const newDownsell = {
          id: Date.now(),
          ...formData
        };
        setDownsells([...downsells, newDownsell]);
        setSuccess('Downsell criado com sucesso!');
      }
      setShowCreateModal(false);
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar downsell');
    } finally {
      setLoading(false);
    }
  };

  if (loadingData) {
    return (
      <Layout>
        <div className="downsell-page">
          <div className="loading-container">Carregando...</div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="downsell-page">
        <div className="downsell-content">
          <div className="downsell-header">
            <div className="downsell-title-section">
              <h1 className="downsell-title">Todos os disparos criados</h1>
              <div className="downsell-badge">
                {downsells.length}
              </div>
            </div>
            <div className="downsell-actions">
              <button
                onClick={handleCreate}
                className="btn-create-downsell"
              >
                Criar novo downsell
              </button>
            </div>
          </div>

          {error && <div className="alert alert-error">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          <div className="downsell-table-container">
            <table className="downsell-table">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Plano</th>
                  <th>Status</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                {downsells.length === 0 ? (
                  <tr>
                    <td colSpan="4" className="empty-state">
                      <div className="empty-message">
                        <p className="empty-title">Nenhum downsell encontrado</p>
                        <p className="empty-subtitle">Tente ajustar os filtros ou criar um novo disparo</p>
                      </div>
                    </td>
                  </tr>
                ) : (
                  downsells.map((downsell) => (
                    <tr key={downsell.id}>
                      <td>{downsell.title || downsell.name}</td>
                      <td>{downsell.plan_name || '-'}</td>
                      <td>
                        <span className={`status-badge status-${downsell.status || 'active'}`}>
                          {downsell.status === 'active' ? 'Ativo' : 'Inativo'}
                        </span>
                      </td>
                      <td>
                        <div className="action-buttons">
                          <button
                            onClick={() => handleEdit(downsell)}
                            className="btn-icon btn-edit"
                            title="Editar"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                          </button>
                          <button
                            onClick={() => handleDelete(downsell.id)}
                            className="btn-icon btn-delete"
                            title="Deletar"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <polyline points="3 6 5 6 21 6"></polyline>
                              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Create/Edit Modal */}
          {showCreateModal && (
            <div className="modal-overlay" onClick={() => setShowCreateModal(false)}>
              <div className="modal-content downsell-modal" onClick={(e) => e.stopPropagation()}>
                <div className="modal-header">
                  <div className="modal-title-section">
                    <h3 className="modal-title">Downsell</h3>
                    <p className="modal-subtitle">Use sua criatividade e capriche na construção da sua mensagem ;)</p>
                  </div>
                  <button
                    onClick={() => setShowCreateModal(false)}
                    className="btn-close"
                  >
                    ×
                  </button>
                </div>
                <div className="modal-body">
                  <div className="form-group">
                    <label>
                      Título <span className="required-asterisk">*</span>
                    </label>
                    <input
                      type="text"
                      name="title"
                      value={formData.title}
                      onChange={handleChange}
                      placeholder="E só para você se lembrar da sua oferta ;)"
                      className="form-input"
                      required
                    />
                  </div>

                  <div className="form-group">
                    <label>Mídia inicial</label>
                    <div className="file-upload-area">
                      <input
                        type="file"
                        name="initial_media"
                        id="initial-media-upload"
                        onChange={handleChange}
                        className="file-input"
                        accept="image/*,video/*"
                      />
                      <label htmlFor="initial-media-upload" className="file-upload-label">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="file-icon">
                          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                          <polyline points="17 8 12 3 7 8"></polyline>
                          <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <span className="file-upload-text">Clique para selecionar uma imagem ou arraste-a para esta área para fazer o upload</span>
                      </label>
                      <p className="file-upload-note">
                        Anexos devem ter até 2MB para imagens e até 20MB para videos
                      </p>
                      {formData.initial_media && (
                        <div className="file-selected">
                          <span>{formData.initial_media.name}</span>
                          <button
                            type="button"
                            onClick={() => setFormData({ ...formData, initial_media: null })}
                            className="file-remove-btn"
                          >
                            ×
                          </button>
                        </div>
                      )}
                    </div>
                  </div>

                  <div className="form-group">
                    <label>
                      Mensagem <span className="required-asterisk">*</span>
                    </label>
                    <textarea
                      name="message"
                      value={formData.message}
                      onChange={handleChange}
                      placeholder="Escreva a mensagem que será disparada"
                      className="form-input form-textarea"
                      rows="6"
                      required
                    />
                  </div>

                  <div className="form-group">
                    <label>Selecione o plano para que eu possa aplicar o downsell</label>
                    <select
                      name="plan_id"
                      value={formData.plan_id}
                      onChange={handleChange}
                      className="form-input"
                      required
                    >
                      <option value="">Selecione uma opção</option>
                      {/* TODO: Carregar planos da API */}
                    </select>
                  </div>

                  <div className="form-group">
                    <label>
                      Valor Promocional <span className="required-asterisk">*</span>
                    </label>
                    <input
                      type="text"
                      name="promotional_value"
                      value={formData.promotional_value}
                      onChange={handleChange}
                      placeholder="50.00"
                      className="form-input"
                      required
                    />
                  </div>

                  <div className="form-group">
                    <label>
                      Quantidade de usos <span className="required-asterisk">*</span>
                    </label>
                    <input
                      type="number"
                      name="quantity_uses"
                      value={formData.quantity_uses}
                      onChange={handleChange}
                      placeholder="3"
                      className="form-input"
                      required
                    />
                  </div>

                  <div className="form-group">
                    <label>
                      Disparar depois de <span className="required-asterisk">*</span>
                    </label>
                    <input
                      type="text"
                      name="trigger_after"
                      value={formData.trigger_after}
                      onChange={handleChange}
                      placeholder="Tempo em minutos Ex.: 4"
                      className="form-input"
                      required
                    />
                  </div>
                </div>
                <div className="modal-footer">
                  <button
                    onClick={() => setShowCreateModal(false)}
                    className="btn btn-cancel"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={handleSave}
                    className="btn btn-create-message"
                    disabled={loading}
                  >
                    {loading ? 'Salvando...' : 'Criar mensagem'}
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </Layout>
  );
};

export default Downsell;

