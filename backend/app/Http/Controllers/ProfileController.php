<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\State;
use App\Models\Municipality;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Obtém o perfil do usuário autenticado
     */
    public function getProfile(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Usuário não autenticado'], 401);
        }

        $user->load(['state', 'municipality']);

        return response()->json([
            'user' => $user
        ]);
    }

    /**
     * Atualiza o perfil do usuário autenticado
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Usuário não autenticado'], 401);
        }

        $isSuperAdmin = $user->isSuperAdmin();

        // Validação dos campos
        $rules = [
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'address_street' => 'nullable|string|max:255',
            'address_number' => 'nullable|string|max:20',
            'address_zipcode' => 'nullable|string|max:10',
            'state_id' => 'nullable|exists:states,id',
            'municipality_id' => 'nullable|exists:municipalities,id',
            'password' => 'sometimes|string|min:6',
        ];

        // Apenas super admin pode alterar email
        if ($isSuperAdmin && $request->has('email')) {
            $rules['email'] = 'sometimes|string|email|max:255|unique:users,email,' . $user->id;
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Campos que podem ser atualizados
        $updateData = $request->only([
            'name',
            'phone',
            'description',
            'address_street',
            'address_number',
            'address_zipcode',
            'state_id',
            'municipality_id'
        ]);

        // Apenas super admin pode alterar email
        if ($isSuperAdmin && $request->has('email')) {
            $updateData['email'] = $request->email;
        }

        // Atualiza senha se fornecida
        if ($request->has('password') && !empty($request->password)) {
            $updateData['password'] = $request->password; // Será hasheado pelo mutator
        }

        $user->update($updateData);
        $user->load(['state', 'municipality']);

        return response()->json([
            'message' => 'Perfil atualizado com sucesso',
            'user' => $user
        ]);
    }

    /**
     * Faz upload da foto de perfil do usuário
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Usuário não autenticado'], 401);
        }

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Máximo 2MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $file = $request->file('avatar');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            
            // Gera nome único para o arquivo
            $fileName = 'avatar_' . $user->id . '_' . time() . '.' . $extension;
            $path = 'avatars';
            
            // Verifica e cria link simbólico se necessário
            $this->ensureStorageLink();
            
            // Remove avatar antigo se existir
            if ($user->avatar) {
                $oldUrl = $user->avatar;
                $baseUrl = Storage::disk('public')->url('');
                $oldPath = str_replace($baseUrl, '', $oldUrl);
                $oldPath = ltrim($oldPath, '/');
                
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    try {
                        Storage::disk('public')->delete($oldPath);
                    } catch (\Exception $e) {
                        \Log::warning('Erro ao deletar avatar antigo: ' . $e->getMessage());
                    }
                }
            }
            
            // Salva o arquivo no storage público
            $filePath = $file->storeAs($path, $fileName, 'public');
            
            // Gera URL pública do arquivo
            $url = Storage::disk('public')->url($filePath);
            
            // Garante que a URL está corretamente formatada
            $url = preg_replace('/\s+/', '%20', $url);
            
            // Atualiza o avatar do usuário
            $user->avatar = $url;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Foto de perfil atualizada com sucesso',
                'url' => $url,
                'user' => $user->load(['state', 'municipality'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao fazer upload da foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a foto de perfil do usuário
     */
    public function removeAvatar(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Usuário não autenticado'], 401);
        }

        try {
            if ($user->avatar) {
                $oldUrl = $user->avatar;
                $baseUrl = Storage::disk('public')->url('');
                $oldPath = str_replace($baseUrl, '', $oldUrl);
                $oldPath = ltrim($oldPath, '/');
                
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            
            $user->avatar = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Foto de perfil removida com sucesso',
                'user' => $user->load(['state', 'municipality'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao remover foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Garante que o link simbólico do storage existe
     */
    private function ensureStorageLink(): void
    {
        $linkPath = public_path('storage');
        $targetPath = storage_path('app/public');
        
        if (!file_exists($linkPath)) {
            try {
                symlink($targetPath, $linkPath);
            } catch (\Exception $e) {
                \Log::warning('Erro ao criar link simbólico: ' . $e->getMessage());
            }
        }
    }

    /**
     * Lista todos os estados
     */
    public function getStates(): JsonResponse
    {
        $states = State::orderBy('nome')->get();

        return response()->json([
            'states' => $states
        ]);
    }

    /**
     * Lista municípios por estado (UF ou state_id)
     */
    public function getMunicipalitiesByState(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'uf' => 'sometimes|string|size:2',
            'state_id' => 'sometimes|exists:states,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $query = Municipality::query();

        if ($request->has('state_id')) {
            $state = State::find($request->state_id);
            if ($state) {
                $query->where('uf', $state->uf);
            }
        } elseif ($request->has('uf')) {
            $query->where('uf', $request->uf);
        } else {
            return response()->json(['error' => 'É necessário fornecer uf ou state_id'], 400);
        }

        $municipalities = $query->orderBy('nome')->get();

        return response()->json([
            'municipalities' => $municipalities
        ]);
    }

    /**
     * Consulta CEP via ViaCEP
     */
    public function consultCep(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cep' => 'required|string|size:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $cep = preg_replace('/[^0-9]/', '', $request->cep);

        try {
            $response = Http::get("https://viacep.com.br/ws/{$cep}/json/");

            if ($response->failed()) {
                return response()->json([
                    'error' => 'Erro ao consultar CEP'
                ], 400);
            }

            $data = $response->json();

            if (isset($data['erro'])) {
                return response()->json([
                    'error' => 'CEP não encontrado'
                ], 404);
            }

            // Busca o estado e município no banco
            $state = State::where('uf', $data['uf'])->first();
            $municipality = Municipality::where('nome', 'LIKE', $data['localidade'] . '%')
                ->where('uf', $data['uf'])
                ->first();

            return response()->json([
                'cep' => $data['cep'],
                'logradouro' => $data['logradouro'],
                'complemento' => $data['complemento'] ?? '',
                'bairro' => $data['bairro'],
                'localidade' => $data['localidade'],
                'uf' => $data['uf'],
                'state_id' => $state ? $state->id : null,
                'municipality_id' => $municipality ? $municipality->id : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao consultar CEP: ' . $e->getMessage()
            ], 500);
        }
    }
}
