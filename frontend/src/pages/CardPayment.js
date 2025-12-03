import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../services/api';
import './CardPayment.css';

const CardPayment = () => {
  const { token } = useParams();
  const navigate = useNavigate();
  
  const [transaction, setTransaction] = useState(null);
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  
  const [formData, setFormData] = useState({
    card_number: '',
    card_name: '',
    card_expiry: '',
    card_cvv: ''
  });

  useEffect(() => {
    if (token) {
      loadTransaction();
    }
  }, [token]);

  const loadTransaction = async () => {
    try {
      setLoading(true);
      setError('');
      
      const response = await api.get(`/payment/transaction/${token}`);
      
      if (response.data.success) {
        setTransaction(response.data.transaction);
      } else {
        throw new Error(response.data.error || 'Erro ao carregar transação');
      }
    } catch (err) {
      setError(err.response?.data?.error || err.message || 'Erro ao carregar dados do pagamento');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    
    if (name === 'card_number') {
      // Formata número do cartão
      let formatted = value.replace(/\s/g, '');
      formatted = formatted.match(/.{1,4}/g)?.join(' ') || formatted;
      setFormData({ ...formData, [name]: formatted });
    } else if (name === 'card_expiry') {
      // Formata validade
      let formatted = value.replace(/\D/g, '');
      if (formatted.length >= 2) {
        formatted = formatted.substring(0, 2) + '/' + formatted.substring(2, 4);
      }
      setFormData({ ...formData, [name]: formatted });
    } else if (name === 'card_cvv') {
      // Apenas números
      setFormData({ ...formData, [name]: value.replace(/\D/g, '') });
    } else {
      setFormData({ ...formData, [name]: value });
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    setProcessing(true);
    setError('');
    setSuccess('');

    try {
      const response = await api.post('/payment/card/process', {
        token: token,
        ...formData
      });

      if (response.data.success) {
        setSuccess(response.data.message || 'Pagamento processado com sucesso!');
        
        // Redireciona após 3 segundos
        setTimeout(() => {
          window.close();
        }, 3000);
      } else {
        throw new Error(response.data.error || 'Erro ao processar pagamento');
      }
    } catch (err) {
      setError(err.response?.data?.error || err.message || 'Erro ao processar pagamento. Tente novamente.');
    } finally {
      setProcessing(false);
    }
  };

  if (loading) {
    return (
      <div className="payment-page">
        <div className="payment-container">
          <div className="loading-spinner">
            <div className="spinner"></div>
            <p>Carregando dados do pagamento...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error && !transaction) {
    return (
      <div className="payment-page">
        <div className="payment-container">
          <div className="error-message">
            <div className="error-icon">❌</div>
            <h1>Erro no Pagamento</h1>
            <p>{error}</p>
            <button onClick={() => window.close()} className="btn-back">
              Fechar
            </button>
          </div>
        </div>
      </div>
    );
  }

  if (!transaction) {
    return (
      <div className="payment-page">
        <div className="payment-container">
          <div className="error-message">
            <div className="error-icon">❌</div>
            <h1>Transação não encontrada</h1>
            <p>Link de pagamento inválido ou expirado.</p>
            <button onClick={() => window.close()} className="btn-back">
              Fechar
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="payment-page">
      <div className="payment-container">
        <div className="payment-header">
          <h1>💳 Pagamento com Cartão</h1>
          <p>Preencha os dados do seu cartão de crédito</p>
        </div>

        <div className="plan-info">
          <h2>{transaction.payment_plan?.title || 'Plano'}</h2>
          <div className="amount">R$ {parseFloat(transaction.amount || 0).toFixed(2).replace('.', ',')}</div>
          <p>Pagamento único</p>
        </div>

        {error && (
          <div className="alert alert-error">
            ❌ {error}
          </div>
        )}

        {success && (
          <div className="alert alert-success">
            ✅ {success}
          </div>
        )}

        <form onSubmit={handleSubmit} id="payment-form">
          <div className="form-group">
            <label htmlFor="card_number">Número do Cartão</label>
            <input
              type="text"
              id="card_number"
              name="card_number"
              value={formData.card_number}
              onChange={handleChange}
              placeholder="0000 0000 0000 0000"
              maxLength="19"
              required
              disabled={processing}
            />
          </div>

          <div className="form-group">
            <label htmlFor="card_name">Nome no Cartão</label>
            <input
              type="text"
              id="card_name"
              name="card_name"
              value={formData.card_name}
              onChange={handleChange}
              placeholder="NOME COMO ESTÁ NO CARTÃO"
              required
              disabled={processing}
            />
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="card_expiry">Validade</label>
              <input
                type="text"
                id="card_expiry"
                name="card_expiry"
                value={formData.card_expiry}
                onChange={handleChange}
                placeholder="MM/AA"
                maxLength="5"
                required
                disabled={processing}
              />
            </div>
            <div className="form-group">
              <label htmlFor="card_cvv">CVV</label>
              <input
                type="text"
                id="card_cvv"
                name="card_cvv"
                value={formData.card_cvv}
                onChange={handleChange}
                placeholder="123"
                maxLength="4"
                required
                disabled={processing}
              />
            </div>
          </div>

          <button 
            type="submit" 
            className="btn-submit" 
            disabled={processing}
          >
            {processing ? 'Processando...' : `Pagar R$ ${parseFloat(transaction.amount || 0).toFixed(2).replace('.', ',')}`}
          </button>
        </form>

        <div className="security-info">
          🔒 Seus dados estão seguros e criptografados
        </div>
      </div>
    </div>
  );
};

export default CardPayment;

