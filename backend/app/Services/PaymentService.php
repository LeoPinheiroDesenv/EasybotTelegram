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
use App\Services\PixCrcService;

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
            // Se webhook_url não estiver configurado no banco, usa a URL base da aplicação
            $webhookUrl = $gatewayConfig->webhook_url;
            if (!$webhookUrl) {
                $webhookUrl = config('app.url') . '/api/payments/webhook/mercadopago';
            }
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
                Log::error('Dados PIX não encontrados na resposta do Mercado Pago', [
                    'payment_id' => $payment->id ?? null,
                    'payment_status' => $payment->status ?? null,
                    'has_point_of_interaction' => isset($payment->point_of_interaction),
                    'point_of_interaction_keys' => isset($payment->point_of_interaction) ? array_keys((array)$payment->point_of_interaction) : []
                ]);
                throw new Exception('Erro ao obter dados do PIX do Mercado Pago. Pagamento criado mas sem dados PIX.');
            }

            // Extrai código PIX e QR Code
            // O Mercado Pago pode retornar o código em diferentes formatos:
            // - qr_code: código PIX em texto (formato EMV)
            // - qr_code_base64: código PIX codificado em base64 (precisa decodificar)
            $pixCode = null;
            if (isset($pixData->qr_code)) {
                $pixCode = $pixData->qr_code;
            } elseif (isset($pixData->qr_code_base64)) {
                // Decodifica base64 para obter o código PIX em texto
                $decoded = base64_decode($pixData->qr_code_base64, true);
                if ($decoded !== false) {
                    $pixCode = $decoded;
                } else {
                    Log::warning('Falha ao decodificar qr_code_base64 do Mercado Pago', [
                        'payment_id' => $payment->id ?? null
                    ]);
                }
            }
            
            $pixCodeBase64 = $pixData->qr_code_base64 ?? null;
            $ticketUrl = $pixData->ticket_url ?? null;

            // Log da estrutura recebida para debug
            Log::debug('Dados PIX recebidos do Mercado Pago', [
                'has_qr_code' => isset($pixData->qr_code),
                'has_qr_code_base64' => isset($pixData->qr_code_base64),
                'has_ticket_url' => isset($pixData->ticket_url),
                'transaction_data_keys' => array_keys((array)$pixData),
                'qr_code_length' => isset($pixData->qr_code) ? strlen($pixData->qr_code) : null
            ]);

            if (!$pixCode) {
                Log::error('Código PIX não encontrado na resposta do Mercado Pago', [
                    'payment_id' => $payment->id ?? null,
                    'transaction_data' => (array)$pixData
                ]);
                throw new Exception('Código PIX não retornado pelo Mercado Pago.');
            }

            // IMPORTANTE: O código PIX do Mercado Pago já vem com CRC calculado
            // Devemos validar o CRC ANTES de normalizar, pois o CRC foi calculado sobre o código original
            $pixCrcService = new PixCrcService();
            $pixCodeOriginal = $pixCode;
            
            // Primeiro, tenta validar o código PIX original (sem normalizar)
            // Remove apenas espaços em branco para validação, mas mantém o código original
            $pixCodeForValidation = trim($pixCode);
            $pixCodeForValidation = preg_replace('/\s+/', '', $pixCodeForValidation);
            
            // Valida formato básico
            if (!str_starts_with($pixCodeForValidation, '000201')) {
                Log::error('Código PIX em formato incorreto - não começa com 000201', [
                    'pix_code_start' => substr($pixCodeForValidation, 0, 50),
                    'pix_code_length' => strlen($pixCodeForValidation),
                    'pix_code_original_start' => substr($pixCodeOriginal, 0, 50)
                ]);
                throw new Exception('Código PIX retornado pelo Mercado Pago está em formato inválido.');
            }
            
            // Valida comprimento mínimo
            if (strlen($pixCodeForValidation) < 100) {
                Log::error('Código PIX muito curto', [
                    'pix_code_length' => strlen($pixCodeForValidation),
                    'pix_code_start' => substr($pixCodeForValidation, 0, 50)
                ]);
                throw new Exception('Código PIX retornado pelo Mercado Pago está incompleto.');
            }
            
            // Valida o CRC do código original (normalizado apenas removendo espaços)
            $crcValidation = $pixCrcService->validatePixCode($pixCodeForValidation);
            
            if ($crcValidation['valid']) {
                // CRC válido - usa o código normalizado (sem espaços, mas mantendo estrutura original)
                $pixCode = $pixCodeForValidation;
                Log::info('Código PIX do Mercado Pago com CRC válido', [
                    'crc' => $crcValidation['current_crc'],
                    'pix_code_length' => strlen($pixCode)
                ]);
            } else {
                // CRC inválido - tenta normalizar mais e recalcular
                Log::warning('Código PIX com CRC inválido, tentando normalizar e recalcular...', [
                    'errors' => $crcValidation['errors'],
                    'current_crc' => $crcValidation['current_crc'],
                    'calculated_crc' => $crcValidation['calculated_crc'],
                    'pix_code_length' => strlen($pixCodeForValidation),
                    'pix_code_end' => substr($pixCodeForValidation, -10)
                ]);
                
                // Normaliza mais agressivamente: remove caracteres de controle
                $pixCodeNormalized = $pixCodeForValidation;
                $pixCodeNormalized = preg_replace('/[\x00-\x1F\x7F]/', '', $pixCodeNormalized);
                
                // Recalcula o CRC
                $pixCode = $pixCrcService->addCrc($pixCodeNormalized);
                
                // Valida novamente após correção
                $crcValidation = $pixCrcService->validatePixCode($pixCode);
                if (!$crcValidation['valid']) {
                    Log::error('Falha ao corrigir CRC do código PIX após normalização', [
                        'errors' => $crcValidation['errors'],
                        'pix_code_length' => strlen($pixCode),
                        'calculated_crc' => $crcValidation['calculated_crc'],
                        'current_crc' => $crcValidation['current_crc']
                    ]);
                    throw new Exception('Erro ao validar/corrigir CRC do código PIX: ' . implode(', ', $crcValidation['errors']));
                }
                
                Log::info('CRC do código PIX corrigido após normalização', [
                    'old_crc' => $crcValidation['current_crc'] ?? 'N/A',
                    'new_crc' => $crcValidation['calculated_crc'],
                    'pix_code_length' => strlen($pixCode)
                ]);
            }
            
            // Garante que o código está limpo (sem espaços ou caracteres de controle) antes de gerar QR Code
            $pixCode = trim($pixCode);
            $pixCode = preg_replace('/\s+/', '', $pixCode);
            $pixCode = preg_replace('/[\x00-\x1F\x7F]/', '', $pixCode);
            
            // Validação final antes de gerar QR Code
            $finalValidation = $pixCrcService->validatePixCode($pixCode);
            if (!$finalValidation['valid']) {
                Log::error('Código PIX inválido após normalização final', [
                    'errors' => $finalValidation['errors'],
                    'pix_code_length' => strlen($pixCode)
                ]);
                throw new Exception('Código PIX inválido após processamento: ' . implode(', ', $finalValidation['errors']));
            }
            
            Log::info('Código PIX extraído, normalizado e validado com sucesso', [
                'pix_code_length' => strlen($pixCode),
                'pix_code_start' => substr($pixCode, 0, 20),
                'pix_code_end' => substr($pixCode, -20),
                'crc' => substr($pixCode, -4),
                'is_valid_format' => str_starts_with($pixCode, '000201'),
                'crc_valid' => $finalValidation['crc_valid']
            ]);

            // Gera QR Code como imagem
            // Prioriza usar o QR Code base64 do Mercado Pago se disponível e válido
            // Caso contrário, gera localmente usando o código PIX validado
            $qrCodeImage = null;
            
            if (!empty($pixCodeBase64)) {
                // Tenta usar o QR Code base64 do Mercado Pago
                // Mas valida se o código PIX dentro dele corresponde ao nosso código validado
                try {
                    $decodedBase64 = base64_decode($pixCodeBase64, true);
                    if ($decodedBase64 !== false && strlen($decodedBase64) > 100) {
                        // Verifica se é uma imagem válida (PNG ou SVG)
                        $isPng = substr($decodedBase64, 0, 8) === "\x89PNG\r\n\x1a\n";
                        $isSvg = strpos($decodedBase64, '<svg') !== false || strpos($decodedBase64, '<?xml') !== false;
                        
                        if ($isPng || $isSvg) {
                            $qrCodeImage = $pixCodeBase64;
                            Log::info('Usando QR Code base64 do Mercado Pago', [
                                'format' => $isPng ? 'PNG' : 'SVG',
                                'size' => strlen($decodedBase64)
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    Log::warning('Erro ao processar QR Code base64 do Mercado Pago, gerando localmente', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Se não conseguiu usar o QR Code do Mercado Pago, gera localmente
            if (!$qrCodeImage) {
                $qrCodeImage = $this->generateQrCodeImage($pixCode);
                Log::info('QR Code gerado localmente usando código PIX validado', [
                    'pix_code_length' => strlen($pixCode),
                    'crc' => substr($pixCode, -4)
                ]);
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
            // O código PIX já deve estar normalizado e validado antes de chegar aqui
            // Apenas validações finais de segurança
            
            // Valida o código antes de gerar o QR Code
            if (!str_starts_with($pixCode, '000201')) {
                throw new Exception('Código PIX inválido para geração de QR Code: não começa com 000201');
            }
            
            if (strlen($pixCode) < 100) {
                throw new Exception('Código PIX muito curto para geração de QR Code');
            }
            
            // Validação final do CRC (deve estar válido, mas verificamos por segurança)
            $pixCrcService = new PixCrcService();
            $crcValidation = $pixCrcService->validatePixCode($pixCode);
            
            if (!$crcValidation['valid']) {
                Log::error('Código PIX com CRC inválido ao gerar QR Code', [
                    'errors' => $crcValidation['errors'],
                    'current_crc' => $crcValidation['current_crc'],
                    'calculated_crc' => $crcValidation['calculated_crc'],
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_end' => substr($pixCode, -10)
                ]);
                throw new Exception('Código PIX com CRC inválido não pode ser usado para gerar QR Code: ' . implode(', ', $crcValidation['errors']));
            }
            
            Log::debug('Gerando QR Code para código PIX validado', [
                'pix_code_length' => strlen($pixCode),
                'pix_code_start' => substr($pixCode, 0, 20),
                'crc' => substr($pixCode, -4),
                'crc_valid' => true
            ]);
            
            // Tenta gerar PNG (melhor qualidade para QR Codes)
            $hasImagick = extension_loaded('imagick');
            $hasGd = extension_loaded('gd');

            if ($hasImagick || $hasGd) {
                try {
                    // Usa error correction 'H' (High) para máxima confiabilidade
                    // Tamanho 300px com margem 2 para melhor leitura
                    $qrCodeData = QrCode::format('png')
                        ->size(300)
                        ->margin(2)
                        ->errorCorrection('H')
                        ->generate($pixCode);
                    
                    Log::debug('QR Code PNG gerado com sucesso', [
                        'image_size' => strlen($qrCodeData)
                    ]);
                    
                    return base64_encode($qrCodeData);
                } catch (Exception $e) {
                    Log::warning('Erro ao gerar QR Code PNG, tentando SVG', [
                        'error' => $e->getMessage()
                    ]);
                    // Fallback para SVG
                }
            }

            // Fallback: SVG (não requer extensões de imagem)
            $qrCodeData = QrCode::format('svg')
                ->size(300)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($pixCode);
            
            Log::debug('QR Code SVG gerado com sucesso', [
                'image_size' => strlen($qrCodeData)
            ]);
            
            return base64_encode($qrCodeData);
        } catch (Exception $e) {
            Log::error('Erro ao gerar imagem do QR Code', [
                'error' => $e->getMessage(),
                'pix_code_length' => strlen($pixCode ?? ''),
                'pix_code_start' => substr($pixCode ?? '', 0, 20)
            ]);
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

