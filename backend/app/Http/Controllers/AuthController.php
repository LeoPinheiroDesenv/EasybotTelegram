<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\PermissionService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    protected $authService;
    protected $permissionService;
    protected $twoFactorService;

    public function __construct(AuthService $authService, PermissionService $permissionService)
    {
        $this->authService = $authService;
        $this->permissionService = $permissionService;
        // TwoFactorService será instanciado apenas quando necessário (lazy loading)
    }

    /**
     * Get TwoFactorService instance (lazy loading)
     */
    protected function getTwoFactorService(): ?TwoFactorService
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
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'twoFactorToken' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $result = $this->authService->login(
                $request->email,
                $request->password,
                $request->twoFactorToken
            );

            if (isset($result['requiresTwoFactor'])) {
                return response()->json($result, 200);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            $statusCode = match($e->getMessage()) {
                'Invalid credentials' => 401,
                'Account is deactivated' => 403,
                'Invalid two-factor authentication code' => 403,
                default => 500
            };

            return response()->json([
                'error' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * Verify two-factor authentication code
     */
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'required|integer',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $twoFactorService = $this->getTwoFactorService();
            
            if (!$twoFactorService) {
                return response()->json([
                    'error' => 'Two-factor authentication is not available. Google2FA package is not installed.'
                ], 503);
            }

            $user = \App\Models\User::findOrFail($request->userId);

            if (!$user->two_factor_enabled) {
                return response()->json([
                    'error' => 'Two-factor authentication is not enabled for this user'
                ], 400);
            }

            $twoFactorService->verifyLoginCode($user->id, $request->token);

            $token = JWTAuth::fromUser($user);

            // Obtém menus acessíveis
            $permissionService = app(\App\Services\PermissionService::class);
            $accessibleMenus = $permissionService->getAccessibleMenus($user);

            return response()->json([
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
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'User not found'], 404);
        } catch (\Exception $e) {
            $statusCode = in_array($e->getMessage(), [
                'Invalid verification code',
                'Invalid two-factor authentication code',
                '2FA is not enabled for this user'
            ]) ? 401 : 500;

            return response()->json(['error' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * Get current authenticated user
     */
    public function getCurrentUser(): JsonResponse
    {
        try {
            $user = $this->authService->getCurrentUser(auth()->id());
            
            // Obtém menus acessíveis
            $accessibleMenus = $this->permissionService->getAccessibleMenus($user);
            
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'user_type' => $user->user_type,
                    'user_group_id' => $user->user_group_id,
                    'accessible_menus' => $accessibleMenus,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Setup 2FA (generate secret and QR code)
     */
    public function setup2FA(): JsonResponse
    {
        try {
            $user = auth()->user();
            $result = $this->twoFactorService->setup2FA($user->id, $user->email);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Verify and enable 2FA
     */
    public function verifyAndEnable2FA(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $twoFactorService = $this->getTwoFactorService();
            
            if (!$twoFactorService) {
                return response()->json([
                    'error' => 'Two-factor authentication is not available. Google2FA package is not installed.'
                ], 503);
            }

            $result = $twoFactorService->verifyAndEnable2FA(
                auth()->id(),
                $request->token
            );
            return response()->json($result);
        } catch (\Exception $e) {
            $statusCode = in_array($e->getMessage(), [
                'Invalid verification code',
                '2FA not set up. Please set up 2FA first.'
            ]) ? 400 : 500;

            return response()->json(['error' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * Disable 2FA
     */
    public function disable2FA(): JsonResponse
    {
        try {
            $twoFactorService = $this->getTwoFactorService();
            
            if (!$twoFactorService) {
                return response()->json([
                    'error' => 'Two-factor authentication is not available. Google2FA package is not installed.'
                ], 503);
            }

            $result = $twoFactorService->disable2FA(auth()->id());
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
