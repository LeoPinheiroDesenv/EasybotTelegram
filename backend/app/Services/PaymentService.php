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
use Carbon\Carbon;

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

            // Log dos dados que serão enviados ao Mercado Pago
            Log::info('Criando pagamento PIX no Mercado Pago', [
                'transaction_amount' => $paymentData['transaction_amount'],
                'description' => $paymentData['description'],
                'payment_method_id' => $paymentData['payment_method_id'],
                'payer_email' => $paymentData['payer']['email'] ?? null,
                'has_cpf' => !empty($paymentData['payer']['identification'] ?? null),
                'external_reference' => $paymentData['external_reference'] ?? null,
                'has_notification_url' => !empty($paymentData['notification_url'] ?? null),
                'statement_descriptor' => $paymentData['statement_descriptor'] ?? null,
                'environment' => $gatewayConfig->environment
            ]);

            // Cria pagamento PIX no Mercado Pago
            $client = new PaymentClient();
            
            try {
                $payment = $client->create($paymentData);
            } catch (MPApiException $e) {
                $errorMessage = $e->getMessage();
                $apiResponse = $e->getApiResponse();
                $statusCode = $apiResponse ? $apiResponse->getStatusCode() : null;
                $content = $apiResponse ? $apiResponse->getContent() : null;
                
                Log::error('Erro ao criar pagamento PIX no Mercado Pago', [
                    'error_message' => $errorMessage,
                    'status_code' => $statusCode,
                    'api_response' => $content,
                    'payment_data' => $paymentData,
                    'environment' => $gatewayConfig->environment
                ]);
                
                // Verifica erros específicos relacionados a configuração da conta
                if ($content && is_array($content)) {
                    $message = $content['message'] ?? $errorMessage;
                    $cause = $content['cause'] ?? [];
                    
                    // Erro: conta sem chave PIX
                    if (strpos(strtolower($message), 'no_key') !== false || 
                        strpos(strtolower($message), 'chave pix') !== false ||
                        strpos(strtolower($message), 'collector_user_no_key') !== false) {
                        throw new Exception('ERRO: A conta do Mercado Pago não possui uma chave PIX cadastrada. Acesse sua conta do Mercado Pago e cadastre uma chave PIX em "Pix" → "Chave Pix" → "Cadastrar chave Pix".');
                    }
                    
                    // Erro: conta não habilitada
                    if (strpos(strtolower($message), 'not_enabled') !== false ||
                        strpos(strtolower($message), 'não habilitado') !== false ||
                        strpos(strtolower($message), 'not enabled') !== false) {
                        throw new Exception('ERRO: A conta do Mercado Pago não está habilitada para receber pagamentos via PIX. Verifique as configurações da conta ou entre em contato com o suporte do Mercado Pago.');
                    }
                    
                    // Erro: credenciais inválidas
                    if ($statusCode === 401 || strpos(strtolower($message), 'unauthorized') !== false) {
                        throw new Exception('ERRO: Credenciais do Mercado Pago inválidas. Verifique se o Access Token está correto e se as credenciais de produção estão ativas.');
                    }
                }
                
                throw new Exception('Erro ao criar pagamento no Mercado Pago: ' . $errorMessage);
            }

            if (!$payment || !isset($payment->id)) {
                Log::error('Erro ao criar pagamento no Mercado Pago - Resposta inválida', [
                    'payment_response' => $payment ? (array)$payment : null
                ]);
                throw new Exception('Erro ao criar pagamento no Mercado Pago. Resposta inválida.');
            }

            // Log da resposta do Mercado Pago
            Log::info('Pagamento PIX criado no Mercado Pago', [
                'payment_id' => $payment->id ?? null,
                'payment_status' => $payment->status ?? null,
                'has_point_of_interaction' => isset($payment->point_of_interaction),
                'point_of_interaction_type' => $payment->point_of_interaction->type ?? null,
                'point_of_interaction_keys' => isset($payment->point_of_interaction) ? array_keys((array)$payment->point_of_interaction) : []
            ]);

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
                    'point_of_interaction_keys' => isset($payment->point_of_interaction) ? array_keys((array)$payment->point_of_interaction) : [],
                    'payment_full_response' => json_encode($payment, JSON_PRETTY_PRINT),
                    'environment' => $gatewayConfig->environment,
                    'note' => 'Isso geralmente indica que a conta não possui chave PIX cadastrada ou não está habilitada para PIX'
                ]);
                
                $errorMessage = 'Erro ao obter dados do PIX do Mercado Pago. Pagamento criado mas sem dados PIX. ';
                $errorMessage .= 'Possíveis causas: ';
                $errorMessage .= '1) A conta não possui uma chave PIX cadastrada - acesse sua conta do Mercado Pago e cadastre uma chave PIX; ';
                $errorMessage .= '2) A conta não está habilitada para receber pagamentos via PIX; ';
                $errorMessage .= '3) A conta não está validada/verificada. ';
                $errorMessage .= 'Verifique a documentação em PROBLEMAS_QRCODE_PIX_MERCADOPAGO.md para mais detalhes.';
                
                throw new Exception($errorMessage);
            }

            // Extrai código PIX e QR Code
            // O Mercado Pago retorna:
            // - qr_code: código PIX EMV em formato string (texto puro)
            // - qr_code_base64: imagem do QR Code codificada em base64
            $pixCode = null;
            $pixCodeRaw = null;
            
            // IMPORTANTE: O Mercado Pago SEMPRE retorna qr_code (código EMV) quando cria um pagamento PIX
            // O qr_code_base64 é apenas a imagem do QR Code
            if (isset($pixData->qr_code)) {
                $pixCodeRaw = $pixData->qr_code;
                // CRÍTICO: Usa o código EXATAMENTE como vem do Mercado Pago - SEM MODIFICAÇÕES
                $pixCode = $pixCodeRaw;
                
                // Log do código original recebido
                Log::info('🔵🔵🔵 CÓDIGO PIX ORIGINAL DO MERCADO PAGO (EXATAMENTE COMO RECEBIDO)', [
                    'payment_id' => $payment->id ?? null,
                    'transaction_id' => $transaction->id ?? null,
                    'bot_id' => $bot->id ?? null,
                    'qr_code_length' => strlen($pixCodeRaw),
                    'qr_code_start' => substr($pixCodeRaw, 0, 30),
                    'qr_code_end' => substr($pixCodeRaw, -10),
                    'qr_code_full' => $pixCodeRaw, // CÓDIGO COMPLETO EXATAMENTE COMO VEIO DO MERCADO PAGO
                    'note' => 'CÓDIGO SERÁ USADO EXATAMENTE COMO RECEBIDO - SEM MODIFICAÇÕES'
                ]);
            } else {
                // Se não tiver qr_code, é um erro - o Mercado Pago sempre retorna
                Log::error('ERRO: Mercado Pago não retornou qr_code (código EMV)', [
                    'payment_id' => $payment->id ?? null,
                    'payment_status' => $payment->status ?? null,
                    'transaction_data_keys' => array_keys((array)$pixData),
                    'has_qr_code_base64' => isset($pixData->qr_code_base64),
                    'environment' => $gatewayConfig->environment,
                ]);
                
                $errorMessage = 'Erro ao gerar código PIX: O Mercado Pago não retornou o código PIX. ';
                $errorMessage .= 'Possíveis causas: ';
                $errorMessage .= '1) A conta não possui uma chave PIX cadastrada; ';
                $errorMessage .= '2) A conta não está habilitada para receber pagamentos via PIX; ';
                $errorMessage .= '3) A conta não está validada/verificada.';
                
                throw new Exception($errorMessage);
            }
            
            $pixCodeBase64 = $pixData->qr_code_base64 ?? null;
            $ticketUrl = $pixData->ticket_url ?? null;

            if (!$pixCode || empty($pixCode)) {
                Log::error('Código PIX vazio na resposta do Mercado Pago', [
                    'payment_id' => $payment->id ?? null,
                ]);
                throw new Exception('Erro ao gerar código PIX: O código retornado pelo Mercado Pago está vazio.');
            }

            // CRÍTICO: O código PIX do Mercado Pago deve ser usado EXATAMENTE como retornado
            // SEM MODIFICAÇÕES - SEM LIMPEZA - SEM VALIDAÇÃO - SEM CORREÇÃO
            // O código será usado exatamente como recebido do Mercado Pago
            
            Log::info('✅✅✅ Código PIX será usado EXATAMENTE como recebido do Mercado Pago (SEM MODIFICAÇÕES)', [
                'pix_code_length' => strlen($pixCode),
                'pix_code_start' => substr($pixCode, 0, 30),
                'pix_code_end' => substr($pixCode, -10),
                'pix_code_full' => $pixCode,
                'note' => 'Código usado EXATAMENTE como o Mercado Pago retornou - SEM NENHUMA MODIFICAÇÃO'
            ]);

            // CRÍTICO: Gera QR Code como imagem
            // PRIORIDADE ABSOLUTA: SEMPRE usa o QR Code base64 do Mercado Pago quando disponível
            // O QR Code do Mercado Pago já está correto, validado e funcionando
            // NÃO devemos gerar localmente se o Mercado Pago forneceu o QR Code
            $qrCodeImage = null;
            
            // PRIORIDADE 1: SEMPRE usa o QR Code base64 do Mercado Pago (OBRIGATÓRIO quando disponível)
            if (!empty($pixCodeBase64)) {
                try {
                    $decodedBase64 = base64_decode($pixCodeBase64, true);
                    if ($decodedBase64 !== false && strlen($decodedBase64) > 100) {
                        // Verifica se é uma imagem válida (PNG, JPEG ou SVG)
                        $isPng = substr($decodedBase64, 0, 8) === "\x89PNG\r\n\x1a\n";
                        $isJpeg = substr($decodedBase64, 0, 2) === "\xFF\xD8";
                        $isSvg = strpos($decodedBase64, '<svg') !== false || strpos($decodedBase64, '<?xml') !== false;
                        
                        if ($isPng || $isJpeg || $isSvg) {
                            $qrCodeImage = $pixCodeBase64;
                            Log::info('✅✅✅ USANDO QR CODE DO MERCADO PAGO (OBRIGATÓRIO)', [
                                'format' => $isPng ? 'PNG' : ($isJpeg ? 'JPEG' : 'SVG'),
                                'size' => strlen($decodedBase64),
                                'pix_code_length' => strlen($pixCode),
                                'pix_code_end' => substr($pixCode, -10),
                                'pix_code_crc' => substr($pixCode, -4),
                                'note' => 'QR Code do Mercado Pago - já validado, correto e funcionando - NÃO SERÁ GERADO LOCALMENTE'
                            ]);
                        } else {
                            Log::warning('QR Code base64 do Mercado Pago não é PNG/JPEG/SVG válido', [
                                'data_start_hex' => bin2hex(substr($decodedBase64, 0, 20)),
                                'data_start_ascii' => substr($decodedBase64, 0, 50)
                            ]);
                        }
                    } else {
                        Log::warning('QR Code base64 do Mercado Pago muito pequeno ou inválido', [
                            'decoded_length' => $decodedBase64 ? strlen($decodedBase64) : 0
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('ERRO ao processar QR Code base64 do Mercado Pago', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            } else {
                Log::warning('QR Code base64 do Mercado Pago não disponível - será gerado localmente', [
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_start' => substr($pixCode, 0, 30),
                    'note' => 'ATENÇÃO: Gerando QR Code localmente - pode não funcionar corretamente'
                ]);
            }
            
            // PRIORIDADE 2: Gera localmente APENAS se o Mercado Pago NÃO forneceu o QR Code
            // IMPORTANTE: Se o Mercado Pago forneceu o QR Code, NÃO devemos gerar localmente
            if (!$qrCodeImage) {
                Log::warning('⚠️⚠️⚠️ ATENÇÃO: Gerando QR Code localmente (Mercado Pago não forneceu QR Code)', [
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_start' => substr($pixCode, 0, 30),
                    'pix_code_end' => substr($pixCode, -10),
                    'pix_code_crc' => substr($pixCode, -4),
                    'pix_code_full' => $pixCode, // Log completo do código que será usado
                    'note' => 'QR Code local pode não funcionar - use o QR Code do Mercado Pago quando disponível'
                ]);
                
                // CRÍTICO: Usa o código PIX EXATO para gerar o QR Code
                // O código usado aqui DEVE ser idêntico ao código copia e cola
                $qrCodeImage = $this->generateQrCodeImage($pixCode);
                
                Log::info('QR Code gerado localmente', [
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_end' => substr($pixCode, -10),
                    'pix_code_crc' => substr($pixCode, -4),
                    'pix_code_full' => $pixCode, // Log completo
                    'note' => 'QR Code local gerado - código usado é o mesmo do copia e cola'
                ]);
            } else {
                // QR Code do Mercado Pago foi usado - valida que o código copia e cola é o mesmo
                Log::info('✅✅✅ QR Code do Mercado Pago será usado - código copia e cola sincronizado', [
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_end' => substr($pixCode, -10),
                    'pix_code_crc' => substr($pixCode, -4),
                    'pix_code_full' => $pixCode, // Log completo
                    'note' => 'QR Code do Mercado Pago - código copia e cola é o mesmo do QR Code'
                ]);
            }

            // Salva QR Code temporariamente (opcional)
            $qrCodePath = $this->saveQrCodeImage($transactionId, $qrCodeImage);
            
            // Atualiza transação com informações do PIX do Mercado Pago
            // IMPORTANTE: O pix_code é salvo como string no metadata (não será modificado pelo JSON)
            $metadata = $transaction->metadata ?? [];
            $metadata['mercadopago_payment_id'] = $payment->id;
            $metadata['mercadopago_status'] = $payment->status ?? 'pending';
            $metadata['pix_code'] = $pixCode; // Código EXATO do Mercado Pago (string)
            $metadata['pix_ticket_url'] = $ticketUrl;
            $metadata['qr_code_path'] = $qrCodePath;
            $metadata['expiration_date'] = $pixData->expiration_date ?? null;
            
            // Log antes de salvar
            Log::info('Salvando código PIX no metadata da transação', [
                'transaction_id' => $transaction->id,
                'pix_code_length' => strlen($pixCode),
                'pix_code_start' => substr($pixCode, 0, 30),
                'pix_code_end' => substr($pixCode, -10),
                'pix_code_crc' => substr($pixCode, -4),
                'note' => 'Código será salvo como string no JSON metadata'
            ]);
            
            $transaction->update([
                'gateway_transaction_id' => (string) $payment->id,
                'metadata' => $metadata
            ]);
            
            // Valida que o código foi salvo corretamente
            $transaction->refresh();
            $savedPixCode = $transaction->metadata['pix_code'] ?? null;
            
            if ($savedPixCode !== $pixCode) {
                Log::error('ERRO CRÍTICO: Código PIX foi modificado ao salvar no metadata!', [
                    'original_length' => strlen($pixCode),
                    'saved_length' => $savedPixCode ? strlen($savedPixCode) : 0,
                    'original_end' => substr($pixCode, -10),
                    'saved_end' => $savedPixCode ? substr($savedPixCode, -10) : null,
                    'original_crc' => substr($pixCode, -4),
                    'saved_crc' => $savedPixCode ? substr($savedPixCode, -4) : null
                ]);
                // Corrige o código salvo
                $metadata['pix_code'] = $pixCode;
                $transaction->update(['metadata' => $metadata]);
                Log::info('Código PIX corrigido no metadata');
            } else {
                Log::info('✅ Código PIX salvo corretamente no metadata', [
                    'pix_code_length' => strlen($savedPixCode),
                    'pix_code_end' => substr($savedPixCode, -10)
                ]);
            }

            // VERIFICAÇÃO AUTOMÁTICA IMEDIATA: Verifica o status do pagamento imediatamente após criar
            try {
                $this->checkPaymentStatusImmediately($transaction, $gatewayConfig);
            } catch (\Exception $e) {
                Log::warning('Erro ao verificar status do pagamento imediatamente após criação', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Log final: código será retornado EXATAMENTE como recebido do Mercado Pago
            Log::info('🔴🔴🔴 CÓDIGO PIX FINAL - COMPARE COM O ORIGINAL', [
                'payment_id' => $payment->id ?? null,
                'transaction_id' => $transaction->id ?? null,
                'bot_id' => $bot->id ?? null,
                'mercado_pago_code_original' => $pixCodeRaw, // CÓDIGO ORIGINAL DO MERCADO PAGO
                'mercado_pago_code_original_length' => strlen($pixCodeRaw),
                'code_to_return' => $pixCode, // Código que será retornado (EXATAMENTE como recebido)
                'code_to_return_length' => strlen($pixCode),
                'codes_are_identical' => ($pixCodeRaw === $pixCode), // TRUE - não foi modificado
                'pix_code_full' => $pixCode, // Código completo
                'note' => 'Código usado EXATAMENTE como recebido do Mercado Pago - SEM MODIFICAÇÕES'
            ]);
            
            return [
                'success' => true,
                'transaction' => $transaction,
                'pix_key' => null,
                'pix_code' => $pixCode, // Código EXATAMENTE como recebido do Mercado Pago
                'qr_code_image' => $qrCodeImage,
                'qr_code_path' => $qrCodePath,
                'ticket_url' => $ticketUrl,
                'payment_id' => $payment->id
            ];
        } catch (MPApiException $e) {
            $errorMessage = 'Erro ao gerar código PIX: ';
            if ($e->getApiResponse() && isset($e->getApiResponse()->getContent()['message'])) {
                $errorMessage .= $e->getApiResponse()->getContent()['message'];
            } else {
                $errorMessage .= $e->getMessage();
            }

            Log::error('Erro ao gerar código PIX via Mercado Pago', [
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
            $errorMessage = 'Erro ao gerar código PIX: ' . $e->getMessage();
            
            Log::error('Erro ao gerar código PIX', [
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
        }
    }


    /**
     * Limpa código PIX de forma segura - remove APENAS espaços/quebras
     * Esta função garante que o código não seja corrompido
     * 
     * IMPORTANTE: Esta função é usada em TODOS os lugares para garantir consistência
     * Remove APENAS espaços em branco e quebras de linha
     * NÃO remove nenhum caractere alfanumérico ou especial válido
     * 
     * @param string $pixCode Código PIX original
     * @return string Código PIX limpo (apenas espaços/quebras removidos)
     */
    public static function cleanPixCodeSafely(string $pixCode): string
    {
        // Preserva o código original para comparação e validação
        $original = $pixCode;
        $originalLength = strlen($original);
        
        // Valida que o código original não está vazio
        if (empty($original)) {
            return $original;
        }
        
        // Remove APENAS espaços em branco e quebras de linha
        // NÃO remove nenhum caractere alfanumérico ou especial válido
        $cleaned = trim($pixCode); // Remove espaços no início e fim
        
        // Remove quebras de linha e tabs (mas preserva todos os outros caracteres)
        $cleaned = str_replace(["\r\n", "\n", "\r", "\t"], '', $cleaned);
        
        // Remove TODOS os espaços em branco (mas preserva todos os outros caracteres)
        $cleaned = preg_replace('/\s+/', '', $cleaned);
        
        $cleanedLength = strlen($cleaned);
        $lengthDiff = $originalLength - $cleanedLength;
        
        // Validação crítica: verifica se o código não foi corrompido
        // O código PIX EMV deve começar com 000201
        $originalStartsWith000201 = str_starts_with($original, '000201');
        $cleanedStartsWith000201 = str_starts_with($cleaned, '000201');
        
        if (!$cleanedStartsWith000201 && $originalStartsWith000201) {
            Log::error('ERRO CRÍTICO: Código PIX foi corrompido durante limpeza!', [
                'original_start' => substr($original, 0, 30),
                'cleaned_start' => substr($cleaned, 0, 30),
                'original_length' => $originalLength,
                'cleaned_length' => $cleanedLength,
                'length_diff' => $lengthDiff,
                'original_hex_start' => bin2hex(substr($original, 0, 20)),
                'cleaned_hex_start' => bin2hex(substr($cleaned, 0, 20))
            ]);
            // Retorna o original se a limpeza corrompeu
            return $original;
        }
        
        // Valida que o comprimento não mudou drasticamente (mais de 5% de diferença)
        // Espaços/quebras normalmente representam menos de 5% do código
        if ($lengthDiff > $originalLength * 0.05 && $lengthDiff > 10) {
            Log::warning('ATENÇÃO: Muitos caracteres removidos na limpeza', [
                'original_length' => $originalLength,
                'cleaned_length' => $cleanedLength,
                'length_diff' => $lengthDiff,
                'percentage' => ($lengthDiff / $originalLength) * 100,
                'original_start' => substr($original, 0, 30),
                'cleaned_start' => substr($cleaned, 0, 30)
            ]);
        }
        
        // Valida que o código limpo ainda tem comprimento válido (mínimo 100 caracteres)
        if ($cleanedLength < 100 && $originalLength >= 100) {
            Log::error('ERRO CRÍTICO: Código PIX ficou muito curto após limpeza!', [
                'original_length' => $originalLength,
                'cleaned_length' => $cleanedLength,
                'length_diff' => $lengthDiff
            ]);
            // Retorna o original se ficou muito curto
            return $original;
        }
        
        return $cleaned;
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
            // CRÍTICO: O código PIX deve ser usado EXATAMENTE como recebido
            // NÃO devemos limpar, modificar ou alterar o código
            // Usa o código EXATAMENTE como recebido do Mercado Pago
            
            // CRÍTICO: O código PIX deve ser usado EXATAMENTE como recebido
            // NÃO devemos limpar, modificar ou alterar o código
            // Usa o código EXATAMENTE como recebido do Mercado Pago
            
            Log::info('Gerando QR Code para código PIX (EXATAMENTE como recebido)', [
                'pix_code_length' => strlen($pixCode),
                'pix_code_full' => $pixCode,
                'note' => 'Código usado EXATAMENTE como recebido - SEM MODIFICAÇÕES'
            ]);
            
            // Tenta gerar PNG (melhor qualidade para QR Codes)
            $hasImagick = extension_loaded('imagick');
            $hasGd = extension_loaded('gd');

            if ($hasImagick || $hasGd) {
                try {
                    // Configurações otimizadas para QR Code PIX
                    // Error Correction 'H' (High) = ~30% de correção - máxima confiabilidade
                    // Tamanho 300px - suficiente para boa leitura
                    // Margem 4 - margem adequada para leitores
                    $qrCodeData = QrCode::format('png')
                        ->size(300)
                        ->margin(4)
                        ->errorCorrection('H')
                        ->generate($pixCode);
                    
                    Log::info('QR Code PNG gerado com sucesso', [
                        'image_size' => strlen($qrCodeData),
                        'pix_code_length' => strlen($pixCode),
                        'pix_code_used' => $pixCode, // Log do código usado
                        'note' => 'QR Code PNG gerado - verifique se o código está correto'
                    ]);
                    
                    return base64_encode($qrCodeData);
                } catch (Exception $e) {
                    Log::warning('Erro ao gerar QR Code PNG, tentando SVG', [
                        'error' => $e->getMessage(),
                        'pix_code_length' => strlen($pixCode)
                    ]);
                    // Fallback para SVG
                }
            }

            // Fallback: SVG (não requer extensões de imagem)
            // IMPORTANTE: Usa o código PIX EXATO do Mercado Pago (sem modificações)
            $qrCodeData = QrCode::format('svg')
                ->size(300)
                ->margin(4)
                ->errorCorrection('H')
                ->generate($pixCode);
            
            Log::info('QR Code SVG gerado com sucesso', [
                'image_size' => strlen($qrCodeData),
                'pix_code_length' => strlen($pixCode),
                'pix_code_used' => $pixCode, // Log do código usado
                'note' => 'QR Code SVG gerado - verifique se o código está correto'
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

    /**
     * Verifica o status do pagamento imediatamente após criar
     * Isso garante que pagamentos já aprovados sejam processados imediatamente
     *
     * @param Transaction $transaction
     * @param PaymentGatewayConfig $gatewayConfig
     * @return void
     */
    protected function checkPaymentStatusImmediately(Transaction $transaction, PaymentGatewayConfig $gatewayConfig): void
    {
        try {
            if ($transaction->gateway !== 'mercadopago') {
                return;
            }

            // Recarrega a transação para garantir que temos os dados mais recentes
            $transaction->refresh();
            
            $paymentId = $transaction->gateway_transaction_id ?? $transaction->metadata['mercadopago_payment_id'] ?? null;
            
            if (!$paymentId) {
                Log::warning('Não é possível verificar status: transação sem payment_id', [
                    'transaction_id' => $transaction->id
                ]);
                return;
            }

            // Configura o SDK do Mercado Pago
            MercadoPagoConfig::setAccessToken($gatewayConfig->api_key);
            $client = new PaymentClient();
            
            // Busca o status atual do pagamento
            $payment = $client->get($paymentId);
            
            if ($payment) {
                $status = $payment->status ?? 'pending';
                
                Log::info('Verificação imediata de status do pagamento', [
                    'transaction_id' => $transaction->id,
                    'payment_id' => $paymentId,
                    'status' => $status
                ]);
                
                // Se já está aprovado, processa imediatamente
                if ($status === 'approved') {
                    Log::info('Pagamento já aprovado imediatamente após criação - processando', [
                        'transaction_id' => $transaction->id,
                        'payment_id' => $paymentId
                    ]);
                    
                    // Recarrega relacionamentos antes de processar
                    $transaction->load(['bot', 'contact', 'paymentPlan']);
                    
                    // Processa a aprovação usando o mesmo código do webhook
                    $this->processPaymentApproval($transaction, $payment, $gatewayConfig);
                }
            }
        } catch (MPApiException $e) {
            $errorMessage = $e->getMessage();
            $apiResponse = $e->getApiResponse();
            $statusCode = $apiResponse ? $apiResponse->getStatusCode() : null;
            $responseContent = $apiResponse ? $apiResponse->getContent() : null;
            
            // Verifica se é o erro "Chave não localizada" (payment não encontrado)
            $isKeyNotFound = stripos($errorMessage, 'chave não localizada') !== false 
                || stripos($errorMessage, 'key not found') !== false
                || stripos($errorMessage, 'not found') !== false
                || ($statusCode === 404)
                || (isset($responseContent['message']) && (
                    stripos($responseContent['message'], 'chave não localizada') !== false ||
                    stripos($responseContent['message'], 'not found') !== false
                ));
            
            if ($isKeyNotFound) {
                // Pagamento não encontrado - marca transação como inválida
                Log::warning('⚠️ Pagamento não encontrado no Mercado Pago (Chave não localizada)', [
                    'transaction_id' => $transaction->id,
                    'payment_id' => $paymentId,
                    'status_code' => $statusCode,
                    'api_response' => $responseContent,
                    'note' => 'O payment_id pode estar incorreto ou o pagamento foi deletado no Mercado Pago'
                ]);
                
                // Atualiza metadata para indicar que o pagamento não foi encontrado
                $metadata = $transaction->metadata ?? [];
                $metadata['payment_not_found'] = true;
                $metadata['payment_not_found_at'] = now()->toIso8601String();
                $metadata['payment_not_found_error'] = $errorMessage;
                $transaction->update(['metadata' => $metadata]);
            } else {
                Log::warning('Erro ao verificar status do pagamento imediatamente', [
                    'transaction_id' => $transaction->id,
                    'payment_id' => $paymentId,
                    'error' => $errorMessage,
                    'status_code' => $statusCode,
                    'api_response' => $responseContent
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Erro ao verificar status do pagamento imediatamente', [
                'transaction_id' => $transaction->id,
                'payment_id' => $paymentId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Processa a aprovação de um pagamento (reutilizável)
     *
     * @param Transaction $transaction
     * @param object $payment
     * @param PaymentGatewayConfig $gatewayConfig
     * @return void
     */
    public function processPaymentApproval(Transaction $transaction, $payment, PaymentGatewayConfig $gatewayConfig): void
    {
        try {
            $status = $payment->status ?? 'pending';
            $statusDetail = $payment->status_detail ?? null;
            
            // Salva o status anterior
            $oldStatus = $transaction->status;
            
            // Mapeia status do Mercado Pago para status interno
            $internalStatus = 'pending';
            if ($status === 'approved') {
                $internalStatus = 'completed';
            } elseif ($status === 'rejected' || $status === 'cancelled') {
                $internalStatus = 'failed';
            } elseif ($status === 'refunded') {
                $internalStatus = 'refunded';
            } elseif ($status === 'charged_back') {
                $internalStatus = 'charged_back';
            }

            // Atualiza transação
            $metadata = $transaction->metadata ?? [];
            $metadata['mercadopago_status'] = $status;
            $metadata['mercadopago_status_detail'] = $statusDetail;
            $metadata['last_status_check'] = now()->toIso8601String();

            $transaction->update([
                'status' => $internalStatus,
                'metadata' => $metadata
            ]);
            
            // Recarrega a transação com os relacionamentos
            $transaction->refresh();
            $transaction->load(['bot', 'contact', 'paymentPlan']);

            // Se o pagamento foi aprovado, notifica o usuário
            // CRÍTICO: Sempre envia notificação quando pagamento é aprovado
            // Isso garante que o link do grupo seja sempre enviado/renovado
            if ($status === 'approved' && $internalStatus === 'completed') {
                // Verifica se deve notificar
                // Se é uma NOVA transação (status anterior era pending), sempre notifica
                // Se é uma atualização (já estava aprovado), verifica se foi notificado recentemente
                $shouldNotify = true;
                
                // Se já estava aprovado, verifica se foi notificado recentemente (últimas 2 minutos)
                // Isso evita notificações duplicadas em caso de webhooks repetidos do mesmo pagamento
                // Mas permite notificações em renovações (novos pagamentos)
                if (in_array($oldStatus, ['approved', 'paid', 'completed'])) {
                    $lastNotification = $metadata['payment_approval_notified_at'] ?? null;
                    if ($lastNotification) {
                        try {
                            $lastNotificationDate = Carbon::parse($lastNotification);
                            $minutesSinceNotification = now()->diffInMinutes($lastNotificationDate);
                            
                            // Se foi notificado há menos de 2 minutos, não notifica novamente (webhook duplicado)
                            // Se foi há mais de 2 minutos, pode ser uma renovação ou atualização, então notifica
                            if ($minutesSinceNotification < 2) {
                                $shouldNotify = false;
                                Log::info('Pagamento já estava aprovado e foi notificado recentemente (webhook duplicado) - pulando notificação', [
                                    'transaction_id' => $transaction->id,
                                    'old_status' => $oldStatus,
                                    'minutes_since_notification' => $minutesSinceNotification
                                ]);
                            } else {
                                // Foi notificado há mais de 2 minutos - pode ser renovação, então notifica
                                Log::info('Pagamento já estava aprovado mas notificação foi há mais de 2 minutos - enviando novamente (possível renovação)', [
                                    'transaction_id' => $transaction->id,
                                    'old_status' => $oldStatus,
                                    'minutes_since_notification' => $minutesSinceNotification
                                ]);
                            }
                        } catch (\Exception $e) {
                            // Se houver erro ao parsear data, notifica mesmo assim
                            Log::warning('Erro ao verificar última notificação - notificando mesmo assim', [
                                'transaction_id' => $transaction->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    } else {
                        // Não tem registro de notificação - pode ser renovação ou primeira notificação, então notifica
                        Log::info('Pagamento já estava aprovado mas sem registro de notificação - enviando (possível renovação ou primeira notificação)', [
                            'transaction_id' => $transaction->id,
                            'old_status' => $oldStatus
                        ]);
                    }
                } else {
                    // Nova transação (status anterior era pending) - sempre notifica
                    Log::info('Nova transação aprovada - enviando notificação com link do grupo', [
                        'transaction_id' => $transaction->id,
                        'old_status' => $oldStatus,
                        'new_status' => $internalStatus
                    ]);
                }
                
                if ($shouldNotify && $transaction->contact && $transaction->bot && !empty($transaction->contact->telegram_id)) {
                    // Marca timestamp da notificação antes de enviar
                    $metadata['payment_approval_notified_at'] = now()->toIso8601String();
                    $transaction->update(['metadata' => $metadata]);
                    
                    $this->sendPaymentApprovalNotification($transaction);
                }
            }
        } catch (Exception $e) {
            Log::error('Erro ao processar aprovação de pagamento', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Envia notificação de pagamento aprovado
     *
     * @param Transaction $transaction
     * @return void
     */
    public function sendPaymentApprovalNotification(Transaction $transaction): void
    {
        try {
            // CRÍTICO: Garante que todos os relacionamentos estão carregados
            if (!$transaction->relationLoaded('bot')) {
                $transaction->load('bot');
            }
            if (!$transaction->relationLoaded('contact')) {
                $transaction->load('contact');
            }
            if (!$transaction->relationLoaded('paymentPlan')) {
                $transaction->load('paymentPlan');
            }
            
            // Validações essenciais
            if (!$transaction->bot) {
                Log::error('❌ Bot não encontrado na transação', [
                    'transaction_id' => $transaction->id,
                    'bot_id' => $transaction->bot_id
                ]);
                return;
            }
            
            if (!$transaction->contact || empty($transaction->contact->telegram_id)) {
                Log::error('❌ Contact não encontrado ou sem telegram_id', [
                    'transaction_id' => $transaction->id,
                    'contact_id' => $transaction->contact_id
                ]);
                return;
            }
            
            $telegramService = app(\App\Services\TelegramService::class);
            $paymentPlan = $transaction->paymentPlan;
            $amount = number_format($transaction->amount, 2, ',', '.');
            
            $message = "✅ <b>Pagamento Confirmado!</b>\n\n";
            $message .= "Olá " . ($transaction->contact->first_name ?? 'Cliente') . ",\n\n";
            $message .= "Seu pagamento foi confirmado com sucesso!\n\n";
            $message .= "📦 <b>Plano:</b> " . ($paymentPlan->title ?? 'N/A') . "\n";
            $message .= "💰 <b>Valor:</b> R$ {$amount}\n\n";
            
            // CRÍTICO: Garante que paymentCycle está carregado para calcular expiração
            if (!$transaction->relationLoaded('paymentCycle')) {
                $transaction->load('paymentCycle');
            }
            
            // CRÍTICO: Busca o link do grupo para enviar ao usuário - ESTRATÉGIA ROBUSTA
            // Esta função garante que sempre tentará encontrar um link do grupo
            // O link será criado com expiração baseada no ciclo do plano
            $groupLink = $this->findGroupInviteLink($transaction, $telegramService);
            
            // CRÍTICO: Verifica se o link está expirado antes de enviar
            if ($groupLink) {
                $metadata = $transaction->metadata ?? [];
                $savedLink = $metadata['group_invite_link'] ?? null;
                $expiresAt = $metadata['group_invite_link_expires_at'] ?? null;
                
                // Se o link retornado é diferente do salvo, ou se não há metadata, atualiza
                if ($savedLink !== $groupLink || !$expiresAt) {
                    // Calcula data de expiração
                    $expireDate = null;
                    if ($transaction->paymentCycle) {
                        $days = $transaction->paymentCycle->days ?? 30;
                        $expireDate = \Carbon\Carbon::now()->addDays($days);
                    }
                    
                    // Atualiza metadata com link e expiração
                    $metadata['group_invite_link'] = $groupLink;
                    if ($expireDate) {
                        $metadata['group_invite_link_expires_at'] = $expireDate->toIso8601String();
                        $metadata['group_invite_link_created_at'] = now()->toIso8601String();
                    }
                    $transaction->update(['metadata' => $metadata]);
                    
                    Log::info('✅ Metadata atualizado com link e expiração após findGroupInviteLink', [
                        'transaction_id' => $transaction->id,
                        'expires_at' => $expireDate ? $expireDate->toDateTimeString() : null
                    ]);
                } else if ($expiresAt) {
                    // Verifica se o link salvo está expirado
                    try {
                        $expireDate = \Carbon\Carbon::parse($expiresAt);
                        if (now()->greaterThan($expireDate)) {
                            Log::warning('⚠️ Link salvo está expirado, buscando novo link', [
                                'transaction_id' => $transaction->id,
                                'expires_at' => $expireDate->toDateTimeString()
                            ]);
                            
                            // Link expirado, busca novo
                            $groupLink = $this->findGroupInviteLink($transaction, $telegramService);
                            
                            // Atualiza metadata com novo link
                            if ($groupLink) {
                                $expireDate = null;
                                if ($transaction->paymentCycle) {
                                    $days = $transaction->paymentCycle->days ?? 30;
                                    $expireDate = \Carbon\Carbon::now()->addDays($days);
                                }
                                
                                $metadata['group_invite_link'] = $groupLink;
                                if ($expireDate) {
                                    $metadata['group_invite_link_expires_at'] = $expireDate->toIso8601String();
                                    $metadata['group_invite_link_created_at'] = now()->toIso8601String();
                                    $metadata['group_invite_link_renewed_at'] = now()->toIso8601String();
                                }
                                $transaction->update(['metadata' => $metadata]);
                                
                                Log::info('✅ Link expirado renovado e metadata atualizado', [
                                    'transaction_id' => $transaction->id,
                                    'expires_at' => $expireDate ? $expireDate->toDateTimeString() : null
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Erro ao verificar expiração do link salvo', [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // Se não encontrou, tenta estratégias adicionais mais agressivas
            if (!$groupLink) {
                Log::warning('⚠️ Link do grupo NÃO encontrado na primeira tentativa - tentando estratégias alternativas', [
                    'transaction_id' => $transaction->id,
                    'bot_id' => $transaction->bot_id,
                    'payment_plan_id' => $paymentPlan->id ?? null
                ]);
                
                // ESTRATÉGIA EXTRA 1: Busca qualquer grupo do bot sem filtros (incluindo inativos)
                $lastResortGroup = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)
                    ->whereNotNull('invite_link')
                    ->orderBy('updated_at', 'desc')
                    ->first();
                
                if ($lastResortGroup && $lastResortGroup->invite_link) {
                    $groupLink = $lastResortGroup->invite_link;
                    Log::info('✅ Link encontrado em grupo sem filtros (estratégia extra)', [
                        'transaction_id' => $transaction->id,
                        'group_id' => $lastResortGroup->id,
                        'invite_link' => $groupLink
                    ]);
                }
                
                // ESTRATÉGIA EXTRA 2: Se ainda não encontrou, tenta obter via API do grupo do bot
                if (!$groupLink && !empty($transaction->bot->telegram_group_id)) {
                    try {
                        $linkResult = $telegramService->getChatInviteLink(
                            $transaction->bot->token,
                            $transaction->bot->telegram_group_id,
                            null
                        );
                        
                        if ($linkResult['success'] && !empty($linkResult['invite_link'])) {
                            $groupLink = $linkResult['invite_link'];
                            Log::info('✅ Link obtido via API direta do grupo do bot', [
                                'transaction_id' => $transaction->id,
                                'group_link' => $groupLink
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('⚠️ Erro ao tentar obter link via API direta', [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // CRÍTICO: Adiciona o link do grupo na mensagem - IMPRESCINDÍVEL
            if ($groupLink) {
                $message .= "🔗 <b>Acesse nosso grupo exclusivo:</b>\n";
                $message .= "{$groupLink}\n\n";
                Log::info('✅ Link do grupo adicionado à mensagem de confirmação de pagamento', [
                    'transaction_id' => $transaction->id,
                    'group_link' => $groupLink,
                    'contact_telegram_id' => $transaction->contact->telegram_id
                ]);
            } else {
                // Se ainda não tem link, adiciona uma mensagem informativa mas NÃO bloqueia o envio
                Log::error('❌ CRÍTICO: Link do grupo NÃO encontrado após TODAS as tentativas', [
                    'transaction_id' => $transaction->id,
                    'bot_id' => $transaction->bot_id,
                    'payment_plan_id' => $paymentPlan->id ?? null,
                    'bot_telegram_group_id' => $transaction->bot->telegram_group_id ?? null,
                    'action_required' => 'Verifique se há grupos configurados para este bot/plano e se o bot tem permissões'
                ]);
                
                // Adiciona mensagem informando que o link será enviado posteriormente
                $message .= "⚠️ <i>O link do grupo será enviado em breve. Entre em contato conosco se necessário.</i>\n\n";
            }
            
            $message .= "Obrigado pela sua compra! 🎉";
            
            // CRÍTICO: Verifica se o link está na mensagem antes de enviar
            $linkInMessage = !empty($groupLink) && strpos($message, $groupLink) !== false;
            
            Log::info('📤 Preparando envio de notificação de pagamento aprovado', [
                'transaction_id' => $transaction->id,
                'contact_telegram_id' => $transaction->contact->telegram_id,
                'group_link_found' => !empty($groupLink),
                'group_link' => $groupLink,
                'link_in_message' => $linkInMessage,
                'message_length' => strlen($message),
                'message_preview' => substr($message, 0, 300)
            ]);
            
            // Envia a mensagem
            try {
                $telegramService->sendMessage(
                    $transaction->bot,
                    $transaction->contact->telegram_id,
                    $message
                );
                
                Log::info('✅ Notificação de pagamento aprovado enviada com SUCESSO', [
                    'transaction_id' => $transaction->id,
                    'contact_id' => $transaction->contact->id,
                    'contact_telegram_id' => $transaction->contact->telegram_id,
                    'group_link_sent' => !empty($groupLink),
                    'group_link' => $groupLink,
                    'link_in_message' => $linkInMessage,
                    'message_length' => strlen($message),
                    'message_preview' => substr($message, 0, 300)
                ]);
            } catch (\Exception $e) {
                Log::error('❌ Erro ao enviar mensagem de notificação', [
                    'transaction_id' => $transaction->id,
                    'contact_telegram_id' => $transaction->contact->telegram_id,
                    'error' => $e->getMessage()
                ]);
                throw $e; // Re-lança para que o erro seja tratado no nível superior
            }
        } catch (\Exception $e) {
            Log::error('Erro ao enviar notificação de pagamento aprovado', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Busca o link de convite do grupo usando múltiplas estratégias
     * Garante que sempre tenta encontrar um link antes de desistir
     *
     * @param Transaction $transaction
     * @param \App\Services\TelegramService $telegramService
     * @return string|null
     */
    public function findGroupInviteLink(Transaction $transaction, \App\Services\TelegramService $telegramService): ?string
    {
        $groupLink = null;
        
        // CRÍTICO: Garante que os relacionamentos estão carregados
        if (!$transaction->relationLoaded('paymentPlan')) {
            $transaction->load('paymentPlan');
        }
        if (!$transaction->relationLoaded('bot')) {
            $transaction->load('bot');
        }
        if (!$transaction->relationLoaded('paymentCycle')) {
            $transaction->load('paymentCycle');
        }
        
        $paymentPlan = $transaction->paymentPlan;
        
        // CRÍTICO: Calcula data de expiração baseada no ciclo do plano ANTES de buscar links
        // Isso garante que TODOS os links criados terão a expiração correta
        $expireDate = null;
        if ($transaction->paymentCycle) {
            $days = $transaction->paymentCycle->days ?? 30;
            $expireDate = \Carbon\Carbon::now()->addDays($days);
            Log::info('📅 Data de expiração calculada para link do grupo', [
                'transaction_id' => $transaction->id,
                'cycle_days' => $days,
                'expire_date' => $expireDate->toDateTimeString()
            ]);
        } else {
            Log::warning('⚠️ Transação sem ciclo de pagamento - link será criado sem expiração', [
                'transaction_id' => $transaction->id
            ]);
        }
        
        // CRÍTICO: Primeiro verifica quantos grupos existem no banco para este bot
        $totalGroups = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)->count();
        $activeGroups = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)
            ->where('active', true)
            ->count();
        
        // Lista todos os grupos do bot para debug
        $allBotGroups = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)
            ->get(['id', 'title', 'telegram_group_id', 'payment_plan_id', 'active', 'type']);
        
        Log::info('🔍 Iniciando busca ROBUSTA de link do grupo para notificação de pagamento', [
            'transaction_id' => $transaction->id,
            'bot_id' => $transaction->bot_id,
            'payment_plan_id' => $paymentPlan->id ?? null,
            'payment_plan_title' => $paymentPlan->title ?? null,
            'bot_telegram_group_id' => $transaction->bot->telegram_group_id ?? null,
            'has_expire_date' => !is_null($expireDate),
            'expire_date' => $expireDate ? $expireDate->toDateTimeString() : null,
            'total_groups_for_bot' => $totalGroups,
            'active_groups_for_bot' => $activeGroups,
            'all_bot_groups' => $allBotGroups->map(function($g) {
                return [
                    'id' => $g->id,
                    'title' => $g->title,
                    'telegram_group_id' => $g->telegram_group_id,
                    'payment_plan_id' => $g->payment_plan_id,
                    'active' => $g->active,
                    'type' => $g->type
                ];
            })->toArray()
        ]);
        
        // ESTRATÉGIA 1: Busca grupo associado ao plano de pagamento (com link salvo)
        if ($paymentPlan) {
            // Primeiro tenta buscar grupo ativo associado ao plano
            $telegramGroup = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)
                ->where('payment_plan_id', $paymentPlan->id)
                ->where('active', true)
                ->first();
            
            // Se não encontrou grupo ativo, tenta buscar qualquer grupo (ativo ou inativo) associado ao plano
            if (!$telegramGroup) {
                $telegramGroup = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)
                    ->where('payment_plan_id', $paymentPlan->id)
                    ->first();
                
                if ($telegramGroup) {
                    Log::warning('⚠️ Grupo encontrado mas está inativo - tentando usar mesmo assim', [
                        'transaction_id' => $transaction->id,
                        'group_id' => $telegramGroup->id,
                        'active' => $telegramGroup->active
                    ]);
                }
            }
            
            if ($telegramGroup) {
                Log::info('✅ Grupo associado ao plano encontrado', [
                    'transaction_id' => $transaction->id,
                    'group_id' => $telegramGroup->id,
                    'telegram_group_id' => $telegramGroup->telegram_group_id,
                    'has_invite_link' => !empty($telegramGroup->invite_link),
                    'group_title' => $telegramGroup->title
                ]);
                
                $groupLink = $this->getLinkFromGroup($telegramGroup, $transaction, $telegramService, 'plano de pagamento', $expireDate);
                if ($groupLink) {
                    Log::info('✅ Link encontrado via grupo do plano de pagamento', [
                        'transaction_id' => $transaction->id,
                        'group_link' => $groupLink,
                        'has_expire_date' => !is_null($expireDate)
                    ]);
                    return $groupLink;
                } else {
                    Log::warning('⚠️ Grupo encontrado mas getLinkFromGroup retornou null', [
                        'transaction_id' => $transaction->id,
                        'group_id' => $telegramGroup->id,
                        'telegram_group_id' => $telegramGroup->telegram_group_id
                    ]);
                }
            } else {
                // Log detalhado sobre por que não encontrou
                $groupsForPlan = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)
                    ->where('payment_plan_id', $paymentPlan->id)
                    ->get();
                
                Log::info('⚠️ Grupo associado ao plano não encontrado', [
                    'transaction_id' => $transaction->id,
                    'payment_plan_id' => $paymentPlan->id,
                    'groups_found_for_plan' => $groupsForPlan->count(),
                    'groups_details' => $groupsForPlan->map(function($g) {
                        return [
                            'id' => $g->id,
                            'title' => $g->title,
                            'active' => $g->active,
                            'telegram_group_id' => $g->telegram_group_id
                        ];
                    })->toArray()
                ]);
            }
        } else {
            Log::warning('⚠️ Transação sem plano de pagamento associado', [
                'transaction_id' => $transaction->id
            ]);
        }
        
        // ESTRATÉGIA 2: Busca qualquer grupo ativo do bot (prioriza grupos com link salvo)
        // Primeiro tenta grupos com link salvo
        $anyGroupWithLink = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)
            ->where('active', true)
            ->whereNotNull('invite_link')
            ->orderBy('updated_at', 'desc')
            ->first();
        
        if ($anyGroupWithLink) {
            Log::info('✅ Grupo ativo do bot com link salvo encontrado', [
                'transaction_id' => $transaction->id,
                'group_id' => $anyGroupWithLink->id,
                'telegram_group_id' => $anyGroupWithLink->telegram_group_id,
                'payment_plan_id' => $anyGroupWithLink->payment_plan_id
            ]);
            
            $groupLink = $this->getLinkFromGroup($anyGroupWithLink, $transaction, $telegramService, 'grupo ativo com link salvo', $expireDate);
            if ($groupLink) {
                Log::info('✅ Link encontrado via grupo ativo com link salvo', [
                    'transaction_id' => $transaction->id,
                    'group_id' => $anyGroupWithLink->id
                ]);
                return $groupLink;
            } else {
                Log::warning('⚠️ Grupo ativo com link salvo encontrado mas getLinkFromGroup retornou null', [
                    'transaction_id' => $transaction->id,
                    'group_id' => $anyGroupWithLink->id,
                    'telegram_group_id' => $anyGroupWithLink->telegram_group_id
                ]);
            }
        } else {
            Log::info('⚠️ Nenhum grupo ativo com link salvo encontrado', [
                'transaction_id' => $transaction->id,
                'bot_id' => $transaction->bot_id
            ]);
        }
        
        // Se não encontrou com link, busca qualquer grupo ativo
        $anyGroup = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)
            ->where('active', true)
            ->whereNotNull('telegram_group_id')
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($anyGroup) {
            Log::info('✅ Grupo ativo do bot encontrado (qualquer grupo)', [
                'transaction_id' => $transaction->id,
                'group_id' => $anyGroup->id,
                'telegram_group_id' => $anyGroup->telegram_group_id,
                'payment_plan_id' => $anyGroup->payment_plan_id
            ]);
            
            $groupLink = $this->getLinkFromGroup($anyGroup, $transaction, $telegramService, 'qualquer grupo ativo do bot', $expireDate);
            if ($groupLink) {
                Log::info('✅ Link encontrado via qualquer grupo ativo do bot', [
                    'transaction_id' => $transaction->id,
                    'group_id' => $anyGroup->id
                ]);
                return $groupLink;
            } else {
                Log::warning('⚠️ Grupo ativo encontrado mas getLinkFromGroup retornou null', [
                    'transaction_id' => $transaction->id,
                    'group_id' => $anyGroup->id,
                    'telegram_group_id' => $anyGroup->telegram_group_id
                ]);
            }
        } else {
            // Última tentativa: busca qualquer grupo (mesmo inativo) do bot
            $anyGroupInactive = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)
                ->whereNotNull('telegram_group_id')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($anyGroupInactive) {
                Log::warning('⚠️ Usando grupo inativo como última tentativa', [
                    'transaction_id' => $transaction->id,
                    'group_id' => $anyGroupInactive->id,
                    'active' => $anyGroupInactive->active
                ]);
                
                $groupLink = $this->getLinkFromGroup($anyGroupInactive, $transaction, $telegramService, 'grupo inativo (última tentativa)', $expireDate);
                if ($groupLink) {
                    return $groupLink;
                }
            }
            
            // Log detalhado sobre grupos disponíveis
            $allGroups = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)->get();
            Log::error('❌ Nenhum grupo encontrado no banco de dados para este bot', [
                'transaction_id' => $transaction->id,
                'bot_id' => $transaction->bot_id,
                'total_groups' => $allGroups->count(),
                'groups_details' => $allGroups->map(function($g) {
                    return [
                        'id' => $g->id,
                        'title' => $g->title,
                        'active' => $g->active,
                        'telegram_group_id' => $g->telegram_group_id,
                        'payment_plan_id' => $g->payment_plan_id,
                        'has_invite_link' => !empty($g->invite_link)
                    ];
                })->toArray()
            ]);
        }
        
        // ESTRATÉGIA 3: Usa o grupo do bot (telegram_group_id do modelo Bot)
        if (!empty($transaction->bot->telegram_group_id)) {
            Log::info('✅ Tentando usar grupo do bot (telegram_group_id)', [
                'transaction_id' => $transaction->id,
                'bot_telegram_group_id' => $transaction->bot->telegram_group_id,
                'has_expire_date' => !is_null($expireDate)
            ]);
            
            $groupLink = $this->getLinkFromTelegramId(
                $transaction->bot->telegram_group_id,
                $transaction,
                $telegramService,
                'grupo do bot',
                $expireDate
            );
            if ($groupLink) {
                return $groupLink;
            }
        }
        
        // ESTRATÉGIA 4: Busca grupos inativos também (última tentativa)
        // CRÍTICO: NÃO usa links salvos - sempre cria link novo para evitar links expirados
        $inactiveGroup = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)
            ->whereNotNull('telegram_group_id')
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($inactiveGroup && $inactiveGroup->telegram_group_id) {
            Log::warning('⚠️ Tentando criar link novo para grupo inativo (última tentativa)', [
                'transaction_id' => $transaction->id,
                'group_id' => $inactiveGroup->id,
                'active' => $inactiveGroup->active
            ]);
            
            // Tenta criar link novo ao invés de usar link salvo
            $groupLink = $this->getLinkFromGroup($inactiveGroup, $transaction, $telegramService, 'grupo inativo (última tentativa)', $expireDate);
            if ($groupLink) {
                return $groupLink;
            }
        }
        
        // CRÍTICO: Garante que o metadata é atualizado mesmo se não encontrou link
        // Isso permite que o sistema saiba que tentou buscar o link
        try {
            $metadata = $transaction->metadata ?? [];
            if ($expireDate) {
                // Se encontrou link mas não retornou (erro interno), salva a data de expiração esperada
                if (!isset($metadata['group_invite_link_expires_at'])) {
                    $metadata['group_invite_link_expires_at'] = $expireDate->toIso8601String();
                    $transaction->update(['metadata' => $metadata]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Erro ao atualizar metadata após busca de link', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
        }
        
        Log::error('❌ FALHA CRÍTICA: Nenhum link de grupo encontrado após todas as estratégias', [
            'transaction_id' => $transaction->id,
            'bot_id' => $transaction->bot_id,
            'payment_plan_id' => $paymentPlan->id ?? null
        ]);
        
        return null;
    }

    /**
     * Obtém o link de um grupo Telegram usando múltiplas estratégias
     *
     * @param \App\Models\TelegramGroup $telegramGroup
     * @param Transaction $transaction
     * @param \App\Services\TelegramService $telegramService
     * @param string $source
     * @return string|null
     */
    protected function getLinkFromGroup(
        \App\Models\TelegramGroup $telegramGroup,
        Transaction $transaction,
        \App\Services\TelegramService $telegramService,
        string $source,
        ?\Carbon\Carbon $expireDate = null
    ): ?string {
        // CRÍTICO: Garante que o bot está carregado
        if (!$transaction->relationLoaded('bot')) {
            $transaction->load('bot');
        }
        
        if (!$transaction->bot || empty($transaction->bot->token)) {
            Log::error("❌ Bot não encontrado ou sem token ({$source})", [
                'transaction_id' => $transaction->id,
                'group_id' => $telegramGroup->id
            ]);
            return null;
        }
        
        // CRÍTICO: Sempre obtém um link FRESCO via API para evitar links expirados
        // Prioriza obter um link novo ao invés de usar links salvos que podem estar expirados
        
        // Estratégia 1: Para grupos com username (@), criar link temporário com expiração
        // Links de username são permanentes, mas podemos criar um link temporário via API
        // que terá a expiração baseada no ciclo do plano
        if ($telegramGroup->telegram_group_id && str_starts_with($telegramGroup->telegram_group_id, '@')) {
            // CRÍTICO: Mesmo para grupos com username, cria um link temporário com expiração
            // Isso garante que o link expira conforme o ciclo do plano
            if ($expireDate) {
                Log::info("🔄 Criando link temporário com expiração para grupo com username ({$source})", [
                    'transaction_id' => $transaction->id,
                    'group_id' => $telegramGroup->id,
                    'telegram_group_id' => $telegramGroup->telegram_group_id,
                    'expire_date' => $expireDate->toDateTimeString()
                ]);
                
                // Tenta criar link temporário via API (mesmo para grupos com username)
                $botInfo = $telegramService->validateToken($transaction->bot->token);
                $botIdForLink = $botInfo['valid'] && isset($botInfo['bot']['id']) ? $botInfo['bot']['id'] : null;
                
                $tempLink = $this->getFreshInviteLink(
                    $telegramService,
                    $transaction->bot->token,
                    $telegramGroup->telegram_group_id,
                    $botIdForLink,
                    $transaction->id,
                    $telegramGroup->id,
                    $source . ' (grupo com username)',
                    $expireDate
                );
                
                if ($tempLink) {
                    Log::info("✅ Link temporário criado para grupo com username ({$source})", [
                        'transaction_id' => $transaction->id,
                        'group_id' => $telegramGroup->id,
                        'invite_link' => $tempLink,
                        'expire_date' => $expireDate->toDateTimeString()
                    ]);
                    return $tempLink;
                } else {
                    Log::warning("⚠️ Não foi possível criar link temporário para grupo com username, usando link permanente", [
                        'transaction_id' => $transaction->id,
                        'group_id' => $telegramGroup->id
                    ]);
                    // Fallback: usa link permanente (mas isso não é ideal)
                    $generatedLink = $telegramGroup->generateInviteLink();
                    if ($generatedLink) {
                        Log::warning("⚠️ Usando link permanente para grupo com username - NÃO TERÁ EXPIRAÇÃO", [
                            'transaction_id' => $transaction->id,
                            'group_id' => $telegramGroup->id
                        ]);
                        return $generatedLink;
                    }
                }
            } else {
                // Se não há data de expiração, usa link permanente (fallback)
                $generatedLink = $telegramGroup->generateInviteLink();
                if ($generatedLink) {
                    Log::warning("⚠️ Link gerado para grupo com username SEM expiração (sem ciclo definido)", [
                        'transaction_id' => $transaction->id,
                        'group_id' => $telegramGroup->id,
                        'invite_link' => $generatedLink
                    ]);
                    return $generatedLink;
                }
            }
        }
        
        // Estratégia 2: Obter link FRESCO via API do Telegram - CRÍTICO: Sempre tenta obter novo link
        if ($telegramGroup->telegram_group_id) {
            try {
                Log::info("🔄 Tentando obter link via API do Telegram ({$source})", [
                    'transaction_id' => $transaction->id,
                    'group_id' => $telegramGroup->id,
                    'telegram_group_id' => $telegramGroup->telegram_group_id,
                    'bot_id' => $transaction->bot_id
                ]);
                
                $botInfo = $telegramService->validateToken($transaction->bot->token);
                $botIdForLink = $botInfo['valid'] && isset($botInfo['bot']['id']) ? $botInfo['bot']['id'] : null;
                
                if (!$botIdForLink) {
                    Log::warning("⚠️ Não foi possível obter bot_id do token ({$source})", [
                        'transaction_id' => $transaction->id,
                        'group_id' => $telegramGroup->id
                    ]);
                }
                
                // CRÍTICO: Usa a data de expiração passada como parâmetro (já calculada baseada no ciclo)
                // Se não foi passada, calcula aqui como fallback
                if (!$expireDate) {
                    if (!$transaction->relationLoaded('paymentCycle')) {
                        $transaction->load('paymentCycle');
                    }
                    if ($transaction->paymentCycle) {
                        $days = $transaction->paymentCycle->days ?? 30;
                        $expireDate = \Carbon\Carbon::now()->addDays($days);
                        Log::info("📅 Data de expiração calculada baseada no ciclo (fallback)", [
                            'transaction_id' => $transaction->id,
                            'cycle_days' => $days,
                            'expire_date' => $expireDate->toDateTimeString()
                        ]);
                    }
                }
                
                // CRÍTICO: Sempre tenta obter um link NOVO via createChatInviteLink primeiro
                // Isso garante que o link não está expirado e tem a data de expiração correta
                $linkResult = $this->getFreshInviteLink(
                    $telegramService,
                    $transaction->bot->token,
                    $telegramGroup->telegram_group_id,
                    $botIdForLink,
                    $transaction->id,
                    $telegramGroup->id,
                    $source,
                    $expireDate
                );
                
                if ($linkResult && !empty($linkResult)) {
                    // Salva o link novo no banco
                    try {
                        $telegramGroup->update(['invite_link' => $linkResult]);
                    } catch (\Exception $e) {
                        Log::warning("⚠️ Erro ao salvar link obtido via API, mas retornando mesmo assim", [
                            'transaction_id' => $transaction->id,
                            'group_id' => $telegramGroup->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                    
                    // CRÍTICO: Salva informações do link no metadata da transação
                    if ($expireDate) {
                        try {
                            $metadata = $transaction->metadata ?? [];
                            $metadata['group_invite_link'] = $linkResult;
                            $metadata['group_invite_link_expires_at'] = $expireDate->toIso8601String();
                            $metadata['group_invite_link_created_at'] = now()->toIso8601String();
                            $transaction->update(['metadata' => $metadata]);
                            
                            Log::info("✅ Informações do link salvas no metadata da transação", [
                                'transaction_id' => $transaction->id,
                                'expires_at' => $expireDate->toDateTimeString()
                            ]);
                        } catch (\Exception $e) {
                            Log::warning("⚠️ Erro ao salvar informações do link no metadata", [
                                'transaction_id' => $transaction->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    
                    Log::info("✅ Link FRESCO obtido via API e salvo no banco ({$source})", [
                        'transaction_id' => $transaction->id,
                        'group_id' => $telegramGroup->id,
                        'invite_link' => $linkResult,
                        'expires_at' => $expireDate ? $expireDate->toDateTimeString() : null
                    ]);
                    return $linkResult;
                } else {
                    Log::warning("⚠️ Falha ao obter link fresco via API ({$source})", [
                        'transaction_id' => $transaction->id,
                        'group_id' => $telegramGroup->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("❌ Exceção ao obter link via API ({$source})", [
                    'transaction_id' => $transaction->id,
                    'group_id' => $telegramGroup->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            Log::warning("⚠️ Grupo sem telegram_group_id ({$source})", [
                'transaction_id' => $transaction->id,
                'group_id' => $telegramGroup->id
            ]);
        }
        
        // NUNCA usa link salvo no banco - sempre obtém link novo para evitar links expirados
        Log::warning("⚠️ Não foi possível obter link novo via API ({$source})", [
            'transaction_id' => $transaction->id,
            'group_id' => $telegramGroup->id
        ]);
        
        return null;
    }

    /**
     * Obtém um link FRESCO de convite via API do Telegram
     * Prioriza createChatInviteLink (cria novo link) ao invés de exportChatInviteLink (pode retornar link expirado)
     *
     * @param \App\Services\TelegramService $telegramService
     * @param string $token
     * @param string $chatId
     * @param int|null $botId
     * @param int $transactionId
     * @param int $groupId
     * @param string $source
     * @return string|null
     */
    protected function getFreshInviteLink(
        \App\Services\TelegramService $telegramService,
        string $token,
        string $chatId,
        ?int $botId,
        int $transactionId,
        int $groupId,
        string $source,
        ?\Carbon\Carbon $expireDate = null
    ): ?string {
        try {
            // CRÍTICO: Estratégia 1 - SEMPRE tenta criar um link NOVO via createChatInviteLink
            // Isso garante que o link não está expirado e é válido
            // Se expireDate for fornecido, usa essa data; caso contrário, cria link sem expiração
            try {
                $requestData = [
                    'chat_id' => $chatId,
                    'creates_join_request' => false,
                    'name' => 'Link automático - ' . now()->format('Y-m-d H:i:s'),
                    'member_limit' => null // Sem limite de membros
                ];
                
                // CRÍTICO: Se expireDate foi fornecido, adiciona ao request
                if ($expireDate) {
                    $requestData['expire_date'] = $expireDate->timestamp;
                    Log::info("📅 Criando link com data de expiração", [
                        'transaction_id' => $transactionId,
                        'expire_date' => $expireDate->toDateTimeString(),
                        'expire_timestamp' => $expireDate->timestamp
                    ]);
                } else {
                    $requestData['expire_date'] = null; // Sem data de expiração
                }
                
                $response = \Illuminate\Support\Facades\Http::timeout(30)
                    ->retry(2, 1000)
                    ->post("https://api.telegram.org/bot{$token}/createChatInviteLink", $requestData);

                $responseData = $response->json() ?? [];
                
                if ($response->successful() && ($responseData['ok'] ?? false) && isset($responseData['result']['invite_link'])) {
                    $freshLink = $responseData['result']['invite_link'];
                    
                    // Valida o link antes de retornar
                    if ($this->validateInviteLink($freshLink, $token, $chatId)) {
                        Log::info("✅ Link FRESCO criado e VALIDADO via createChatInviteLink ({$source})", [
                            'transaction_id' => $transactionId,
                            'group_id' => $groupId,
                            'invite_link' => $freshLink
                        ]);
                        return $freshLink;
                    } else {
                        Log::warning("⚠️ Link criado mas falhou na validação, tentando novamente", [
                            'transaction_id' => $transactionId,
                            'group_id' => $groupId
                        ]);
                    }
                } else {
                    $errorMsg = $responseData['description'] ?? 'Erro desconhecido';
                    $errorCode = $responseData['error_code'] ?? null;
                    Log::warning("⚠️ Falha ao criar link novo via createChatInviteLink, tentando exportChatInviteLink", [
                        'transaction_id' => $transactionId,
                        'group_id' => $groupId,
                        'error' => $errorMsg,
                        'error_code' => $errorCode
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning("⚠️ Exceção ao criar link novo via createChatInviteLink, tentando exportChatInviteLink", [
                    'transaction_id' => $transactionId,
                    'group_id' => $groupId,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Estratégia 2: Se não conseguiu criar novo, tenta obter link existente via exportChatInviteLink
            // Mas ainda valida antes de retornar
            try {
                $linkResult = $telegramService->getChatInviteLink($token, $chatId, $botId);
                
                if ($linkResult['success'] && !empty($linkResult['invite_link'])) {
                    $link = $linkResult['invite_link'];
                    
                    // Valida o link antes de retornar
                    if ($this->validateInviteLink($link, $token, $chatId)) {
                        Log::info("✅ Link obtido e VALIDADO via exportChatInviteLink ({$source})", [
                            'transaction_id' => $transactionId,
                            'group_id' => $groupId,
                            'invite_link' => $link,
                            'method' => $linkResult['details']['method'] ?? 'unknown'
                        ]);
                        return $link;
                    } else {
                        Log::warning("⚠️ Link obtido via exportChatInviteLink mas falhou na validação", [
                            'transaction_id' => $transactionId,
                            'group_id' => $groupId
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error("❌ Erro ao obter link via exportChatInviteLink", [
                    'transaction_id' => $transactionId,
                    'group_id' => $groupId,
                    'error' => $e->getMessage()
                ]);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("❌ Exceção ao obter link fresco", [
                'transaction_id' => $transactionId,
                'group_id' => $groupId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Valida se um link de convite está válido e não expirado
     * Tenta obter informações do link via API do Telegram
     *
     * @param string $inviteLink
     * @param string $token
     * @param string $chatId
     * @return bool
     */
    protected function validateInviteLink(string $inviteLink, string $token, string $chatId): bool
    {
        try {
            // Extrai o invite_hash do link
            // Formato: https://t.me/joinchat/INVITE_HASH ou https://t.me/+INVITE_HASH
            $inviteHash = null;
            
            if (preg_match('/joinchat\/([a-zA-Z0-9_-]+)/', $inviteLink, $matches)) {
                $inviteHash = $matches[1];
            } elseif (preg_match('/\+([a-zA-Z0-9_-]+)/', $inviteLink, $matches)) {
                $inviteHash = $matches[1];
            }
            
            if (!$inviteHash) {
                // Se não tem hash, pode ser um link de username (@grupo) que sempre é válido
                if (str_contains($inviteLink, 't.me/') && !str_contains($inviteLink, 'joinchat') && !str_contains($inviteLink, '+')) {
                    return true; // Links de username são sempre válidos
                }
                Log::warning('Não foi possível extrair invite_hash do link', [
                    'invite_link' => $inviteLink
                ]);
                return false;
            }
            
            // Tenta obter informações do link via getChatInviteLink
            // Se conseguir, o link é válido
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(10)
                    ->get("https://api.telegram.org/bot{$token}/getChatInviteLink", [
                        'chat_id' => $chatId,
                        'invite_link' => $inviteLink
                    ]);
                
                $responseData = $response->json() ?? [];
                
                if ($response->successful() && ($responseData['ok'] ?? false)) {
                    $inviteLinkInfo = $responseData['result'] ?? [];
                    
                    // Verifica se o link está expirado
                    if (isset($inviteLinkInfo['expire_date']) && $inviteLinkInfo['expire_date']) {
                        $expireDate = \Carbon\Carbon::createFromTimestamp($inviteLinkInfo['expire_date']);
                        if (now()->greaterThan($expireDate)) {
                            Log::warning('Link está expirado', [
                                'invite_link' => $inviteLink,
                                'expire_date' => $expireDate->toDateTimeString()
                            ]);
                            return false;
                        }
                    }
                    
                    // Verifica se atingiu o limite de membros
                    if (isset($inviteLinkInfo['member_limit']) && $inviteLinkInfo['member_limit']) {
                        $memberCount = $inviteLinkInfo['member_count'] ?? 0;
                        if ($memberCount >= $inviteLinkInfo['member_limit']) {
                            Log::warning('Link atingiu limite de membros', [
                                'invite_link' => $inviteLink,
                                'member_count' => $memberCount,
                                'member_limit' => $inviteLinkInfo['member_limit']
                            ]);
                            return false;
                        }
                    }
                    
                    // Se chegou aqui, o link é válido
                    Log::info('Link validado com sucesso', [
                        'invite_link' => $inviteLink,
                        'is_creates_join_request' => $inviteLinkInfo['creates_join_request'] ?? false,
                        'is_primary' => $inviteLinkInfo['is_primary'] ?? false
                    ]);
                    return true;
                } else {
                    $errorMsg = $responseData['description'] ?? 'Erro desconhecido';
                    Log::warning('Falha ao validar link - pode estar expirado ou inválido', [
                        'invite_link' => $inviteLink,
                        'error' => $errorMsg
                    ]);
                    return false;
                }
            } catch (\Exception $e) {
                Log::warning('Exceção ao validar link, assumindo que é válido', [
                    'invite_link' => $inviteLink,
                    'error' => $e->getMessage()
                ]);
                // Em caso de erro na validação, assume que o link é válido
                // (melhor enviar um link que pode estar válido do que não enviar nada)
                return true;
            }
        } catch (\Exception $e) {
            Log::error('Erro ao validar link de convite', [
                'invite_link' => $inviteLink,
                'error' => $e->getMessage()
            ]);
            // Em caso de erro, assume que o link é válido
            return true;
        }
    }

    /**
     * Obtém o link diretamente de um telegram_group_id (sem modelo TelegramGroup)
     *
     * @param string $telegramGroupId
     * @param Transaction $transaction
     * @param \App\Services\TelegramService $telegramService
     * @param string $source
     * @return string|null
     */
    protected function getLinkFromTelegramId(
        string $telegramGroupId,
        Transaction $transaction,
        \App\Services\TelegramService $telegramService,
        string $source,
        ?\Carbon\Carbon $expireDate = null
    ): ?string {
        try {
            // CRÍTICO: Para grupos com username (@), criar link temporário com expiração
            // Links de username são permanentes, mas podemos criar um link temporário via API
            // que terá a expiração baseada no ciclo do plano
            if (str_starts_with($telegramGroupId, '@')) {
                if ($expireDate) {
                    Log::info("🔄 Criando link temporário com expiração para grupo com username ({$source})", [
                        'transaction_id' => $transaction->id,
                        'telegram_group_id' => $telegramGroupId,
                        'expire_date' => $expireDate->toDateTimeString()
                    ]);
                    
                    // Tenta criar link temporário via API (mesmo para grupos com username)
                    $botInfo = $telegramService->validateToken($transaction->bot->token);
                    $botIdForLink = $botInfo['valid'] && isset($botInfo['bot']['id']) ? $botInfo['bot']['id'] : null;
                    
                    $tempLink = $this->getFreshInviteLink(
                        $telegramService,
                        $transaction->bot->token,
                        $telegramGroupId,
                        $botIdForLink,
                        $transaction->id,
                        0,
                        $source . ' (grupo com username)',
                        $expireDate
                    );
                    
                    if ($tempLink) {
                        Log::info("✅ Link temporário criado para grupo com username ({$source})", [
                            'transaction_id' => $transaction->id,
                            'telegram_group_id' => $telegramGroupId,
                            'invite_link' => $tempLink,
                            'expire_date' => $expireDate->toDateTimeString()
                        ]);
                        return $tempLink;
                    } else {
                        Log::warning("⚠️ Não foi possível criar link temporário para grupo com username, usando link permanente", [
                            'transaction_id' => $transaction->id,
                            'telegram_group_id' => $telegramGroupId
                        ]);
                        // Fallback: usa link permanente (mas isso não é ideal)
                        $link = 'https://t.me/' . ltrim($telegramGroupId, '@');
                        Log::warning("⚠️ Usando link permanente para grupo com username - NÃO TERÁ EXPIRAÇÃO", [
                            'transaction_id' => $transaction->id,
                            'telegram_group_id' => $telegramGroupId
                        ]);
                        return $link;
                    }
                } else {
                    // Se não há data de expiração, usa link permanente (fallback)
                    $link = 'https://t.me/' . ltrim($telegramGroupId, '@');
                    Log::warning("⚠️ Link gerado para grupo com username SEM expiração (sem ciclo definido)", [
                        'transaction_id' => $transaction->id,
                        'telegram_group_id' => $telegramGroupId,
                        'invite_link' => $link
                    ]);
                    return $link;
                }
            }
            
            // Para grupos sem username, usa getFreshInviteLink para criar link com expiração
            Log::info("🔄 Tentando obter link via API do Telegram ({$source})", [
                'transaction_id' => $transaction->id,
                'telegram_group_id' => $telegramGroupId,
                'has_expire_date' => !is_null($expireDate)
            ]);
            
            $botInfo = $telegramService->validateToken($transaction->bot->token);
            $botIdForLink = $botInfo['valid'] && isset($botInfo['bot']['id']) ? $botInfo['bot']['id'] : null;
            
            // Usa getFreshInviteLink para criar link novo com expiração
            $link = $this->getFreshInviteLink(
                $telegramService,
                $transaction->bot->token,
                $telegramGroupId,
                $botIdForLink,
                $transaction->id,
                0, // Não temos group_id aqui, usa 0
                $source,
                $expireDate
            );
            
            if ($link) {
                // Salva informações do link no metadata da transação
                if ($expireDate) {
                    try {
                        $metadata = $transaction->metadata ?? [];
                        $metadata['group_invite_link'] = $link;
                        $metadata['group_invite_link_expires_at'] = $expireDate->toIso8601String();
                        $metadata['group_invite_link_created_at'] = now()->toIso8601String();
                        $transaction->update(['metadata' => $metadata]);
                    } catch (\Exception $e) {
                        Log::warning("⚠️ Erro ao salvar informações do link no metadata", [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                // Tenta salvar no banco se encontrar o grupo
                $telegramGroup = \App\Models\TelegramGroup::where('bot_id', $transaction->bot_id)
                    ->where('telegram_group_id', $telegramGroupId)
                    ->first();
                if ($telegramGroup) {
                    $telegramGroup->update(['invite_link' => $link]);
                }
                
                return $link;
            } else {
                Log::warning("⚠️ Falha ao obter link via API ({$source})", [
                    'transaction_id' => $transaction->id,
                    'telegram_group_id' => $telegramGroupId
                ]);
            }
        } catch (\Exception $e) {
            Log::error("❌ Exceção ao obter link ({$source})", [
                'transaction_id' => $transaction->id,
                'telegram_group_id' => $telegramGroupId,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
}

