<?php

namespace App\Services;

use App\Models\User;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorService
{
    protected $google2fa;

    public function __construct()
    {
        // Verificar se a classe Google2FA está disponível
        if (!class_exists('PragmaRX\Google2FA\Google2FA')) {
            throw new \RuntimeException(
                'Google2FA package is not installed. Please run: composer require pragmarx/google2fa:^9.0'
            );
        }
        
        $this->google2fa = new \PragmaRX\Google2FA\Google2FA();
    }

    /**
     * Gera um secret para 2FA
     */
    public function generateSecret(string $userEmail): array
    {
        $secret = $this->google2fa->generateSecretKey();
        
        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            'Easy Bot Telegram',
            $userEmail,
            $secret
        );

        return [
            'base32' => $secret,
            'otpauth_url' => $otpauthUrl
        ];
    }

    /**
     * Gera QR code em formato data URL
     */
    public function generateQRCode(string $otpauthUrl): string
    {
        try {
            $qrCodeData = QrCode::format('png')
                ->size(200)
                ->generate($otpauthUrl);
            
            // Converte para data URL
            $base64 = base64_encode($qrCodeData);
            return 'data:image/png;base64,' . $base64;
        } catch (\Exception $e) {
            throw new \Exception('Failed to generate QR code: ' . $e->getMessage());
        }
    }

    /**
     * Verifica o código TOTP
     */
    public function verifyToken(string $secret, string $token): bool
    {
        try {
            return $this->google2fa->verifyKey($secret, $token, 2); // window: 2 períodos
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Setup inicial do 2FA - gera secret e QR code
     */
    public function setup2FA(int $userId, string $userEmail): array
    {
        $secretData = $this->generateSecret($userEmail);
        
        // Salva o secret temporariamente (ainda não está ativado)
        $user = User::findOrFail($userId);
        $user->two_factor_secret = $secretData['base32'];
        $user->save();

        // Gera QR code
        $qrCodeUrl = $this->generateQRCode($secretData['otpauth_url']);

        return [
            'secret' => $secretData['base32'],
            'qrCode' => $qrCodeUrl,
            'manualEntryKey' => $secretData['base32']
        ];
    }

    /**
     * Verifica o código durante o setup e ativa o 2FA
     */
    public function verifyAndEnable2FA(int $userId, string $token): array
    {
        $user = User::findOrFail($userId);
        
        if (!$user->two_factor_secret) {
            throw new \Exception('2FA not set up. Please set up 2FA first.');
        }

        $isValid = $this->verifyToken($user->two_factor_secret, $token);
        
        if (!$isValid) {
            throw new \Exception('Invalid verification code');
        }

        // Ativa o 2FA
        $user->two_factor_enabled = true;
        $user->save();

        return ['success' => true];
    }

    /**
     * Desativa o 2FA
     */
    public function disable2FA(int $userId): array
    {
        $user = User::findOrFail($userId);
        $user->two_factor_secret = null;
        $user->two_factor_enabled = false;
        $user->save();

        return ['success' => true];
    }

    /**
     * Verifica o código durante o login
     */
    public function verifyLoginCode(int $userId, string $token): array
    {
        $user = User::findOrFail($userId);
        
        if (!$user->two_factor_enabled || !$user->two_factor_secret) {
            throw new \Exception('2FA is not enabled for this user');
        }

        $isValid = $this->verifyToken($user->two_factor_secret, $token);
        
        if (!$isValid) {
            throw new \Exception('Invalid verification code');
        }

        return ['success' => true];
    }
}

