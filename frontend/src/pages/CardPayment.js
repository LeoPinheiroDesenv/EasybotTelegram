import React, { useState, useEffect, useRef } from 'react';
import { useParams } from 'react-router-dom';
import api from '../services/api';
import './CardPayment.css';

const CardPayment = () => {
  const { token } = useParams();
  const [transaction, setTransaction] = useState(null);
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [stripe, setStripe] = useState(null);
  const [clientSecret, setClientSecret] = useState(null);
  // eslint-disable-next-line no-unused-vars
  const [paymentIntentId, setPaymentIntentId] = useState(null);
  const [stripePublicKey, setStripePublicKey] = useState(null);
  const elementsRef = useRef(null);
  const cardElementRef = useRef(null);

  useEffect(() => {
    if (token) {
      loadTransaction();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  useEffect(() => {
    // Carrega Stripe.js quando temos a public key
    if (stripePublicKey && window.Stripe) {
      const stripeInstance = window.Stripe(stripePublicKey);
      setStripe(stripeInstance);
    }
  }, [stripePublicKey]);

  useEffect(() => {
    // Cria Stripe Elements quando temos o client_secret
    if (stripe && clientSecret && !elementsRef.current) {
      const elements = stripe.elements();
      elementsRef.current = elements;

      const cardElement = elements.create('card', {
        style: {
          base: {
            fontSize: '16px',
            color: '#424770',
            '::placeholder': {
              color: '#aab7c4',
            },
          },
          invalid: {
            color: '#9e2146',
          },
        },
      });

      cardElement.mount('#card-element');
      cardElementRef.current = cardElement;

      cardElement.on('change', ({ error }) => {
        if (error) {
          setError(error.message);
        } else {
          setError('');
        }
      });
    }

    return () => {
      if (cardElementRef.current) {
        cardElementRef.current.unmount();
      }
    };
  }, [stripe, clientSecret]);

  const loadTransaction = async () => {
    try {
      setLoading(true);
      setError('');
      
      const response = await api.get(`/payment/transaction/${token}`);
      
      if (response.data.success) {
        setTransaction(response.data.transaction);
        // Cria PaymentIntent e obtém public key
        await createPaymentIntent();
      } else {
        throw new Error(response.data.error || 'Erro ao carregar transação');
      }
    } catch (err) {
      setError(err.response?.data?.error || err.message || 'Erro ao carregar dados do pagamento');
    } finally {
      setLoading(false);
    }
  };

  const createPaymentIntent = async () => {
    try {
      // Primeiro, busca a public key do Stripe passando o token
      try {
        const configResponse = await api.get(`/payment/stripe-config?token=${token}`);
        if (configResponse.data.success && configResponse.data.public_key) {
          setStripePublicKey(configResponse.data.public_key);
        } else {
          throw new Error(configResponse.data.error || 'Chave pública não retornada');
        }
      } catch (e) {
        // Tenta usar variável de ambiente como fallback
        const publicKey = process.env.REACT_APP_STRIPE_PUBLIC_KEY || '';
        if (publicKey) {
          setStripePublicKey(publicKey);
        } else {
          const errorMessage = e.response?.data?.error || e.message || 'Chave pública do Stripe não configurada.';
          setError(errorMessage);
          return;
        }
      }

      // Cria o PaymentIntent
      const response = await api.post('/payment/card/create-intent', {
        token: token
      });

      if (response.data.success) {
        setClientSecret(response.data.client_secret);
        setPaymentIntentId(response.data.payment_intent_id);
      } else {
        throw new Error(response.data.error || 'Erro ao criar PaymentIntent');
      }
    } catch (err) {
      setError(err.response?.data?.error || err.message || 'Erro ao inicializar pagamento');
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!stripe || !elementsRef.current || !cardElementRef.current) {
      setError('Stripe não está carregado. Por favor, recarregue a página.');
      return;
    }

    setProcessing(true);
    setError('');
    setSuccess('');

    try {
      if (!stripe || !cardElementRef.current) {
        setError('Stripe não está carregado. Aguarde um momento e tente novamente.');
        setProcessing(false);
        return;
      }

      // Confirma o pagamento usando Stripe.js com confirmation_method automatic
      const { error: confirmError, paymentIntent } = await stripe.confirmCardPayment(
        clientSecret,
        {
          payment_method: {
            card: cardElementRef.current,
            billing_details: {
              name: document.getElementById('card-name')?.value || 'Cliente',
            },
          },
        }
      );

      if (confirmError) {
        setError(confirmError.message || 'Erro ao processar pagamento');
        setProcessing(false);
        return;
      }

      // Verifica o status do pagamento
      if (paymentIntent.status === 'succeeded') {
        // Pagamento aprovado - confirma no backend
        try {
          const response = await api.post('/payment/card/confirm', {
            token: token,
            payment_intent_id: paymentIntent.id
          });

          if (response.data.success) {
            setSuccess(response.data.message || 'Pagamento processado com sucesso!');
            
            // Redireciona após 3 segundos
            setTimeout(() => {
              window.close();
            }, 3000);
          } else {
            throw new Error(response.data.error || 'Erro ao confirmar pagamento no servidor');
          }
        } catch (err) {
          // Mesmo que o backend falhe, o pagamento já foi processado no Stripe
          setError(err.response?.data?.error || err.message || 'Pagamento processado, mas houve erro ao atualizar status. Entre em contato com o suporte.');
        }
      } else if (paymentIntent.status === 'requires_action') {
        // Requer ação adicional (3D Secure) - Stripe.js já deve ter tratado isso
        // Mas vamos tentar confirmar novamente se necessário
        setError('Pagamento requer autenticação adicional. Por favor, complete a autenticação.');
      } else if (paymentIntent.status === 'requires_payment_method') {
        setError('Método de pagamento inválido. Por favor, verifique os dados do cartão.');
      } else if (paymentIntent.status === 'canceled') {
        setError('Pagamento cancelado.');
      } else {
        setError(`Status do pagamento: ${paymentIntent.status}. Entre em contato com o suporte.`);
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

        {!stripePublicKey && (
          <div className="alert alert-error">
            ⚠️ Configuração do Stripe não encontrada. Entre em contato com o suporte.
          </div>
        )}

        <form onSubmit={handleSubmit} id="payment-form">
          <div className="form-group">
            <label htmlFor="card-name">Nome no Cartão</label>
            <input
              type="text"
              id="card-name"
              name="card-name"
              placeholder="NOME COMO ESTÁ NO CARTÃO"
              required
              disabled={processing || !stripe}
            />
          </div>

          <div className="form-group">
            <label htmlFor="card-element">Dados do Cartão</label>
            <div id="card-element" style={{ padding: '10px', border: '1px solid #ddd', borderRadius: '4px' }}>
              {/* Stripe Elements será montado aqui */}
            </div>
            <div id="card-errors" role="alert" style={{ color: '#dc3545', marginTop: '8px' }}></div>
          </div>

          <button 
            type="submit" 
            className="btn-submit" 
            disabled={processing || !stripe || !clientSecret}
          >
            {processing ? 'Processando...' : `Pagar R$ ${parseFloat(transaction.amount || 0).toFixed(2).replace('.', ',')}`}
          </button>
        </form>

        <div className="security-info">
          🔒 Seus dados estão seguros e criptografados pelo Stripe
        </div>
      </div>
    </div>
  );
};

export default CardPayment;
