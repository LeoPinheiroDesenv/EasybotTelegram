import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import Layout from '../components/Layout';
import useConfirm from '../hooks/useConfirm';
import useAlert from '../hooks/useAlert';
import downsellService from '../services/downsellService';
import paymentPlanService from '../services/paymentPlanService';
import './Downsell.css';

const Downsell = () => {
  const { confirm, DialogComponent: ConfirmDialog } = useConfirm();
  const { alert, DialogComponent: AlertDialog } = useAlert();
  const [searchParams] = useSearchParams();
  let botId = searchParams.get('botId');
  
  // Tenta obter botId do localStorage se não estiver na URL
  if (!botId) {
    const storedBotId = localStorage.getItem('selectedBotId');
    if (storedBotId) {
      botId = storedBotId;
    }
  }

  const [downsells, setDownsells] = useState([]);
  const [paymentPlans, setPaymentPlans] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingDownsell, setEditingDownsell] = useState(null);
  const [formData, setFormData] = useState({
    bot_id: botId || '',
    title: '',
    initial_media: null,
    message: '',
    plan_id: '',
    promotional_value: '',
    max_uses: '',
    trigger_after_minutes: '',
    trigger_event: 'payment_failed'
  });

  useEffect(() => {
    if (botId) {
      loadDownsells();
      loadPaymentPlans();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoadingData(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [botId]);

  const loadDownsells = async () => {
    try {
      setLoadingData(true);
      const downsellsData = await downsellService.getDownsells(botId);
      setDownsells(downsellsData);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar downsells');
    } finally {
      setLoadingData(false);
    }
  };

  const loadPaymentPlans = async () => {
    try {
      const plans = await paymentPlanService.getPaymentPlans(botId);
      setPaymentPlans(plans);
    } catch (err) {
      console.error('Erro ao carregar planos:', err);
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
      bot_id: botId || '',
      title: '',
      initial_media: null,
      message: '',
      plan_id: '',
      promotional_value: '',
      max_uses: '',
      trigger_after_minutes: '',
      trigger_event: 'payment_failed'
    });
    setShowCreateModal(true);
  };

  const handleEdit = (downsell) => {
    setEditingDownsell(downsell);
    setFormData({
      bot_id: downsell.bot_id || botId || '',
      title: downsell.title || '',
      initial_media: null, // Não carrega arquivo existente
      message: downsell.message || '',
      plan_id: downsell.plan_id || '',
      promotional_value: downsell.promotional_value || '',
      max_uses: downsell.max_uses || '',
      trigger_after_minutes: downsell.trigger_after_minutes || '',
      trigger_event: downsell.trigger_event || 'payment_failed'
    });
    setShowCreateModal(true);
  };

  const handleDelete = async (downsellId) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja deletar este downsell?',
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      setLoading(true);
      await downsellService.deleteDownsell(downsellId);
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
    if (!formData.message || !formData.plan_id || !formData.title || !formData.promotional_value || !formData.trigger_after_minutes) {
      await alert('Por favor, preencha todos os campos obrigatórios', 'Atenção', 'info');
      return;
    }

    setError('');
    setLoading(true);

    try {
      const downsellData = {
        bot_id: botId,
        plan_id: formData.plan_id,
        title: formData.title,
        initial_media: formData.initial_media,
        message: formData.message,
        promotional_value: parseFloat(formData.promotional_value),
        max_uses: formData.max_uses ? parseInt(formData.max_uses) : null,
        trigger_after_minutes: parseInt(formData.trigger_after_minutes),
        trigger_event: formData.trigger_event
      };

      if (editingDownsell) {
        await downsellService.updateDownsell(editingDownsell.id, downsellData);
        setSuccess('Downsell atualizado com sucesso!');
      } else {
        await downsellService.createDownsell(downsellData);
        setSuccess('Downsell criado com sucesso!');
      }
      
      setShowCreateModal(false);
      loadDownsells();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.errors?.message?.[0] || 'Erro ao salvar downsell');
    } finally {
      setLoading(false);
    }
  };

  if (loadingData) {
    return (
      <Layout>
        <div className="downsell-page">
          <div className="loading-container">Carregando downsells...</div>
        </div>
      </Layout>
    );
  }

  if (!botId) {
    return (
      <Layout>
        <div className="downsell-page">
          <div className="error-container">
            <p>{error}</p>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <ConfirmDialog />
      <AlertDialog />
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
                      <td>{downsell.title}</td>
                      <td>{downsell.plan?.title || '-'}</td>
                      <td>
                        <span className={`status-badge status-${downsell.active ? 'active' : 'inactive'}`}>
                          {downsell.active ? 'Ativo' : 'Inativo'}
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
                    <label>
                      Selecione o plano para que eu possa aplicar o downsell <span className="required-asterisk">*</span>
                    </label>
                    <select
                      name="plan_id"
                      value={formData.plan_id}
                      onChange={handleChange}
                      className="form-input"
                      required
                    >
                      <option value="">Selecione uma opção</option>
                      {paymentPlans.map(plan => (
                        <option key={plan.id} value={plan.id}>
                          {plan.title} - R$ {parseFloat(plan.price).toFixed(2)}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="form-group">
                    <label>
                      Evento de Disparo <span className="required-asterisk">*</span>
                    </label>
                    <select
                      name="trigger_event"
                      value={formData.trigger_event}
                      onChange={handleChange}
                      className="form-input"
                      required
                    >
                      <option value="payment_failed">Pagamento Falhou</option>
                      <option value="payment_canceled">Pagamento Cancelado</option>
                      <option value="checkout_abandoned">Checkout Abandonado</option>
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
                    {loading ? 'Salvando...' : (editingDownsell ? 'Atualizar' : 'Criar mensagem')}
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

