import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEdit, faTrash, faPlus, faSync, faEdit as faEditIcon } from '@fortawesome/free-solid-svg-icons';
import Layout from '../components/Layout';
import { useManageBot } from '../contexts/ManageBotContext';
import paymentPlanService from '../services/paymentPlanService';
import paymentCycleService from '../services/paymentCycleService';
import useConfirm from '../hooks/useConfirm';
import './PaymentPlans.css';

const PaymentPlans = () => {
  const { confirm, DialogComponent } = useConfirm();
  const navigate = useNavigate();
  const { botId } = useParams();
  const isInManageBot = useManageBot();

  const [paymentPlans, setPaymentPlans] = useState([]);
  const [paymentCycles, setPaymentCycles] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editingPlan, setEditingPlan] = useState(null);
  const [formData, setFormData] = useState({
    title: '',
    price: '',
    payment_cycle_id: '',
    charge_period: 'month',
    cycle: 1,
    message: '',
    pix_message: '',
    active: true,
  });

  useEffect(() => {
    if (botId) {
      loadData();
    } else {
      setError('Bot não selecionado. Por favor, selecione um bot primeiro.');
      setLoadingData(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [botId]);

  const loadData = async () => {
    try {
      setLoadingData(true);
      setError('');
      const [plans, cycles] = await Promise.all([
        paymentPlanService.getAllPaymentPlans(botId),
        paymentCycleService.getAllCycles(),
      ]);
      setPaymentPlans(plans);
      setPaymentCycles(cycles);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar dados');
    } finally {
      setLoadingData(false);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData({
      ...formData,
      [name]: type === 'checkbox' ? checked : type === 'number' ? parseFloat(value) || 0 : value,
    });
    setError('');
  };

  const handleCreate = () => {
    setEditingPlan(null);
    setFormData({
      title: '',
      price: '',
      payment_cycle_id: '',
      charge_period: 'month',
      cycle: 1,
      message: '',
      pix_message: '',
      active: true,
    });
    setShowModal(true);
  };

  const handleEdit = (plan) => {
    setEditingPlan(plan);
    setFormData({
      title: plan.title || '',
      price: plan.price || '',
      payment_cycle_id: plan.payment_cycle_id || '',
      charge_period: plan.charge_period || 'month',
      cycle: plan.cycle || 1,
      message: plan.message || '',
      pix_message: plan.pix_message || '',
      active: plan.active !== undefined ? plan.active : true,
    });
    setShowModal(true);
  };

  const handleDelete = async (planId) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja remover este plano de pagamento?',
      type: 'warning',
    });

    if (!confirmed) return;

    setLoading(true);
    setError('');
    setSuccess('');

    try {
      await paymentPlanService.deletePaymentPlan(planId, botId);
      setSuccess('Plano de pagamento removido com sucesso!');
      setTimeout(() => setSuccess(''), 3000);
      loadData();
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao remover plano de pagamento');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!botId) {
      setError('Bot não selecionado');
      return;
    }

    setError('');
    setSuccess('');
    setLoading(true);

    try {
      const planData = {
        ...formData,
        bot_id: parseInt(botId),
        price: parseFloat(formData.price),
        cycle: parseInt(formData.cycle),
      };

      if (editingPlan) {
        await paymentPlanService.updatePaymentPlan(editingPlan.id, planData);
        setSuccess('Plano de pagamento atualizado com sucesso!');
      } else {
        await paymentPlanService.createPaymentPlan(planData);
        setSuccess('Plano de pagamento criado com sucesso!');
      }

      setTimeout(() => setSuccess(''), 3000);
      setShowModal(false);
      loadData();
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.errors || 'Erro ao salvar plano de pagamento');
    } finally {
      setLoading(false);
    }
  };

  const calculateEarnings = () => {
    // Calcular ganhos do mês atual (implementação simplificada)
    // TODO: Implementar cálculo real baseado em transações
    return 0;
  };

  const formatPrice = (price) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(price);
  };

  const getChargePeriodLabel = (period) => {
    const labels = {
      day: 'Dia',
      week: 'Semana',
      month: 'Mês',
      year: 'Ano',
    };
    return labels[period] || period;
  };

  const content = (
    <>
      <DialogComponent />
      <div className="payment-plans-page">
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
            <div className="payment-plans-content">
              <div className="payment-plans-header">
                
                <div className="header-actions">
                  <button onClick={loadData} className="btn btn-primary radius-8 px-14 py-6 text-sm" disabled={loading}>
                    Atualizar
                  </button>
                  <button onClick={handleCreate} className="btn btn-primary radius-8 px-14 py-6 text-sm">
                    Criar Plano
                  </button>
                  <button onClick={() => {/* TODO: Implementar edição de mensagem PIX */}} className="btn btn-info radius-8 px-30 py-8 text-sm">
                    Mensagem Pix
                  </button>
                </div>
              </div>

              {error && <div className="alert alert-error">{error}</div>}
              {success && <div className="alert alert-success">{success}</div>}

              {/* Earnings Card */}
              <div className="earnings-card">
                <div className="earnings-icon">
                  <span>R$</span>
                </div>
                <div className="earnings-info">
                  <div className="earnings-amount">{formatPrice(calculateEarnings())}</div>
                  <div className="earnings-label">Ganhos com planos de pagamentos criados esse mês</div>
                </div>
              </div>


              {/* Payment Plans List */}
              <div className="payment-plans-section">
                <h2 className="section-title">Planos de pagamento ativos</h2>
                {paymentPlans.length === 0 ? (
                  <div className="empty-state">
                    <p>Nenhum plano de pagamento cadastrado ainda.</p>
                    <p>Clique em "Criar Plano" para começar.</p>
                  </div>
                ) : (
                  <div className="table-wrapper">
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
                            <td>{formatPrice(plan.price)}</td>
                            <td>{getChargePeriodLabel(plan.charge_period)}</td>
                            <td>{plan.cycle}</td>
                            <td className="message-cell">{plan.message || '-'}</td>
                            <td>
                              <div className="action-buttons">
                                <button
                                  onClick={() => handleEdit(plan)}
                                  className="btn-icon btn-edit"
                                  title="Editar"
                                >
                                  <FontAwesomeIcon icon={faEdit} />
                                </button>
                                <button
                                  onClick={() => handleDelete(plan.id)}
                                  className="btn-icon btn-delete"
                                  title="Remover"
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
                    <h2>{editingPlan ? 'Editar Plano' : 'Criar Novo Plano'}</h2>
                    <button className="modal-close" onClick={() => setShowModal(false)}>
                      ×
                    </button>
                  </div>
                  <form className="modal-form" onSubmit={handleSubmit}>
                    <div className="form-group">
                      <label>Título *</label>
                      <input
                        type="text"
                        name="title"
                        value={formData.title}
                        onChange={handleChange}
                        required
                        placeholder="Ex: Plano Mensal"
                      />
                    </div>

                    <div className="form-row">
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

                      <div className="form-group">
                        <label>Ciclo de Pagamento *</label>
                        <select
                          name="payment_cycle_id"
                          value={formData.payment_cycle_id}
                          onChange={handleChange}
                          required
                        >
                          <option value="">Selecione...</option>
                          {paymentCycles.map((cycle) => (
                            <option key={cycle.id} value={cycle.id}>
                              {cycle.name}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>

                    <div className="form-row">
                      <div className="form-group">
                        <label>Período de Cobrança *</label>
                        <select
                          name="charge_period"
                          value={formData.charge_period}
                          onChange={handleChange}
                          required
                        >
                          <option value="day">Dia</option>
                          <option value="week">Semana</option>
                          <option value="month">Mês</option>
                          <option value="year">Ano</option>
                        </select>
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
                      </div>
                    </div>

                    <div className="form-group">
                      <label>Mensagem</label>
                      <textarea
                        name="message"
                        value={formData.message}
                        onChange={handleChange}
                        rows="3"
                        placeholder="Mensagem que será exibida ao usuário"
                      />
                    </div>

                    <div className="form-group">
                      <label>Mensagem PIX</label>
                      <textarea
                        name="pix_message"
                        value={formData.pix_message}
                        onChange={handleChange}
                        rows="3"
                        placeholder="Mensagem específica para pagamento via PIX"
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
                        Plano ativo
                      </label>
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
                        {loading ? 'Salvando...' : editingPlan ? 'Atualizar' : 'Criar'}
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

export default PaymentPlans;
