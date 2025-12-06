import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import Layout from '../components/Layout';
import paymentPlanService from '../services/paymentPlanService';
import paymentCycleService from '../services/paymentCycleService';
import useConfirm from '../hooks/useConfirm';
import './PaymentPlans.css';

const PaymentPlans = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const { botId } = useParams();
  const [paymentPlans, setPaymentPlans] = useState([]);
  const [paymentCycles, setPaymentCycles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showPixModal, setShowPixModal] = useState(false);
  const [selectedPlan, setSelectedPlan] = useState(null);
  const [selectedPlanForPix, setSelectedPlanForPix] = useState(null);
  const [language, setLanguage] = useState('pt');
  const [formData, setFormData] = useState({
    title: '',
    price: '',
    charge_period: 'month',
    cycle: 1,
    payment_cycle_id: null, // ID do ciclo selecionado
    message: '',
    pix_message: '',
    active: true
  });

  useEffect(() => {
    if (!botId) {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoading(false);
      return;
    }
    localStorage.setItem('selectedBotId', botId);
    loadPaymentPlans(botId);
    loadPaymentCycles();
  }, [botId]);

  const loadPaymentCycles = async () => {
    try {
      const cycles = await paymentCycleService.getActiveCycles();
      setPaymentCycles(cycles);
    } catch (err) {
      console.error('Erro ao carregar ciclos de pagamento:', err);
      // Se falhar, usa valores padrão
      setPaymentCycles([]);
    }
  };

  const loadPaymentPlans = async (id) => {
    try {
      setLoading(true);
      const plans = await paymentPlanService.getAllPaymentPlans(id, false);
      setPaymentPlans(plans);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar planos de pagamento');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    
    // Se estiver mudando o ciclo, atualiza também o charge_period
    if (name === 'payment_cycle_id') {
      const selectedCycle = paymentCycles.find(c => c.id === parseInt(value));
      const chargePeriod = mapCycleToChargePeriod(value);
      setFormData({
        ...formData,
        payment_cycle_id: value ? parseInt(value) : null,
        charge_period: chargePeriod,
        cycle: selectedCycle ? Math.max(1, Math.floor(selectedCycle.days / (chargePeriod === 'day' ? 1 : chargePeriod === 'month' ? 30 : 365))) : 1
      });
    } else {
      setFormData({
        ...formData,
        [name]: type === 'checkbox' ? checked : value
      });
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    try {
      // Prepara os dados, garantindo que payment_cycle_id seja enviado
      const submitData = {
        ...formData,
        bot_id: botId,
        payment_cycle_id: formData.payment_cycle_id || null
      };

      if (selectedPlan) {
        await paymentPlanService.updatePaymentPlan(selectedPlan.id, submitData);
        setSuccess('Plano de pagamento atualizado com sucesso!');
      } else {
        await paymentPlanService.createPaymentPlan(submitData);
        setSuccess('Plano de pagamento criado com sucesso!');
      }
      setShowCreateModal(false);
      setShowEditModal(false);
      setSelectedPlan(null);
      setFormData({
        title: '',
        price: '',
        charge_period: 'month',
        cycle: 1,
        payment_cycle_id: paymentCycles.find(c => c.days === 30)?.id || paymentCycles[0]?.id || null,
        message: '',
        pix_message: '',
        active: true
      });
      loadPaymentPlans(botId);
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar plano de pagamento');
    }
  };

  const handleOpenCreateModal = () => {
    const defaultCycle = paymentCycles.find(c => c.days === 30) || paymentCycles[0];
    setFormData({
      title: '',
      price: '',
      charge_period: defaultCycle ? mapCycleToChargePeriod(defaultCycle.id.toString()) : 'month',
      cycle: 1,
      payment_cycle_id: defaultCycle?.id || null,
      message: '',
      pix_message: '',
      active: true
    });
    setShowCreateModal(true);
  };

  const handleEdit = (plan) => {
    setSelectedPlan(plan);
    // Usa o payment_cycle_id do plano se existir, senão tenta encontrar baseado no charge_period
    const cycleId = plan.payment_cycle_id || findCycleByChargePeriod(plan.charge_period, plan.cycle);
    setFormData({
      title: plan.title,
      price: plan.price,
      charge_period: plan.charge_period,
      cycle: plan.cycle,
      payment_cycle_id: cycleId || null,
      message: plan.message || '',
      pix_message: plan.pix_message || '',
      active: plan.active
    });
    setShowEditModal(true);
  };

  const handleDelete = async (id) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja excluir este plano de pagamento?',
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      await paymentPlanService.deletePaymentPlan(id, botId);
      setSuccess('Plano de pagamento excluído com sucesso!');
      loadPaymentPlans(botId);
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao excluir plano de pagamento');
    }
  };


  const handleOpenPixModal = () => {
    setSelectedPlanForPix(null);
    setFormData(prev => ({
      ...prev,
      pix_message: ''
    }));
    setShowPixModal(true);
  };

  const handlePlanSelectForPix = (planId) => {
    const plan = paymentPlans.find(p => p.id === planId);
    if (plan) {
      setSelectedPlanForPix(plan);
      setFormData(prev => ({
        ...prev,
        pix_message: plan.pix_message || ''
      }));
    }
  };

  const handleSavePixMessage = async () => {
    if (!selectedPlanForPix) {
      setError('Selecione um plano primeiro');
      return;
    }

    try {
      await paymentPlanService.updatePaymentPlan(selectedPlanForPix.id, {
        bot_id: botId,
        pix_message: formData.pix_message
      });
      setSuccess('Mensagem PIX atualizada com sucesso!');
      setShowPixModal(false);
      setSelectedPlanForPix(null);
      setFormData(prev => ({
        ...prev,
        pix_message: ''
      }));
      loadPaymentPlans(botId);
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar mensagem PIX');
    }
  };

  // Função para mapear ciclo selecionado para charge_period
  const mapCycleToChargePeriod = (cycleId) => {
    const cycle = paymentCycles.find(c => c.id === parseInt(cycleId));
    if (!cycle) return 'month'; // padrão
    
    // Mapeia baseado nos dias do ciclo
    if (cycle.days === 0) return 'year'; // vitalício
    if (cycle.days <= 1) return 'day';
    if (cycle.days <= 31) return 'month';
    return 'year';
  };

  // Função para encontrar o ciclo baseado no charge_period atual
  const findCycleByChargePeriod = (chargePeriod, cycleDays = 1) => {
    // Tenta encontrar um ciclo que corresponda ao charge_period
    if (chargePeriod === 'day' && cycleDays <= 1) {
      return paymentCycles.find(c => c.days === 1)?.id;
    } else if (chargePeriod === 'month' && cycleDays <= 31) {
      return paymentCycles.find(c => c.days === 30 || c.days === 7)?.id || paymentCycles.find(c => c.days > 1 && c.days <= 31)?.id;
    } else if (chargePeriod === 'year') {
      return paymentCycles.find(c => c.days === 365 || c.days === 0)?.id || paymentCycles.find(c => c.days > 31)?.id;
    }
    return paymentCycles[0]?.id; // retorna o primeiro ciclo como padrão
  };

  const getChargePeriodLabel = (period) => {
    const labels = {
      day: 'Dia',
      month: 'Mês',
      year: 'Ano'
    };
    return labels[period] || period;
  };

  if (loading && !botId) {
    return (
      <Layout>
        <div className="payment-plans-page">
          <div className="loading-container">Carregando...</div>
        </div>
      </Layout>
    );
  }

  if (!botId) {
    return (
      <Layout>
        <div className="payment-plans-page">
          <div className="error-container">
            <p>{error}</p>
            <button onClick={() => navigate('/')} className="btn btn-primary">
              Voltar ao Dashboard
            </button>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <DialogComponent />
      <div className="payment-plans-page">
        <div className="payment-plans-content">
          {/* Header Section */}
          <div className="payment-plans-header">
            <div className="header-text">
              <h1>Gerencie suas cobranças de qualquer lugar</h1>
              <p>Elabore planos, administre e estruture seus pagamentos. Escolha prazos e montantes</p>
            </div>
            <div className="header-actions">
              <button
                onClick={handleOpenCreateModal}
                className="btn btn-create"
              >
                Criar plano
              </button>
              <button
                onClick={handleOpenPixModal}
                className="btn btn-edit-pix"
              >
                Editar mensagem pix
              </button>
            </div>
          </div>

          {error && <div className="alert alert-error">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          {/* Earnings Card */}
          <div className="earnings-card">
            <div className="earnings-icon">0%</div>
            <div className="earnings-info">
              <div className="earnings-amount">R$ 0,00</div>
              <div className="earnings-label">Ganhos com planos de pagamentos criados esse mês</div>
            </div>
          </div>

          {/* Active Payment Plans Section */}
          <div className="payment-plans-section">
            <div className="section-header">
              <h2>Planos de pagamento ativos</h2>
              <select
                value={language}
                onChange={(e) => setLanguage(e.target.value)}
                className="language-selector"
              >
                <option value="pt">Português</option>
                <option value="en">English</option>
                <option value="es">Español</option>
              </select>
            </div>

            {loading ? (
              <div className="loading-container">Carregando planos...</div>
            ) : paymentPlans.length === 0 ? (
              <div className="empty-state">
                <p>Nenhum plano de pagamento criado ainda.</p>
                <button
                  onClick={handleOpenCreateModal}
                  className="btn btn-primary"
                >
                  Criar primeiro plano
                </button>
              </div>
            ) : (
              <table className="payment-plans-table">
                <thead>
                  <tr>
                    <th>Título</th>
                    <th>Preço</th>
                    <th>Cobrança</th>
                    <th>Ciclo</th>
                    <th>Mensagem</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {paymentPlans.map((plan) => (
                    <tr key={plan.id}>
                      <td>{plan.title}</td>
                      <td>R$ {parseFloat(plan.price).toFixed(2)}</td>
                      <td>{getChargePeriodLabel(plan.charge_period)}</td>
                      <td>{plan.cycle}</td>
                      <td>{plan.message || '-'}</td>
                      <td>
                        <div className="action-buttons">
                          <button
                            onClick={() => handleEdit(plan)}
                            className="btn-icon btn-edit"
                            title="Editar"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                          </button>
                          <button
                            onClick={() => handleDelete(plan.id)}
                            className="btn-icon btn-delete"
                            title="Excluir"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <polyline points="3 6 5 6 21 6"></polyline>
                              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>

        {/* Create/Edit Modal */}
        {(showCreateModal || showEditModal) && (
          <div className="modal-overlay" onClick={() => {
            setShowCreateModal(false);
            setShowEditModal(false);
            setSelectedPlan(null);
            setFormData({
              title: '',
              price: '',
              charge_period: 'month',
              cycle: 1,
              payment_cycle_id: paymentCycles.find(c => c.days === 30)?.id || paymentCycles[0]?.id || null,
              message: '',
              pix_message: '',
              active: true
            });
          }}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>{selectedPlan ? 'Editar plano' : 'Criar novo plano'}</h2>
                <button
                  className="modal-close"
                  onClick={() => {
                    setShowCreateModal(false);
                    setShowEditModal(false);
                    setSelectedPlan(null);
                  }}
                >
                  ×
                </button>
              </div>
              <form onSubmit={handleSubmit} className="modal-form">
                <div className="form-group">
                  <label>Título *</label>
                  <input
                    type="text"
                    name="title"
                    value={formData.title}
                    onChange={handleChange}
                    required
                    placeholder="Ex: Mensal, Trimestral, Anual"
                  />
                </div>
                <div className="form-group">
                  <label>Preço *</label>
                  <input
                    type="number"
                    name="price"
                    value={formData.price}
                    onChange={handleChange}
                    required
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                  />
                </div>
                <div className="form-row">
                  <div className="form-group">
                    <label>Período de cobrança *</label>
                    <select
                      name="payment_cycle_id"
                      value={formData.payment_cycle_id || ''}
                      onChange={handleChange}
                      required
                    >
                      <option value="">Selecione um período</option>
                      {paymentCycles.map((cycle) => (
                        <option key={cycle.id} value={cycle.id}>
                          {cycle.name} {cycle.description ? `- ${cycle.description}` : ''}
                        </option>
                      ))}
                    </select>
                    {paymentCycles.length === 0 && (
                      <small style={{ color: '#999' }}>Carregando períodos...</small>
                    )}
                  </div>
                  <div className="form-group">
                    <label>Ciclo *</label>
                    <input
                      type="number"
                      name="cycle"
                      value={formData.cycle}
                      onChange={handleChange}
                      required
                      min="1"
                      placeholder="1"
                    />
                    <small>Número de períodos no ciclo</small>
                  </div>
                </div>
                <div className="form-group">
                  <label>Mensagem</label>
                  <textarea
                    name="message"
                    value={formData.message}
                    onChange={handleChange}
                    rows="3"
                    placeholder="Mensagem que será enviada ao usuário"
                  />
                </div>
                <div className="form-group">
                  <label>
                    <input
                      type="checkbox"
                      name="active"
                      checked={formData.active}
                      onChange={handleChange}
                    />
                    Ativo
                  </label>
                </div>
                <div className="modal-footer">
                  <button
                    type="button"
                    onClick={() => {
                      setShowCreateModal(false);
                      setShowEditModal(false);
                      setSelectedPlan(null);
                    }}
                    className="btn btn-cancel"
                  >
                    Cancelar
                  </button>
                  <button type="submit" className="btn btn-primary">
                    {selectedPlan ? 'Atualizar' : 'Criar'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Pix Message Modal */}
        {showPixModal && (
          <div className="modal-overlay" onClick={() => {
            setShowPixModal(false);
            setSelectedPlanForPix(null);
            setFormData(prev => ({
              ...prev,
              pix_message: ''
            }));
          }}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>Editar mensagem PIX</h2>
                <button
                  className="modal-close"
                  onClick={() => {
                    setShowPixModal(false);
                    setSelectedPlanForPix(null);
                    setFormData(prev => ({
                      ...prev,
                      pix_message: ''
                    }));
                  }}
                >
                  ×
                </button>
              </div>
              <div className="modal-form">
                <div className="form-group">
                  <label>Selecione o plano *</label>
                  <select
                    value={selectedPlanForPix?.id || ''}
                    onChange={(e) => handlePlanSelectForPix(parseInt(e.target.value))}
                    className="form-input"
                    required
                  >
                    <option value="">Selecione um plano</option>
                    {paymentPlans.map((plan) => (
                      <option key={plan.id} value={plan.id}>
                        {plan.title} - R$ {parseFloat(plan.price).toFixed(2)}
                      </option>
                    ))}
                  </select>
                  {paymentPlans.length === 0 && (
                    <small style={{ color: '#999' }}>Nenhum plano disponível. Crie um plano primeiro.</small>
                  )}
                </div>
                {selectedPlanForPix && (
                  <div className="form-group">
                    <label>Mensagem PIX</label>
                    <textarea
                      name="pix_message"
                      value={formData.pix_message}
                      onChange={handleChange}
                      rows="5"
                      placeholder="Digite a mensagem PIX que será enviada aos usuários"
                    />
                  </div>
                )}
                <div className="modal-footer">
                  <button
                    type="button"
                    onClick={() => {
                      setShowPixModal(false);
                      setSelectedPlanForPix(null);
                      setFormData(prev => ({
                        ...prev,
                        pix_message: ''
                      }));
                    }}
                    className="btn btn-cancel"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={handleSavePixMessage}
                    className="btn btn-primary"
                    disabled={!selectedPlanForPix}
                  >
                    Salvar
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
};

export default PaymentPlans;

