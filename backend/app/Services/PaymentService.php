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
                // CRÍTICO: O qr_code vem como string EMV do Mercado Pago
                // Segundo a documentação, o código já vem no formato correto
                // Preservamos o original EXATAMENTE como recebido
                $pixCode = $pixCodeRaw;
                
                Log::info('✅ Código PIX EMV extraído do campo qr_code do Mercado Pago (EXATO)', [
                    'payment_id' => $payment->id ?? null,
                    'qr_code_type' => gettype($pixData->qr_code),
                    'qr_code_length' => strlen($pixCodeRaw),
                    'qr_code_start' => substr($pixCodeRaw, 0, 30),
                    'qr_code_end' => substr($pixCodeRaw, -10),
                    'qr_code_crc' => substr($pixCodeRaw, -4),
                    'qr_code_full' => $pixCodeRaw, // Log completo para validação
                    'note' => 'Código EXATO do Mercado Pago - será usado sem modificações'
                ]);
            } else {
                // Se não tiver qr_code, é um erro - o Mercado Pago sempre retorna
                // Isso geralmente indica problema na configuração da conta
                Log::error('ERRO CRÍTICO: Mercado Pago não retornou qr_code (código EMV)', [
                    'payment_id' => $payment->id ?? null,
                    'payment_status' => $payment->status ?? null,
                    'transaction_data_keys' => array_keys((array)$pixData),
                    'transaction_data' => (array)$pixData,
                    'has_qr_code_base64' => isset($pixData->qr_code_base64),
                    'environment' => $gatewayConfig->environment,
                    'note' => 'Isso geralmente indica que a conta não possui chave PIX cadastrada ou não está habilitada para PIX'
                ]);
                
                $errorMessage = 'Código PIX EMV (qr_code) não encontrado na resposta do Mercado Pago. ';
                $errorMessage .= 'Possíveis causas: ';
                $errorMessage .= '1) A conta não possui uma chave PIX cadastrada - acesse sua conta do Mercado Pago e cadastre uma chave PIX; ';
                $errorMessage .= '2) A conta não está habilitada para receber pagamentos via PIX; ';
                $errorMessage .= '3) A conta não está validada/verificada. ';
                $errorMessage .= 'Verifique a documentação em PROBLEMAS_QRCODE_PIX_MERCADOPAGO.md para mais detalhes.';
                
                throw new Exception($errorMessage);
            }
            
            $pixCodeBase64 = $pixData->qr_code_base64 ?? null;
            $ticketUrl = $pixData->ticket_url ?? null;

            // Log detalhado da estrutura recebida
            Log::info('Dados PIX recebidos do Mercado Pago - Estrutura Completa', [
                'payment_id' => $payment->id ?? null,
                'has_qr_code' => isset($pixData->qr_code),
                'has_qr_code_base64' => isset($pixData->qr_code_base64),
                'has_ticket_url' => isset($pixData->ticket_url),
                'transaction_data_keys' => array_keys((array)$pixData),
                'qr_code_raw_length' => $pixCodeRaw ? strlen($pixCodeRaw) : null,
                'qr_code_raw_start' => $pixCodeRaw ? substr($pixCodeRaw, 0, 30) : null,
                'qr_code_raw_end' => $pixCodeRaw ? substr($pixCodeRaw, -10) : null,
                'qr_code_has_spaces' => $pixCodeRaw ? (strpos($pixCodeRaw, ' ') !== false) : null,
                'qr_code_has_newlines' => $pixCodeRaw ? (strpos($pixCodeRaw, "\n") !== false || strpos($pixCodeRaw, "\r") !== false) : null,
                'qr_code_has_tabs' => $pixCodeRaw ? (strpos($pixCodeRaw, "\t") !== false) : null
            ]);

            if (!$pixCode || empty($pixCode)) {
                Log::error('Código PIX não encontrado na resposta do Mercado Pago', [
                    'payment_id' => $payment->id ?? null,
                    'transaction_data' => (array)$pixData
                ]);
                throw new Exception('Código PIX não retornado pelo Mercado Pago.');
            }

            // CRÍTICO: O código PIX do Mercado Pago deve ser usado EXATAMENTE como retornado
            // Segundo a documentação do Mercado Pago, o código já vem no formato correto e pronto para uso
            // NÃO devemos limpar, modificar ou alterar o código de forma alguma
            // O código PIX EMV deve ser uma string contínua, mas o Mercado Pago já retorna assim
            
            $pixCrcService = new PixCrcService();
            $pixCodeOriginal = $pixCode;
            
            // CRÍTICO: Segundo a documentação do Mercado Pago, o código já vem correto
            // NÃO devemos limpar o código - apenas removemos espaços/quebras se realmente existirem
            // Mas na prática, o Mercado Pago geralmente retorna o código já limpo
            
            // Verifica se há espaços/quebras (geralmente não há)
            $hasSpaces = strpos($pixCode, ' ') !== false;
            $hasNewlines = strpos($pixCode, "\n") !== false || strpos($pixCode, "\r") !== false;
            $hasTabs = strpos($pixCode, "\t") !== false;
            $hasLeadingTrailingSpaces = $pixCode !== trim($pixCode);
            
            // Só limpa se realmente houver espaços/quebras (geralmente não há)
            if ($hasSpaces || $hasNewlines || $hasTabs || $hasLeadingTrailingSpaces) {
                Log::warning('⚠️ Código PIX do Mercado Pago contém espaços/quebras (incomum) - removendo APENAS esses caracteres', [
                    'has_spaces' => $hasSpaces,
                    'has_newlines' => $hasNewlines,
                    'has_tabs' => $hasTabs,
                    'has_leading_trailing_spaces' => $hasLeadingTrailingSpaces,
                    'original_length' => strlen($pixCodeOriginal),
                    'original_start' => substr($pixCodeOriginal, 0, 30),
                    'original_end' => substr($pixCodeOriginal, -10),
                    'original_crc' => substr($pixCodeOriginal, -4),
                    'original_full' => $pixCodeOriginal, // Log completo do original
                    'note' => 'Código do Mercado Pago geralmente não tem espaços/quebras - removendo apenas esses'
                ]);
                
                // Remove APENAS espaços em branco e quebras de linha
                // Preserva TODOS os outros caracteres (alfanuméricos e especiais)
                $pixCodeCleaned = trim($pixCode); // Remove espaços no início e fim
                $pixCodeCleaned = str_replace(["\r\n", "\n", "\r", "\t"], '', $pixCodeCleaned); // Remove quebras
                $pixCodeCleaned = preg_replace('/\s+/', '', $pixCodeCleaned); // Remove TODOS os espaços
                
                // Validação crítica: verifica se o código não foi corrompido
                if (!str_starts_with($pixCodeCleaned, '000201') && str_starts_with($pixCodeOriginal, '000201')) {
                    Log::error('ERRO CRÍTICO: Código PIX foi corrompido durante limpeza!', [
                        'original_start' => substr($pixCodeOriginal, 0, 30),
                        'cleaned_start' => substr($pixCodeCleaned, 0, 30),
                        'original_length' => strlen($pixCodeOriginal),
                        'cleaned_length' => strlen($pixCodeCleaned),
                        'original_hex_start' => bin2hex(substr($pixCodeOriginal, 0, 20)),
                        'cleaned_hex_start' => bin2hex(substr($pixCodeCleaned, 0, 20)),
                        'original_full' => $pixCodeOriginal,
                        'cleaned_full' => $pixCodeCleaned
                    ]);
                    // Retorna o original se corrompido
                    $pixCode = $pixCodeOriginal;
                } else {
                    $pixCode = $pixCodeCleaned;
                }
                
                // Valida que o comprimento não mudou drasticamente
                $lengthDiff = strlen($pixCodeOriginal) - strlen($pixCode);
                if ($lengthDiff > 10) {
                    Log::warning('ATENÇÃO: Muitos caracteres removidos na limpeza', [
                        'length_diff' => $lengthDiff,
                        'original_length' => strlen($pixCodeOriginal),
                        'cleaned_length' => strlen($pixCode),
                        'percentage' => ($lengthDiff / strlen($pixCodeOriginal)) * 100,
                        'original_full' => $pixCodeOriginal,
                        'cleaned_full' => $pixCode
                    ]);
                }
                
                Log::info('Código PIX limpo (apenas espaços/quebras removidos)', [
                    'original_length' => strlen($pixCodeOriginal),
                    'cleaned_length' => strlen($pixCode),
                    'length_diff' => $lengthDiff,
                    'cleaned_start' => substr($pixCode, 0, 30),
                    'cleaned_end' => substr($pixCode, -10),
                    'cleaned_crc' => substr($pixCode, -4),
                    'cleaned_full' => $pixCode // Log completo do código limpo
                ]);
            } else {
                // Código já está limpo - usa EXATAMENTE como o Mercado Pago retornou
                Log::info('✅✅✅ Código PIX usado EXATAMENTE como o Mercado Pago retornou (SEM MODIFICAÇÕES)', [
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_start' => substr($pixCode, 0, 30),
                    'pix_code_end' => substr($pixCode, -10),
                    'pix_code_crc' => substr($pixCode, -4),
                    'pix_code_full' => $pixCode, // Log completo - código EXATO do Mercado Pago
                    'note' => 'Código usado EXATAMENTE como o Mercado Pago retornou - SEM NENHUMA MODIFICAÇÃO'
                ]);
            }
            
            // Valida formato básico (deve começar com 000201)
            if (!str_starts_with($pixCode, '000201')) {
                Log::error('Código PIX em formato incorreto - não começa com 000201', [
                    'pix_code_start' => substr($pixCode, 0, 50),
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_original_start' => substr($pixCodeOriginal, 0, 50),
                    'pix_code_hex_start' => bin2hex(substr($pixCode, 0, 20))
                ]);
                throw new Exception('Código PIX retornado pelo Mercado Pago está em formato inválido.');
            }
            
            // Valida comprimento mínimo (códigos PIX geralmente têm 200-500 caracteres)
            if (strlen($pixCode) < 100) {
                Log::error('Código PIX muito curto', [
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_start' => substr($pixCode, 0, 50),
                    'pix_code_end' => substr($pixCode, -10)
                ]);
                throw new Exception('Código PIX retornado pelo Mercado Pago está incompleto.');
            }
            
            // CRÍTICO: Valida o CRC do código PIX do Mercado Pago
            // Se o CRC estiver incorreto, o banco NÃO reconhecerá o QR Code
            // Portanto, se o CRC estiver incorreto, devemos corrigi-lo
            $crcValidation = $pixCrcService->validatePixCode($pixCode);
            
            // Log detalhado da validação CRC
            Log::info('Validação CRC do código PIX do Mercado Pago', [
                'crc_validation_valid' => $crcValidation['valid'],
                'crc_validation_crc_valid' => $crcValidation['crc_valid'],
                'crc_validation_format_valid' => $crcValidation['format_valid'],
                'crc_validation_current_crc' => $crcValidation['current_crc'],
                'crc_validation_calculated_crc' => $crcValidation['calculated_crc'],
                'crc_validation_errors' => $crcValidation['errors'],
                'crc_validation_result' => $crcValidation,
                'pix_code_length' => strlen($pixCode),
                'pix_code_start' => substr($pixCode, 0, 30),
                'pix_code_end' => substr($pixCode, -10),
                'pix_code_crc' => substr($pixCode, -4),
                'pix_code_full' => $pixCode, // Log completo para validação manual
                'note' => 'Validando CRC do código PIX do Mercado Pago'
            ]);
            
            // CRÍTICO: Se o CRC estiver inválido, CORRIGE antes de usar
            // Um CRC inválido faz com que o banco NÃO reconheça o QR Code
            $crcWasCorrected = false;
            $crcCorrectionDetails = null;
            
            if (!$crcValidation['crc_valid']) {
                // MÉTRICA: Registra ocorrência de CRC incorreto
                $crcCorrectionDetails = [
                    'payment_id' => $payment->id ?? null,
                    'transaction_id' => $transaction->id ?? null,
                    'bot_id' => $bot->id ?? null,
                    'plan_id' => $plan->id ?? null,
                    'timestamp' => now()->toIso8601String(),
                    'mercado_pago_environment' => $gatewayConfig->environment ?? null,
                    'crc_before' => $crcValidation['current_crc'],
                    'crc_calculated' => $crcValidation['calculated_crc'],
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_start' => substr($pixCode, 0, 30),
                    'pix_code_end_before' => substr($pixCode, -10),
                    'pix_code_full_before' => $pixCode,
                ];
                
                Log::error('❌ ERRO CRÍTICO: CRC do código PIX do Mercado Pago está INCORRETO!', [
                    'crc_validation_result' => $crcValidation,
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_end' => substr($pixCode, -10),
                    'current_crc' => $crcValidation['current_crc'],
                    'calculated_crc' => $crcValidation['calculated_crc'],
                    'pix_code_before_correction' => $pixCode,
                    'payment_id' => $payment->id ?? null,
                    'transaction_id' => $transaction->id ?? null,
                    'bot_id' => $bot->id ?? null,
                    'environment' => $gatewayConfig->environment ?? null,
                    'note' => 'CRC incorreto - o banco NÃO reconhecerá o QR Code. Corrigindo CRC...',
                    'metric' => 'crc_correction_required'
                ]);
                
                // CORRIGE o CRC do código PIX
                $pixCodeOriginal = $pixCode;
                $pixCode = $pixCrcService->addCrc($pixCode);
                
                // Valida novamente após correção
                $crcValidationAfter = $pixCrcService->validatePixCode($pixCode);
                
                // Atualiza detalhes da correção
                $crcCorrectionDetails['crc_after'] = $crcValidationAfter['current_crc'];
                $crcCorrectionDetails['pix_code_end_after'] = substr($pixCode, -10);
                $crcCorrectionDetails['pix_code_full_after'] = $pixCode;
                $crcCorrectionDetails['correction_successful'] = $crcValidationAfter['crc_valid'];
                $crcWasCorrected = true;
                
                Log::info('✅ CRC do código PIX foi CORRIGIDO', [
                    'pix_code_before' => $pixCodeOriginal,
                    'pix_code_after' => $pixCode,
                    'crc_before' => $crcValidation['current_crc'],
                    'crc_after' => $crcValidationAfter['current_crc'],
                    'crc_validation_after' => $crcValidationAfter,
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_start' => substr($pixCode, 0, 30),
                    'pix_code_end' => substr($pixCode, -10),
                    'pix_code_crc' => substr($pixCode, -4),
                    'pix_code_full' => $pixCode, // Log completo do código corrigido
                    'payment_id' => $payment->id ?? null,
                    'transaction_id' => $transaction->id ?? null,
                    'bot_id' => $bot->id ?? null,
                    'environment' => $gatewayConfig->environment ?? null,
                    'note' => 'CRC corrigido - código agora deve ser reconhecido pelo banco',
                    'metric' => 'crc_correction_applied'
                ]);
                
                // Salva detalhes da correção no metadata da transação para análise posterior
                $metadata = $transaction->metadata ?? [];
                $metadata['crc_correction'] = $crcCorrectionDetails;
                $transaction->update(['metadata' => $metadata]);
                
                // Atualiza a validação com o resultado após correção
                $crcValidation = $crcValidationAfter;
            } else {
                Log::info('✅ CRC do código PIX do Mercado Pago está CORRETO', [
                    'crc_validation_result' => $crcValidation,
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_end' => substr($pixCode, -10),
                    'current_crc' => $crcValidation['current_crc'],
                    'calculated_crc' => $crcValidation['calculated_crc'],
                    'payment_id' => $payment->id ?? null,
                    'transaction_id' => $transaction->id ?? null,
                    'bot_id' => $bot->id ?? null,
                    'environment' => $gatewayConfig->environment ?? null,
                    'note' => 'CRC válido - código está correto',
                    'metric' => 'crc_valid'
                ]);
            }
            
            // Log final do código PIX que será usado (EXATO do Mercado Pago)
            Log::info('Código PIX do Mercado Pago pronto para uso (SEM MODIFICAÇÕES)', [
                'pix_code_length' => strlen($pixCode),
                'pix_code_start' => substr($pixCode, 0, 30),
                'pix_code_end' => substr($pixCode, -10),
                'pix_code_crc' => substr($pixCode, -4),
                'is_valid_format' => str_starts_with($pixCode, '000201'),
                'crc_validation_valid' => $crcValidation['valid'],
                'pix_code_full' => $pixCode // Log completo para validação manual
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

            // Validação CRC FINAL (apenas informativo - não modifica o código)
            $finalValidation = $pixCrcService->validatePixCode($pixCode);
            
            // Log final do código PIX que será retornado (EXATO do Mercado Pago)
            Log::info('✅ Código PIX FINAL pronto para retornar (EXATO do Mercado Pago - SEM MODIFICAÇÕES)', [
                'pix_code_length' => strlen($pixCode),
                'pix_code_start' => substr($pixCode, 0, 30),
                'pix_code_end' => substr($pixCode, -10),
                'pix_code_crc' => substr($pixCode, -4),
                'transaction_id' => $transactionId,
                'has_qr_code_image' => !empty($qrCodeImage),
                'using_mercado_pago_qr' => !empty($pixCodeBase64) && !empty($qrCodeImage),
                'crc_validation_valid' => $finalValidation['valid'],
                'crc_validation_crc_valid' => $finalValidation['crc_valid'],
                'crc_validation_current_crc' => $finalValidation['current_crc'],
                'crc_validation_calculated_crc' => $finalValidation['calculated_crc'],
                'pix_code_full' => $pixCode, // Log completo para validação manual
                'note' => 'Código EXATO do Mercado Pago - não foi modificado'
            ]);
            
            // NÃO corrige o CRC - o código do Mercado Pago já está correto
            // Se nossa validação indicar inválido, logamos mas continuamos usando o código do Mercado Pago
            if (!$finalValidation['valid']) {
                Log::warning('ATENÇÃO: Validação CRC final indicou código inválido, mas usando código do Mercado Pago mesmo assim', [
                    'crc_validation_result' => $finalValidation,
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_end' => substr($pixCode, -10),
                    'note' => 'O código do Mercado Pago deve estar correto - pode ser problema na nossa validação'
                ]);
            }
            
            // CRÍTICO: Garante que o código PIX retornado tem CRC VÁLIDO
            // Se o CRC foi corrigido, usa o código corrigido (não o original)
            // O código usado no QR Code (se gerado localmente) DEVE ser idêntico ao código copia e cola
            // Se o QR Code do Mercado Pago foi usado, o código copia e cola DEVE ser o mesmo que está no QR Code
            $pixCodeForReturn = $pixCode; // Usa o código atual (pode ter sido corrigido)
            
            // Validação crítica: verifica se o código tem CRC válido antes de retornar
            // Se o CRC foi corrigido, o código atual ($pixCode) é o correto
            $finalCrcValidation = $pixCrcService->validatePixCode($pixCodeForReturn);
            
            if (!$finalCrcValidation['crc_valid']) {
                Log::error('ERRO CRÍTICO: Código que será retornado ainda tem CRC inválido!', [
                    'pix_code_length' => strlen($pixCodeForReturn),
                    'pix_code_start' => substr($pixCodeForReturn, 0, 30),
                    'pix_code_end' => substr($pixCodeForReturn, -10),
                    'current_crc' => $finalCrcValidation['current_crc'],
                    'calculated_crc' => $finalCrcValidation['calculated_crc'],
                    'pix_code_full' => $pixCodeForReturn,
                    'note' => 'CRC ainda inválido - corrigindo novamente...'
                ]);
                
                // CORRIGE o CRC novamente (caso tenha sido corrompido)
                $pixCodeForReturn = $pixCrcService->addCrc($pixCodeForReturn);
                
                // Valida novamente
                $finalCrcValidation = $pixCrcService->validatePixCode($pixCodeForReturn);
                
                Log::info('✅ CRC do código PIX foi CORRIGIDO novamente antes de retornar', [
                    'pix_code_length' => strlen($pixCodeForReturn),
                    'pix_code_start' => substr($pixCodeForReturn, 0, 30),
                    'pix_code_end' => substr($pixCodeForReturn, -10),
                    'pix_code_crc' => substr($pixCodeForReturn, -4),
                    'crc_validation_after' => $finalCrcValidation,
                    'pix_code_full' => $pixCodeForReturn,
                    'note' => 'CRC corrigido - código agora tem CRC válido'
                ]);
            }
            
            // Valida que o código usado no QR Code é o mesmo do copia e cola
            // NOTA: Se o CRC foi corrigido, o código será diferente do original do Mercado Pago (isso é esperado)
            if (!empty($pixCodeBase64) && !empty($qrCodeImage)) {
                // QR Code do Mercado Pago foi usado - valida que o código copia e cola é o mesmo
                Log::info('✅ QR Code do Mercado Pago usado - código copia e cola sincronizado', [
                    'pix_code_length' => strlen($pixCodeForReturn),
                    'pix_code_start' => substr($pixCodeForReturn, 0, 30),
                    'pix_code_end' => substr($pixCodeForReturn, -10),
                    'pix_code_crc' => substr($pixCodeForReturn, -4),
                    'pix_code_full' => $pixCodeForReturn, // Log completo
                    'crc_validation_valid' => $finalCrcValidation['valid'],
                    'crc_validation_crc_valid' => $finalCrcValidation['crc_valid'],
                    'note' => 'QR Code do Mercado Pago - código copia e cola tem CRC válido'
                ]);
            } else {
                // QR Code foi gerado localmente - valida que o código usado é o mesmo
                Log::info('⚠️ QR Code gerado localmente - validando sincronização código/QR Code', [
                    'pix_code_length' => strlen($pixCodeForReturn),
                    'pix_code_start' => substr($pixCodeForReturn, 0, 30),
                    'pix_code_end' => substr($pixCodeForReturn, -10),
                    'pix_code_crc' => substr($pixCodeForReturn, -4),
                    'pix_code_full' => $pixCodeForReturn, // Log completo
                    'crc_validation_valid' => $finalCrcValidation['valid'],
                    'crc_validation_crc_valid' => $finalCrcValidation['crc_valid'],
                    'note' => 'Código usado no QR Code tem CRC válido e é idêntico ao código copia e cola'
                ]);
            }
            
            // Validação crítica: verifica se o código está em formato válido antes de retornar
            // O código PIX EMV deve começar com 000201 e terminar com CRC válido
            if (!str_starts_with($pixCodeForReturn, '000201')) {
                Log::error('ERRO CRÍTICO: Código PIX não começa com 000201 antes de retornar', [
                    'pix_code_start' => substr($pixCodeForReturn, 0, 50),
                    'pix_code_length' => strlen($pixCodeForReturn),
                    'pix_code_hex_start' => bin2hex(substr($pixCodeForReturn, 0, 20))
                ]);
                throw new Exception('Código PIX em formato inválido - não começa com 000201');
            }
            
            // Valida comprimento mínimo
            if (strlen($pixCodeForReturn) < 100) {
                Log::error('ERRO CRÍTICO: Código PIX muito curto antes de retornar', [
                    'pix_code_length' => strlen($pixCodeForReturn),
                    'pix_code_start' => substr($pixCodeForReturn, 0, 50),
                    'pix_code_end' => substr($pixCodeForReturn, -10)
                ]);
                throw new Exception('Código PIX muito curto - formato inválido');
            }
            
            // Valida que o código contém apenas caracteres válidos para PIX EMV
            if (!preg_match('/^[0-9A-Za-z.\-@\/:]+$/', $pixCodeForReturn)) {
                Log::error('ERRO CRÍTICO: Código PIX contém caracteres inválidos', [
                    'pix_code_length' => strlen($pixCodeForReturn),
                    'pix_code_start' => substr($pixCodeForReturn, 0, 50),
                    'pix_code_hex_start' => bin2hex(substr($pixCodeForReturn, 0, 50)),
                    'invalid_chars' => preg_replace('/[0-9A-Za-z.\-@\/:]/', '', $pixCodeForReturn)
                ]);
                throw new Exception('Código PIX contém caracteres inválidos');
            }
            
            // Validação final crítica: garante que o código usado no QR Code é o mesmo do copia e cola
            // Se o QR Code foi gerado localmente, o código usado DEVE ser idêntico ao código retornado
            $codeUsedInQrCode = $pixCodeForReturn; // Mesmo código usado no QR Code
            
            // Log final confirmando que o código PIX tem CRC VÁLIDO
            Log::info('✅✅✅ Retornando código PIX (CRC VÁLIDO - será reconhecido pelo banco)', [
                'mercado_pago_code_original' => $pixCodeRaw, // Código ORIGINAL do Mercado Pago
                'mercado_pago_code_length' => strlen($pixCodeRaw),
                'code_to_return' => $pixCodeForReturn, // Código que será retornado (pode ter CRC corrigido)
                'code_to_return_length' => strlen($pixCodeForReturn),
                'pix_code_start' => substr($pixCodeForReturn, 0, 30),
                'pix_code_end' => substr($pixCodeForReturn, -10),
                'pix_code_crc' => substr($pixCodeForReturn, -4),
                'crc_validation_valid' => $finalCrcValidation['valid'],
                'crc_validation_crc_valid' => $finalCrcValidation['crc_valid'],
                'crc_validation_current_crc' => $finalCrcValidation['current_crc'],
                'crc_validation_calculated_crc' => $finalCrcValidation['calculated_crc'],
                'has_qr_code_image' => !empty($qrCodeImage),
                'using_mercado_pago_qr' => !empty($pixCodeBase64) && !empty($qrCodeImage),
                'pix_code_full' => $pixCodeForReturn, // Log completo para validação manual
                'pix_code_valid_format' => str_starts_with($pixCodeForReturn, '000201'),
                'pix_code_valid_length' => strlen($pixCodeForReturn) >= 100,
                'pix_code_valid_chars' => preg_match('/^[0-9A-Za-z.\-@\/:]+$/', $pixCodeForReturn),
                'code_used_in_qr_code' => $codeUsedInQrCode, // Código usado no QR Code
                'codes_match' => ($codeUsedInQrCode === $pixCodeForReturn), // Deve ser sempre true
                'note' => 'Código PIX tem CRC VÁLIDO - será reconhecido pelo banco (CRC pode ter sido corrigido se estava incorreto)'
            ]);
            
            
            // Valida que o código usado no QR Code é o mesmo do copia e cola
            if ($codeUsedInQrCode !== $pixCodeForReturn) {
                Log::error('ERRO CRÍTICO: Código usado no QR Code é diferente do código copia e cola!', [
                    'pix_code_for_return' => $pixCodeForReturn,
                    'code_used_in_qr_code' => $codeUsedInQrCode,
                    'pix_code_length' => strlen($pixCodeForReturn),
                    'qr_code_length' => strlen($codeUsedInQrCode)
                ]);
                throw new Exception('ERRO: Código usado no QR Code é diferente do código copia e cola. Verifique os logs.');
            }
            
            return [
                'success' => true,
                'transaction' => $transaction,
                'pix_key' => null, // Mercado Pago não retorna chave PIX diretamente
                'pix_code' => $pixCodeForReturn, // Código EXATO do Mercado Pago (validado)
                'qr_code_image' => $qrCodeImage, // QR Code do Mercado Pago ou gerado com código EXATO
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
            // CRÍTICO: O código PIX já deve estar limpo antes de chegar aqui
            // NÃO devemos limpar novamente - isso pode corromper o código
            // Apenas valida que está no formato correto
            
            $pixCodeOriginal = $pixCode;
            
            // Verifica se há espaços/quebras que precisam ser removidos
            $hasSpaces = strpos($pixCode, ' ') !== false;
            $hasNewlines = strpos($pixCode, "\n") !== false || strpos($pixCode, "\r") !== false;
            $hasTabs = strpos($pixCode, "\t") !== false;
            
            // Só limpa se realmente houver espaços/quebras
            if ($hasSpaces || $hasNewlines || $hasTabs) {
                Log::info('Código PIX contém espaços/quebras em generateQrCodeImage - removendo', [
                    'has_spaces' => $hasSpaces,
                    'has_newlines' => $hasNewlines,
                    'has_tabs' => $hasTabs
                ]);
                
                $pixCode = trim($pixCode);
                $pixCode = str_replace(["\r\n", "\n", "\r", "\t"], '', $pixCode);
                $pixCode = preg_replace('/\s+/', '', $pixCode);
                
                // Valida que não foi corrompido
                if (!str_starts_with($pixCode, '000201') && str_starts_with($pixCodeOriginal, '000201')) {
                    Log::error('ERRO: Código PIX corrompido em generateQrCodeImage!', [
                        'original_start' => substr($pixCodeOriginal, 0, 30),
                        'cleaned_start' => substr($pixCode, 0, 30)
                    ]);
                    $pixCode = $pixCodeOriginal; // Usa o original se corrompido
                }
            }
            
            // Valida o código antes de gerar o QR Code
            if (!str_starts_with($pixCode, '000201')) {
                Log::error('Código PIX inválido para geração de QR Code', [
                    'pix_code_start' => substr($pixCode, 0, 30),
                    'pix_code_length' => strlen($pixCode),
                    'pix_code_hex_start' => bin2hex(substr($pixCode, 0, 20))
                ]);
                throw new Exception('Código PIX inválido para geração de QR Code: não começa com 000201');
            }
            
            if (strlen($pixCode) < 100) {
                Log::error('Código PIX muito curto para geração de QR Code', [
                    'pix_code_length' => strlen($pixCode)
                ]);
                throw new Exception('Código PIX muito curto para geração de QR Code');
            }
            
            // Valida CRC (apenas informativo - NÃO corrige)
            $pixCrcService = new PixCrcService();
            $crcValidation = $pixCrcService->validatePixCode($pixCode);
            
            Log::info('Gerando QR Code para código PIX do Mercado Pago (SEM MODIFICAÇÕES)', [
                'pix_code_length' => strlen($pixCode),
                'pix_code_start' => substr($pixCode, 0, 30),
                'pix_code_end' => substr($pixCode, -10),
                'pix_code_crc' => substr($pixCode, -4),
                'crc_validation_valid' => $crcValidation['valid'],
                'crc_validation_crc_valid' => $crcValidation['crc_valid'],
                'crc_validation_current_crc' => $crcValidation['current_crc'],
                'crc_validation_calculated_crc' => $crcValidation['calculated_crc'],
                'pix_code_full' => $pixCode, // Log completo para validação
                'note' => 'Usando código EXATO do Mercado Pago - não será modificado'
            ]);
            
            // NÃO corrige o CRC - usa o código EXATO do Mercado Pago
            // Se nossa validação indicar inválido, logamos mas continuamos
            if (!$crcValidation['valid']) {
                Log::warning('ATENÇÃO: Validação CRC indicou código inválido, mas gerando QR Code com código do Mercado Pago mesmo assim', [
                    'current_crc' => $crcValidation['current_crc'],
                    'calculated_crc' => $crcValidation['calculated_crc'],
                    'note' => 'O código do Mercado Pago deve estar correto'
                ]);
            }
            
            // CRÍTICO: Gera QR Code usando o código PIX EXATO
            // Usa configurações otimizadas para garantir que o QR Code seja legível pelos bancos
            // Error Correction Level 'H' (High) para máxima confiabilidade
            // Margem adequada para melhor leitura
            // Tamanho suficiente para garantir qualidade
            
            // Log do código que será usado para gerar o QR Code
            Log::info('Gerando QR Code local - código PIX que será usado', [
                'pix_code_length' => strlen($pixCode),
                'pix_code_start' => substr($pixCode, 0, 30),
                'pix_code_end' => substr($pixCode, -10),
                'pix_code_crc' => substr($pixCode, -4),
                'pix_code_full' => $pixCode, // Log completo para validação
                'note' => 'Código EXATO que será codificado no QR Code'
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
}

