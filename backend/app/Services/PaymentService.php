<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Contact;
use App\Models\PaymentPlan;
use App\Models\Transaction;
use App\Models\PaymentGatewayConfig;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    /**
     * Gera QR Code PIX para um pagamento usando Mercado Pago
     *
     * @param Bot $bot
     * @param PaymentPlan $plan
     * @param Contact $contact
     * @return array
     */
    public function generatePixQrCode(Bot $bot, PaymentPlan $plan, Contact $contact): array
    {
        try {
            // Busca configuração do gateway Mercado Pago
            $gatewayConfig = PaymentGatewayConfig::where('bot_id', $bot->id)
                ->where('gateway', 'mercadopago')
                ->where('active', true)
                ->where('environment', 'production')
                ->first();

            // Se não tiver em produção, busca em teste
            if (!$gatewayConfig) {
                $gatewayConfig = PaymentGatewayConfig::where('bot_id', $bot->id)
                    ->where('gateway', 'mercadopago')
                    ->where('active', true)
                    ->where('environment', 'test')
                    ->first();
            }

            if (!$gatewayConfig || !$gatewayConfig->api_key) {
                throw new Exception('Configuração do Mercado Pago não encontrada ou access token não configurado. Configure o gateway de pagamento nas configurações.');
            }

            // Configura o SDK do Mercado Pago
            $accessToken = $gatewayConfig->api_key;
            MercadoPagoConfig::setAccessToken($accessToken);

            // Gera código de transação único
            $transactionId = 'PIX_' . time() . '_' . $bot->id . '_' . $plan->id . '_' . $contact->id;
            
            // Cria transação pendente
            $transaction = Transaction::create([
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'payment_plan_id' => $plan->id,
                'payment_cycle_id' => $plan->payment_cycle_id,
                'gateway' => 'mercadopago',
                'gateway_transaction_id' => $transactionId,
                'amount' => $plan->price,
                'currency' => 'BRL',
                'status' => 'pending',
                'payment_method' => 'pix',
                'metadata' => [
                    'plan_title' => $plan->title,
                    'contact_name' => $contact->first_name ?? $contact->username ?? 'Cliente',
                    'contact_email' => $contact->email,
                    'contact_phone' => $contact->phone,
                    'created_at' => now()->toIso8601String(),
                    'environment' => $gatewayConfig->environment
                ]
            ]);

            // Prepara dados do pagamento PIX
            $paymentData = [
                'transaction_amount' => (float) $plan->price,
                'description' => "Pagamento - {$plan->title}",
                'payment_method_id' => 'pix',
                'payer' => [
                    'email' => $contact->email ?? 'cliente@example.com',
                    'first_name' => $contact->first_name ?? $contact->username ?? 'Cliente',
                    'last_name' => $contact->last_name ?? '',
                ]
            ];

            // Adiciona CPF se disponível
            if (!empty($contact->cpf)) {
                $paymentData['payer']['identification'] = [
                    'type' => 'CPF',
                    'number' => preg_replace('/[^0-9]/', '', $contact->cpf)
                ];
            }

            // Adiciona referência externa
            $paymentData['external_reference'] = $transactionId;

            // Adiciona URL de notificação
            $webhookUrl = $gatewayConfig->webhook_url ?? env('APP_URL') . '/api/payments/webhook/mercadopago';
            if ($webhookUrl) {
                $paymentData['notification_url'] = $webhookUrl;
            }

            // Adiciona descritor do extrato (máximo 22 caracteres)
            $statementDescriptor = substr($bot->name ?? 'EasyPagamentos', 0, 22);
            if ($statementDescriptor) {
                $paymentData['statement_descriptor'] = $statementDescriptor;
            }

            // Cria pagamento PIX no Mercado Pago
            $client = new PaymentClient();
            $payment = $client->create($paymentData);

            if (!$payment || !isset($payment->id)) {
                throw new Exception('Erro ao criar pagamento no Mercado Pago. Resposta inválida.');
            }

            // Obtém informações do PIX da resposta
            $pixData = null;
            if (isset($payment->point_of_interaction) && 
                isset($payment->point_of_interaction->transaction_data)) {
                $pixData = $payment->point_of_interaction->transaction_data;
            }

            if (!$pixData) {
                throw new Exception('Erro ao obter dados do PIX do Mercado Pago. Pagamento criado mas sem dados PIX.');
            }

            // Extrai código PIX e QR Code
            $pixCode = $pixData->qr_code ?? null;
            $pixCodeBase64 = $pixData->qr_code_base64 ?? null;
            $ticketUrl = $pixData->ticket_url ?? null;

            if (!$pixCode) {
                throw new Exception('Código PIX não retornado pelo Mercado Pago.');
            }

            // Gera QR Code como imagem usando o código PIX do Mercado Pago
            $qrCodeImage = null;
            if ($pixCodeBase64) {
                // Usa o QR Code base64 retornado pelo Mercado Pago
                $qrCodeImage = $pixCodeBase64;
            } else {
                // Gera QR Code localmente usando o código PIX
                $qrCodeImage = $this->generateQrCodeImage($pixCode);
            }

            // Salva QR Code temporariamente (opcional)
            $qrCodePath = $this->saveQrCodeImage($transactionId, $qrCodeImage);

            // Atualiza transação com informações do PIX do Mercado Pago
            $transaction->update([
                'gateway_transaction_id' => (string) $payment->id,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'mercadopago_payment_id' => $payment->id,
                    'mercadopago_status' => $payment->status ?? 'pending',
                    'pix_code' => $pixCode,
                    'pix_ticket_url' => $ticketUrl,
                    'qr_code_path' => $qrCodePath,
                    'expiration_date' => $pixData->expiration_date ?? null
                ])
            ]);

            return [
                'success' => true,
                'transaction' => $transaction,
                'pix_key' => null, // Mercado Pago não retorna chave PIX diretamente
                'pix_code' => $pixCode,
                'qr_code_image' => $qrCodeImage,
                'qr_code_path' => $qrCodePath,
                'ticket_url' => $ticketUrl,
                'payment_id' => $payment->id
            ];
        } catch (MPApiException $e) {
            $errorMessage = 'Erro na API do Mercado Pago: ';
            if ($e->getApiResponse() && isset($e->getApiResponse()->getContent()['message'])) {
                $errorMessage .= $e->getApiResponse()->getContent()['message'];
            } else {
                $errorMessage .= $e->getMessage();
            }

            Log::error('Erro ao gerar QR Code PIX via Mercado Pago', [
                'bot_id' => $bot->id,
                'plan_id' => $plan->id,
                'contact_id' => $contact->id,
                'error' => $errorMessage,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $errorMessage
            ];
        } catch (Exception $e) {
            Log::error('Erro ao gerar QR Code PIX', [
                'bot_id' => $bot->id,
                'plan_id' => $plan->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }


    /**
     * Gera imagem do QR Code a partir do código PIX
     *
     * @param string $pixCode
     * @return string Base64 da imagem
     */
    protected function generateQrCodeImage(string $pixCode): string
    {
        try {
            // Tenta gerar PNG
            $hasImagick = extension_loaded('imagick');
            $hasGd = extension_loaded('gd');

            if ($hasImagick || $hasGd) {
                try {
                    $qrCodeData = QrCode::format('png')
                        ->size(300)
                        ->margin(2)
                        ->errorCorrection('H')
                        ->generate($pixCode);
                    
                    return base64_encode($qrCodeData);
                } catch (Exception $e) {
                    // Fallback para SVG
                }
            }

            // Fallback: SVG
            $qrCodeData = QrCode::format('svg')
                ->size(300)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($pixCode);
            
            return base64_encode($qrCodeData);
        } catch (Exception $e) {
            throw new Exception('Erro ao gerar imagem do QR Code: ' . $e->getMessage());
        }
    }

    /**
     * Salva imagem do QR Code temporariamente
     *
     * @param string $transactionId
     * @param string $qrCodeImage Base64
     * @return string|null
     */
    protected function saveQrCodeImage(string $transactionId, string $qrCodeImage): ?string
    {
        try {
            // Cria diretório se não existir
            $directory = 'qrcodes';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $filename = $directory . '/pix_' . $transactionId . '.png';
            Storage::disk('public')->put($filename, base64_decode($qrCodeImage));
            return $filename;
        } catch (Exception $e) {
            Log::warning('Erro ao salvar QR Code', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Gera link de pagamento para cartão de crédito
     *
     * @param Bot $bot
     * @param PaymentPlan $plan
     * @param Contact $contact
     * @return array
     */
    public function generateCardPaymentLink(Bot $bot, PaymentPlan $plan, Contact $contact): array
    {
        try {
            // Busca configuração do gateway de pagamento (prioriza Stripe para cartão)
            $gatewayConfig = PaymentGatewayConfig::where('bot_id', $bot->id)
                ->where('gateway', 'stripe')
                ->where('active', true)
                ->where('environment', 'production')
                ->first();

            // Se não tiver em produção, busca em teste
            if (!$gatewayConfig) {
                $gatewayConfig = PaymentGatewayConfig::where('bot_id', $bot->id)
                    ->where('gateway', 'stripe')
                    ->where('active', true)
                    ->where('environment', 'test')
                    ->first();
            }

            // Se não tiver Stripe, tenta Mercado Pago
            if (!$gatewayConfig) {
                $gatewayConfig = PaymentGatewayConfig::where('bot_id', $bot->id)
                    ->where('gateway', 'mercadopago')
                    ->where('active', true)
                    ->where('environment', 'production')
                    ->first();
            }

            if (!$gatewayConfig) {
                $gatewayConfig = PaymentGatewayConfig::where('bot_id', $bot->id)
                    ->where('gateway', 'mercadopago')
                    ->where('active', true)
                    ->where('environment', 'test')
                    ->first();
            }

            if (!$gatewayConfig) {
                throw new Exception('Nenhum gateway de pagamento configurado para cartão de crédito. Configure o Stripe ou Mercado Pago nas configurações.');
            }

            // Gera código de transação único
            $transactionId = 'CARD_' . time() . '_' . $bot->id . '_' . $plan->id . '_' . $contact->id;
            
            // Cria transação pendente
            $transaction = Transaction::create([
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'payment_plan_id' => $plan->id,
                'payment_cycle_id' => $plan->payment_cycle_id,
                'gateway' => $gatewayConfig->gateway,
                'gateway_transaction_id' => $transactionId,
                'amount' => $plan->price,
                'currency' => 'BRL',
                'status' => 'pending',
                'payment_method' => 'card',
                'metadata' => [
                    'plan_title' => $plan->title,
                    'contact_name' => $contact->first_name ?? $contact->username ?? 'Cliente',
                    'contact_email' => $contact->email,
                    'contact_phone' => $contact->phone,
                    'created_at' => now()->toIso8601String()
                ]
            ]);

            // Gera token único para o link de pagamento
            $paymentToken = bin2hex(random_bytes(32));
            
            // Salva token na transação
            $transaction->update([
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'payment_token' => $paymentToken,
                    'expires_at' => now()->addHours(24)->toIso8601String()
                ])
            ]);

            // Gera URL do link de pagamento
            $baseUrl = env('APP_URL', 'http://localhost');
            $paymentUrl = "{$baseUrl}/payment/card/{$paymentToken}";

            return [
                'success' => true,
                'transaction' => $transaction,
                'payment_url' => $paymentUrl,
                'payment_token' => $paymentToken
            ];
        } catch (Exception $e) {
            Log::error('Erro ao gerar link de pagamento com cartão', [
                'bot_id' => $bot->id,
                'plan_id' => $plan->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cria um PaymentIntent no Stripe (sem confirmar)
     * O frontend usará Stripe.js para confirmar o pagamento
     *
     * @param Transaction $transaction
     * @return array
     */
    public function createStripePaymentIntent(Transaction $transaction): array
    {
        try {
            // Busca configuração do gateway Stripe
            $gatewayConfig = PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                ->where('gateway', 'stripe')
                ->where('active', true)
                ->where('environment', 'production')
                ->first();

            // Se não tiver em produção, busca em teste
            if (!$gatewayConfig) {
                $gatewayConfig = PaymentGatewayConfig::where('bot_id', $transaction->bot_id)
                    ->where('gateway', 'stripe')
                    ->where('active', true)
                    ->where('environment', 'test')
                    ->first();
            }

            if (!$gatewayConfig) {
                throw new Exception('Configuração do Stripe não encontrada. Configure o gateway de pagamento nas configurações.');
            }

            // Extrai secret key do Stripe
            $stripeData = json_decode($gatewayConfig->api_secret, true);
            $secretKey = $stripeData['secret_key'] ?? $gatewayConfig->api_secret ?? null;

            if (!$secretKey) {
                throw new Exception('Chave secreta do Stripe não configurada.');
            }

            // Configura o Stripe
            Stripe::setApiKey($secretKey);

            // Prepara descrição do pagamento
            $metadata = is_string($transaction->metadata) 
                ? json_decode($transaction->metadata, true) 
                : ($transaction->metadata ?? []);
            $planTitle = $metadata['plan_title'] ?? 'Plano';

            // Cria PaymentIntent no Stripe (será confirmado no frontend usando Stripe.js)
            $paymentIntentData = [
                'amount' => (int) ($transaction->amount * 100), // Stripe usa centavos
                'currency' => strtolower($transaction->currency ?? 'brl'),
                'confirmation_method' => 'automatic', // Permite confirmação no frontend com chave pública
                'confirm' => false, // Não confirma aqui - frontend fará isso usando Stripe.js
                'description' => "Pagamento - {$planTitle}",
                'payment_method_types' => ['card'],
                'metadata' => [
                    'transaction_id' => (string) $transaction->id,
                    'bot_id' => (string) $transaction->bot_id,
                    'contact_id' => (string) $transaction->contact_id,
                    'plan_id' => (string) $transaction->payment_plan_id,
                ],
            ];

            // Cria o PaymentIntent
            $paymentIntent = PaymentIntent::create($paymentIntentData);

            // Atualiza transação com PaymentIntent ID
            $transactionMetadata = $transaction->metadata ?? [];
            $transactionMetadata['stripe_payment_intent_id'] = $paymentIntent->id;
            $transactionMetadata['stripe_status'] = 'requires_payment_method';

            $transaction->update([
                'gateway_transaction_id' => $paymentIntent->id,
                'status' => 'pending',
                'metadata' => $transactionMetadata
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'transaction_id' => $transaction->id
            ];

        } catch (ApiErrorException $e) {
            $errorMessage = 'Erro na API do Stripe: ';
            if ($e->getStripeCode()) {
                $errorMessage .= "[{$e->getStripeCode()}] ";
            }
            $errorMessage .= $e->getMessage();

            Log::error('Erro ao processar pagamento Stripe', [
                'transaction_id' => $transaction->id,
                'error' => $errorMessage,
                'stripe_code' => $e->getStripeCode(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $errorMessage
            ];
        } catch (Exception $e) {
            Log::error('Erro ao processar pagamento com cartão', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

