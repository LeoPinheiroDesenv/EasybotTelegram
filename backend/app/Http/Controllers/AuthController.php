<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\PermissionService;
use App\Services\TwoFactorService;
use App\Models\User;
use App\Mail\PasswordResetMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
     * Register a new administrator
     */
    public function registerAdmin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'user_type' => 'admin',
                'role' => 'admin',
                'active' => true, // Admins created this way are active by default
            ]);

            return response()->json([
                'message' => 'Administrador cadastrado com sucesso!',
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Admin registration error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Erro ao cadastrar administrador'], 500);
        }
    }

    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'user_type' => 'nullable|string',
            'role' => 'nullable|string',
            'admin_code' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $userType = $request->user_type ?? 'user';
            $role = $request->role ?? 'user';

            // If creating an admin, set active to false by default (requires activation by super admin)
            $isAdmin = ($userType === 'admin' || $role === 'admin');

            $user = User::create([
                'name' => $request->username,
                'email' => $request->email,
                'password' => $request->password,
                'role' => $role,
                'user_type' => $userType,
                'active' => $isAdmin ? false : true,
            ]);

            $token = JWTAuth::fromUser($user);
            $user->load(['state', 'municipality']);

            $accessibleMenus = $this->permissionService->getAccessibleMenus($user);

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'user_type' => $user->user_type,
                    'user_group_id' => $user->user_group_id,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'description' => $user->description,
                    'address_street' => $user->address_street,
                    'address_number' => $user->address_number,
                    'address_zipcode' => $user->address_zipcode,
                    'state_id' => $user->state_id,
                    'municipality_id' => $user->municipality_id,
                    'state' => $user->state ? [
                        'id' => $user->state->id,
                        'nome' => $user->state->nome,
                        'uf' => $user->state->uf,
                    ] : null,
                    'municipality' => $user->municipality ? [
                        'id' => $user->municipality->id,
                        'nome' => $user->municipality->nome,
                    ] : null,
                    'accessible_menus' => $accessibleMenus,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Register error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Erro ao registrar usuário'], 500);
        }
    }

    /**
     * Authenticate or register using Google ID token (credential)
     */
    public function google(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'credential' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $credential = $request->credential;

            // Verify token with Google's tokeninfo endpoint
            $resp = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $credential]);

            if ($resp->failed()) {
                return response()->json(['error' => 'Invalid Google token'], 401);
            }

            $payload = $resp->json();

            // payload contains: email, email_verified, name, picture, sub (user id)
            if (empty($payload['email'])) {
                return response()->json(['error' => 'Google token does not contain email'], 400);
            }

            $email = $payload['email'];
            $name = $payload['name'] ?? ($payload['given_name'] ?? '');

            // Find or create user
            $user = User::where('email', $email)->first();
            if (!$user) {
                $user = User::create([
                    'name' => $name ?: $email,
                    'email' => $email,
                    // set a random password (user should use social login)
                    'password' => Str::random(32),
                    'role' => 'user',
                    'user_type' => 'user',
                    'active' => true,
                    'avatar' => $payload['picture'] ?? null,
                ]);
            }

            $token = JWTAuth::fromUser($user);
            $user->load(['state', 'municipality']);

            $accessibleMenus = $this->permissionService->getAccessibleMenus($user);

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'user_type' => $user->user_type,
                    'user_group_id' => $user->user_group_id,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'description' => $user->description,
                    'address_street' => $user->address_street,
                    'address_number' => $user->address_number,
                    'address_zipcode' => $user->address_zipcode,
                    'state_id' => $user->state_id,
                    'municipality_id' => $user->municipality_id,
                    'state' => $user->state ? [
                        'id' => $user->state->id,
                        'nome' => $user->state->nome,
                        'uf' => $user->state->uf,
                    ] : null,
                    'municipality' => $user->municipality ? [
                        'id' => $user->municipality->id,
                        'nome' => $user->municipality->nome,
                    ] : null,
                    'accessible_menus' => $accessibleMenus,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Google auth error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Erro ao validar credencial do Google'], 500);
        }
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
            $user->load(['state', 'municipality']);

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
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'description' => $user->description,
                    'address_street' => $user->address_street,
                    'address_number' => $user->address_number,
                    'address_zipcode' => $user->address_zipcode,
                    'state_id' => $user->state_id,
                    'municipality_id' => $user->municipality_id,
                    'state' => $user->state ? [
                        'id' => $user->state->id,
                        'nome' => $user->state->nome,
                        'uf' => $user->state->uf,
                    ] : null,
                    'municipality' => $user->municipality ? [
                        'id' => $user->municipality->id,
                        'nome' => $user->municipality->nome,
                    ] : null,
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
            $user->load(['state', 'municipality']);

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
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'description' => $user->description,
                    'address_street' => $user->address_street,
                    'address_number' => $user->address_number,
                    'address_zipcode' => $user->address_zipcode,
                    'state_id' => $user->state_id,
                    'municipality_id' => $user->municipality_id,
                    'state' => $user->state ? [
                        'id' => $user->state->id,
                        'nome' => $user->state->nome,
                        'uf' => $user->state->uf,
                    ] : null,
                    'municipality' => $user->municipality ? [
                        'id' => $user->municipality->id,
                        'nome' => $user->municipality->nome,
                    ] : null,
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
            $twoFactorService = $this->getTwoFactorService();

            if (!$twoFactorService) {
                return response()->json([
                    'error' => 'Two-factor authentication is not available. Google2FA package is not installed.'
                ], 503);
            }

            $user = auth()->user();
            $result = $twoFactorService->setup2FA($user->id, $user->email);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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

    /**
     * Request password reset
     */
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = User::where('email', $request->email)->first();

            // Sempre retorna sucesso para não revelar se o email existe
            if (!$user) {
                return response()->json([
                    'message' => 'Se o email existir, você receberá um link de recuperação de senha.'
                ], 200);
            }

            // Gera token único
            $token = Str::random(64);

            // Remove tokens antigos
            DB::table('password_resets')->where('email', $request->email)->delete();

            // Salva novo token
            DB::table('password_resets')->insert([
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            // Gera URL de reset
            $resetUrl = env('FRONTEND_URL', env('APP_URL')) . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);

            // Log antes de enviar
            \Log::info('Attempting to send password reset email', [
                'email' => $request->email,
                'mail_mailer' => env('MAIL_MAILER', 'not set'),
                'mail_host' => env('MAIL_HOST', 'not set'),
                'mail_port' => env('MAIL_PORT', 'not set'),
                'mail_encryption' => env('MAIL_ENCRYPTION', 'not set'),
                'mail_username' => env('MAIL_USERNAME', 'not set'),
                'mail_from_address' => env('MAIL_FROM_ADDRESS', 'not set'),
            ]);

            // Envia email
            try {
                $mailResult = Mail::to($user->email)->send(new PasswordResetMail($resetUrl));

                \Log::info('Password reset email sent successfully', [
                    'email' => $request->email,
                    'result' => $mailResult !== null ? 'sent' : 'queued',
                ]);
            } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
                \Log::error('Mail Transport Error sending password reset email', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'email' => $request->email,
                ]);
            } catch (\Exception $e) {
                \Log::error('Error sending password reset email', [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'email' => $request->email,
                ]);
            }

            return response()->json([
                'message' => 'Se o email existir, você receberá um link de recuperação de senha.'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error requesting password reset', [
                'error' => $e->getMessage(),
                'email' => $request->email,
            ]);

            return response()->json([
                'message' => 'Se o email existir, você receberá um link de recuperação de senha.'
            ], 200);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            // Busca o token
            $passwordReset = DB::table('password_resets')
                ->where('email', $request->email)
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'error' => 'Token inválido ou expirado.'
                ], 400);
            }

            // Verifica se o token é válido (válido por 1 hora)
            if (now()->diffInMinutes($passwordReset->created_at) > 60) {
                DB::table('password_resets')->where('email', $request->email)->delete();
                return response()->json([
                    'error' => 'Token expirado. Solicite um novo link de recuperação.'
                ], 400);
            }

            // Verifica se o token corresponde
            if (!Hash::check($request->token, $passwordReset->token)) {
                return response()->json([
                    'error' => 'Token inválido.'
                ], 400);
            }

            // Busca o usuário
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuário não encontrado.'
                ], 404);
            }

            // Atualiza a senha
            $user->password = $request->password;
            $user->save();

            // Remove o token usado
            DB::table('password_resets')->where('email', $request->email)->delete();

            return response()->json([
                'message' => 'Senha redefinida com sucesso!'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error resetting password', [
                'error' => $e->getMessage(),
                'email' => $request->email,
            ]);

            return response()->json([
                'error' => 'Erro ao redefinir senha. Tente novamente.'
            ], 500);
        }
    }
}
