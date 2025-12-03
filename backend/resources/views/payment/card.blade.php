<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pagamento - {{ $transaction->paymentPlan->title ?? 'Plano' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .payment-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }

        .payment-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .payment-header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .payment-header p {
            color: #666;
            font-size: 14px;
        }

        .plan-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .plan-info h2 {
            color: #333;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .plan-info .amount {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.active {
            display: block;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .security-info {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h1>💳 Pagamento com Cartão</h1>
            <p>Preencha os dados do seu cartão de crédito</p>
        </div>

        <div class="plan-info">
            <h2>{{ $transaction->paymentPlan->title ?? 'Plano' }}</h2>
            <div class="amount">R$ {{ number_format($transaction->amount, 2, ',', '.') }}</div>
            <p style="color: #666; font-size: 14px;">Pagamento único</p>
        </div>

        <div id="alert-container"></div>

        <form id="payment-form">
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="card_number">Número do Cartão</label>
                <input 
                    type="text" 
                    id="card_number" 
                    name="card_number" 
                    placeholder="0000 0000 0000 0000"
                    maxlength="19"
                    required
                >
            </div>

            <div class="form-group">
                <label for="card_name">Nome no Cartão</label>
                <input 
                    type="text" 
                    id="card_name" 
                    name="card_name" 
                    placeholder="NOME COMO ESTÁ NO CARTÃO"
                    required
                >
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="card_expiry">Validade</label>
                    <input 
                        type="text" 
                        id="card_expiry" 
                        name="card_expiry" 
                        placeholder="MM/AA"
                        maxlength="5"
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="card_cvv">CVV</label>
                    <input 
                        type="text" 
                        id="card_cvv" 
                        name="card_cvv" 
                        placeholder="123"
                        maxlength="4"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submit-btn">
                Pagar R$ {{ number_format($transaction->amount, 2, ',', '.') }}
            </button>
        </form>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p style="margin-top: 15px; color: #666;">Processando pagamento...</p>
        </div>

        <div class="security-info">
            🔒 Seus dados estão seguros e criptografados
        </div>
    </div>

    <script>
        // Formata número do cartão
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formatted;
        });

        // Formata validade
        document.getElementById('card_expiry').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // Apenas números no CVV
        document.getElementById('card_cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });

        // Submit do formulário
        document.getElementById('payment-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const form = e.target;
            const submitBtn = document.getElementById('submit-btn');
            const loading = document.getElementById('loading');
            const alertContainer = document.getElementById('alert-container');

            // Limpa alertas anteriores
            alertContainer.innerHTML = '';

            // Desabilita botão e mostra loading
            submitBtn.disabled = true;
            loading.classList.add('active');

            try {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData);

                // Obtém token CSRF
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

                const response = await fetch('/payment/card/process', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            ✅ ${result.message || 'Pagamento processado com sucesso!'}
                        </div>
                    `;
                    form.reset();
                    
                    // Redireciona após 3 segundos
                    setTimeout(() => {
                        window.close();
                    }, 3000);
                } else {
                    alertContainer.innerHTML = `
                        <div class="alert alert-error">
                            ❌ ${result.error || 'Erro ao processar pagamento'}
                        </div>
                    `;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                alertContainer.innerHTML = `
                    <div class="alert alert-error">
                        ❌ Erro ao processar pagamento. Tente novamente.
                    </div>
                `;
                submitBtn.disabled = false;
            } finally {
                loading.classList.remove('active');
            }
        });
    </script>
</body>
</html>

