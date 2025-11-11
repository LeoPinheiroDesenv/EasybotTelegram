import React, { useState, useEffect, useContext } from 'react';
import Layout from '../components/Layout';
import paymentCycleService from '../services/paymentCycleService';
import { AuthContext } from '../contexts/AuthContext';
import './PaymentCycles.css';

const PaymentCycles = () => {
  const { isAdmin } = useContext(AuthContext);
  const [cycles, setCycles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editingCycle, setEditingCycle] = useState(null);
  const [formData, setFormData] = useState({
    name: '',
    days: 0,
    description: '',
    is_active: true
  });

  useEffect(() => {
    loadCycles();
  }, []);

  const loadCycles = async () => {
    try {
      setLoading(true);
      setError('');
      const data = await paymentCycleService.getAllCycles();
      setCycles(data);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar ciclos de pagamento');
    } finally {
      setLoading(false);
    }
  };

  const handleOpenModal = (cycle = null) => {
    if (cycle) {
      setEditingCycle(cycle);
      setFormData({
        name: cycle.name,
        days: cycle.days,
        description: cycle.description || '',
        is_active: cycle.is_active
      });
    } else {
      setEditingCycle(null);
      setFormData({
        name: '',
        days: 0,
        description: '',
        is_active: true
      });
    }
    setShowModal(true);
    setError('');
    setSuccess('');
  };

  const handleCloseModal = () => {
    setShowModal(false);
    setEditingCycle(null);
    setFormData({
      name: '',
      days: 0,
      description: '',
      is_active: true
    });
    setError('');
    setSuccess('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    if (!formData.name || formData.days === undefined) {
      setError('Nome e dias são obrigatórios');
      return;
    }

    try {
      if (editingCycle) {
        await paymentCycleService.updateCycle(editingCycle.id, formData);
        setSuccess('Ciclo de pagamento atualizado com sucesso!');
      } else {
        await paymentCycleService.createCycle(formData);
        setSuccess('Ciclo de pagamento criado com sucesso!');
      }
      handleCloseModal();
      loadCycles();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar ciclo de pagamento');
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Tem certeza que deseja excluir este ciclo de pagamento?')) {
      return;
    }

    try {
      await paymentCycleService.deleteCycle(id);
      setSuccess('Ciclo de pagamento excluído com sucesso!');
      loadCycles();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao excluir ciclo de pagamento');
    }
  };

  if (loading) {
    return (
      <Layout>
        <div className="payment-cycles-page">
          <div className="loading-container">Carregando...</div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="payment-cycles-page">
        <div className="page-header">
          <h1 className="page-title">Ciclos de Pagamento</h1>
          {isAdmin && (
            <button className="btn btn-primary" onClick={() => handleOpenModal()}>
              + Novo Ciclo
            </button>
          )}
        </div>

        {error && <div className="alert alert-error">{error}</div>}
        {success && <div className="alert alert-success">{success}</div>}

        <div className="cycles-table-container">
          <table className="cycles-table">
            <thead>
              <tr>
                <th>Nome</th>
                <th>Dias</th>
                <th>Descrição</th>
                <th>Status</th>
                {isAdmin && <th>Ações</th>}
              </tr>
            </thead>
            <tbody>
              {cycles.length === 0 ? (
                <tr>
                  <td colSpan={isAdmin ? 5 : 4} className="empty-state">
                    Nenhum ciclo de pagamento encontrado
                  </td>
                </tr>
              ) : (
                cycles.map((cycle) => (
                  <tr key={cycle.id}>
                    <td>{cycle.name}</td>
                    <td>{cycle.days === 0 ? 'Vitalício' : `${cycle.days} dias`}</td>
                    <td>{cycle.description || '-'}</td>
                    <td>
                      <span className={`status-badge ${cycle.is_active ? 'active' : 'inactive'}`}>
                        {cycle.is_active ? 'Ativo' : 'Inativo'}
                      </span>
                    </td>
                    {isAdmin && (
                      <td>
                        <div className="action-buttons">
                          <button
                            className="btn-icon btn-edit"
                            onClick={() => handleOpenModal(cycle)}
                            title="Editar"
                          >
                            ✏️
                          </button>
                          <button
                            className="btn-icon btn-delete"
                            onClick={() => handleDelete(cycle.id)}
                            title="Excluir"
                          >
                            🗑️
                          </button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {showModal && (
          <div className="modal-overlay" onClick={handleCloseModal}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>{editingCycle ? 'Editar Ciclo' : 'Novo Ciclo de Pagamento'}</h2>
                <button className="modal-close" onClick={handleCloseModal}>×</button>
              </div>
              <form onSubmit={handleSubmit}>
                <div className="form-group">
                  <label htmlFor="name">Nome *</label>
                  <input
                    type="text"
                    id="name"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    required
                    placeholder="Ex: Mensal"
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="days">Dias *</label>
                  <input
                    type="number"
                    id="days"
                    value={formData.days}
                    onChange={(e) => setFormData({ ...formData, days: parseInt(e.target.value) || 0 })}
                    required
                    min="0"
                    placeholder="0 para vitalício"
                  />
                  <small>Use 0 para ciclo vitalício</small>
                </div>
                <div className="form-group">
                  <label htmlFor="description">Descrição</label>
                  <textarea
                    id="description"
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    rows="3"
                    placeholder="Descrição do ciclo de pagamento"
                  />
                </div>
                <div className="form-group">
                  <label className="checkbox-label">
                    <input
                      type="checkbox"
                      checked={formData.is_active}
                      onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                    />
                    <span>Ativo</span>
                  </label>
                </div>
                {error && <div className="alert alert-error">{error}</div>}
                <div className="modal-actions">
                  <button type="button" className="btn btn-secondary" onClick={handleCloseModal}>
                    Cancelar
                  </button>
                  <button type="submit" className="btn btn-primary">
                    {editingCycle ? 'Atualizar' : 'Criar'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
};

export default PaymentCycles;

