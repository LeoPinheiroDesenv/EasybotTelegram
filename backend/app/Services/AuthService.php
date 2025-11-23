<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    protected $twoFactorService;

    public function __construct()
    {
        // TwoFactorService será instanciado apenas quando necessário (lazy loading)
    }

    /**
     * Get TwoFactorService instance (lazy loading)
     */
    protected function getTwoFactorService()
    {
        if ($this->twoFactorService === null) {
            // Verificar se a classe existe antes de instanciar
            if (!class_exists('PragmaRX\Google2FA\Google2FA')) {
                return null;
            }
            
            try {
                $this->twoFactorService = app(TwoFactorService::class);
            } catch (\Exception $e) {
                // Se não conseguir instanciar, retorna null
                return null;
            }
        }
        
        return $this->twoFactorService;
    }

    /**
     * Login user
     */
    public function login(string $email, string $password, ?string $twoFactorToken = null): array
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new \Exception('Invalid credentials');
        }

        if (!$user->active) {
            throw new \Exception('Account is deactivated');
        }

        if (!Hash::check($password, $user->password)) {
            throw new \Exception('Invalid credentials');
        }

        // Se 2FA está ativado, verifica o token
        if ($user->two_factor_enabled) {
            $twoFactorService = $this->getTwoFactorService();
            
            if (!$twoFactorService) {
                throw new \Exception('Two-factor authentication is not available. Google2FA package is not installed.');
            }
            
            if (!$twoFactorToken) {
                return [
                    'requiresTwoFactor' => true,
                    'userId' => $user->id,
                    'message' => 'Two-factor authentication required'
                ];
            }

            try {
                $twoFactorService->verifyLoginCode($user->id, $twoFactorToken);
            } catch (\Exception $e) {
                throw new \Exception('Invalid two-factor authentication code');
            }
        }

        $token = JWTAuth::fromUser($user);

        // Obtém menus acessíveis
        $permissionService = app(\App\Services\PermissionService::class);
        $accessibleMenus = $permissionService->getAccessibleMenus($user);

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'user_type' => $user->user_type,
                'user_group_id' => $user->user_group_id,
                'accessible_menus' => $accessibleMenus,
            ]
        ];
    }

    /**
     * Get current user
     */
    public function getCurrentUser(int $userId): User
    {
        $user = User::find($userId);
        
        if (!$user) {
            throw new \Exception('User not found');
        }

        return $user;
    }
}

