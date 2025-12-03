<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Contact;
use App\Models\PaymentPlan;
use App\Models\Transaction;
use App\Models\PaymentGatewayConfig;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    /**
     * Gera QR Code PIX para um pagamento
     *
     * @param Bot $bot
     * @param PaymentPlan $plan
     * @param Contact $contact
     * @return array
     */
    public function generatePixQrCode(Bot $bot, PaymentPlan $plan, Contact $contact): array
    {
        try {
            // Busca configuração do gateway PIX
            $gatewayConfig = PaymentGatewayConfig::where('bot_id', $bot->id)
                ->where('gateway', 'pix')
                ->where('active', true)
                ->first();

            // Gera código de transação único
            $transactionId = 'PIX_' . time() . '_' . $bot->id . '_' . $plan->id . '_' . $contact->id;
            
            // Cria transação pendente
            $transaction = Transaction::create([
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'payment_plan_id' => $plan->id,
                'payment_cycle_id' => $plan->payment_cycle_id,
                'gateway' => 'pix',
                'gateway_transaction_id' => $transactionId,
                'amount' => $plan->price,
                'currency' => 'BRL',
                'status' => 'pending',
                'payment_method' => 'pix',
                'metadata' => [
                    'plan_title' => $plan->title,
                    'contact_name' => $contact->first_name ?? $contact->username ?? 'Cliente',
                    'created_at' => now()->toIso8601String()
                ]
            ]);

            // Gera chave PIX (pode ser CPF, email, chave aleatória, etc)
            // Por enquanto, vamos usar uma chave aleatória baseada no transaction_id
            $pixKey = $this->generatePixKey($bot, $transactionId);

            // Gera código PIX (EMV QR Code)
            $pixCode = $this->generatePixCode($bot, $plan, $pixKey, $transactionId);

            // Gera QR Code como imagem
            $qrCodeImage = $this->generateQrCodeImage($pixCode);

            // Salva QR Code temporariamente (opcional)
            $qrCodePath = $this->saveQrCodeImage($transactionId, $qrCodeImage);

            // Atualiza transação com informações do PIX
            $transaction->update([
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'pix_key' => $pixKey,
                    'pix_code' => $pixCode,
                    'qr_code_path' => $qrCodePath
                ])
            ]);

            return [
                'success' => true,
                'transaction' => $transaction,
                'pix_key' => $pixKey,
                'pix_code' => $pixCode,
                'qr_code_image' => $qrCodeImage,
                'qr_code_path' => $qrCodePath
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
     * Gera chave PIX
     *
     * @param Bot $bot
     * @param string $transactionId
     * @return string
     */
    protected function generatePixKey(Bot $bot, string $transactionId): string
    {
        // Busca configuração do gateway para obter chave PIX configurada
        $gatewayConfig = PaymentGatewayConfig::where('bot_id', $bot->id)
            ->where('gateway', 'pix')
            ->where('active', true)
            ->first();

        // Se tiver chave configurada no gateway, usa ela
        if ($gatewayConfig && $gatewayConfig->api_key) {
            return $gatewayConfig->api_key;
        }

        // Caso contrário, gera uma chave aleatória baseada no transaction_id
        // Em produção, isso deve ser configurado com uma chave PIX real
        return hash('sha256', $transactionId . $bot->id);
    }

    /**
     * Gera código PIX (EMV QR Code)
     * Formato simplificado - em produção, deve seguir o padrão EMV
     *
     * @param Bot $bot
     * @param PaymentPlan $plan
     * @param string $pixKey
     * @param string $transactionId
     * @return string
     */
    protected function generatePixCode(Bot $bot, PaymentPlan $plan, string $pixKey, string $transactionId): string
    {
        // Formato simplificado do código PIX
        // Em produção, deve seguir o padrão EMV QR Code do Banco Central
        $amount = number_format($plan->price, 2, '.', '');
        $merchantName = substr($bot->name ?? 'EasyPagamentos', 0, 25);
        $merchantCity = 'SAO PAULO';
        $description = "Pagamento - {$plan->title}";
        
        // Constrói código PIX no formato EMV
        // Payload Format Indicator (00)
        $payload = "000201";
        
        // Merchant Account Information (26) - PIX
        $merchantAccountInfo = "0014BR.GOV.BCB.PIX01" . sprintf("%02d", strlen($pixKey)) . $pixKey;
        $payload .= "26" . sprintf("%02d", strlen($merchantAccountInfo)) . $merchantAccountInfo;
        
        // Merchant Category Code (52) - 0000 = Não especificado
        $payload .= "52040000";
        
        // Transaction Currency (53) - 986 = BRL
        $payload .= "5303986";
        
        // Transaction Amount (54)
        $payload .= "54" . sprintf("%02d", strlen($amount)) . $amount;
        
        // Country Code (58) - BR
        $payload .= "5802BR";
        
        // Merchant Name (59)
        $payload .= "59" . sprintf("%02d", strlen($merchantName)) . $merchantName;
        
        // Merchant City (60)
        $payload .= "60" . sprintf("%02d", strlen($merchantCity)) . $merchantCity;
        
        // Additional Data Field Template (62)
        $referenceLabel = substr($transactionId, 0, 25);
        $additionalData = "05" . sprintf("%02d", strlen($referenceLabel)) . $referenceLabel;
        $payload .= "62" . sprintf("%02d", strlen($additionalData)) . $additionalData;
        
        // CRC16 (63)
        $crc = $this->calculateCRC16($payload);
        $payload .= "6304" . $crc;

        return $payload;
    }

    /**
     * Calcula CRC16 (simplificado)
     * Em produção, usar algoritmo correto do padrão EMV
     *
     * @param string $data
     * @return string
     */
    protected function calculateCRC16(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc <<= 1;
                }
            }
        }
        return strtoupper(dechex($crc & 0xFFFF));
    }

    /**
     * Gera imagem do QR Code
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
            // Busca configuração do gateway de pagamento
            $gatewayConfig = PaymentGatewayConfig::where('bot_id', $bot->id)
                ->whereIn('gateway', ['mercadopago', 'stripe'])
                ->where('active', true)
                ->where('environment', 'production')
                ->first();

            // Se não tiver em produção, busca em teste
            if (!$gatewayConfig) {
                $gatewayConfig = PaymentGatewayConfig::where('bot_id', $bot->id)
                    ->whereIn('gateway', ['mercadopago', 'stripe'])
                    ->where('active', true)
                    ->where('environment', 'test')
                    ->first();
            }

            // Gera código de transação único
            $transactionId = 'CARD_' . time() . '_' . $bot->id . '_' . $plan->id . '_' . $contact->id;
            
            // Cria transação pendente
            $transaction = Transaction::create([
                'bot_id' => $bot->id,
                'contact_id' => $contact->id,
                'payment_plan_id' => $plan->id,
                'payment_cycle_id' => $plan->payment_cycle_id,
                'gateway' => $gatewayConfig ? $gatewayConfig->gateway : 'card',
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
}

