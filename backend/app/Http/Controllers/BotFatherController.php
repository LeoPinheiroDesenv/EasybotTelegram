<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\PermissionService;

class BotFatherController extends Controller
{
    protected $telegramService;
    protected $permissionService;

    public function __construct(TelegramService $telegramService, PermissionService $permissionService)
    {
        $this->telegramService = $telegramService;
        $this->permissionService = $permissionService;
    }

    /**
     * Obtém o timeout configurado para requisições à API do Telegram
     *
     * @return int
     */
    protected function getTimeout(): int
    {
        return (int) env('TELEGRAM_API_TIMEOUT', 30);
    }
    
    /**
     * Cria uma instância HTTP com timeout e retry configurados
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->getTimeout())
            ->retry(2, 1000); // 2 tentativas com 1 segundo de delay
    }

    /**
     * Obtém informações do bot
     */
    public function getBotInfo(string $botId): JsonResponse
    {
        try {
            $bot = Bot::findOrFail($botId);
            $user = auth()->user();

            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $token = $bot->token;
            
            // Busca informações do bot via API do Telegram
            // Usa o mesmo scope usado ao registrar comandos (all_private_chats)
            $info = [
                'name' => $this->getMyName($token),
                'description' => $this->getMyDescription($token),
                'short_description' => $this->getMyShortDescription($token),
                'about' => $this->getMyAbout($token),
                'commands' => $this->getMyCommands($token, ['type' => 'all_private_chats']),
                'menu_button' => $this->getChatMenuButton($token),
                'default_administrator_rights' => $this->getMyDefaultAdministratorRights($token, false),
            ];

            return response()->json($info);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Define o nome do bot
     */
    public function setMyName(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $bot = Bot::findOrFail($botId);
            $user = auth()->user();

            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/setMyName", [
                    'name' => $request->name,
                ]);

            if ($result->successful() && $result->json()['ok']) {
                return response()->json(['success' => true, 'message' => 'Nome do bot atualizado com sucesso']);
            }

            return response()->json(['error' => $result->json()['description'] ?? 'Erro ao atualizar nome'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Define a descrição do bot
     */
    public function setMyDescription(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string|max:512',
            'language_code' => 'nullable|string|max:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $bot = Bot::findOrFail($botId);
            $user = auth()->user();

            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $data = ['description' => $request->description];
            if ($request->has('language_code')) {
                $data['language_code'] = $request->language_code;
            }

            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/setMyDescription", $data);

            if ($result->successful() && $result->json()['ok']) {
                return response()->json(['success' => true, 'message' => 'Descrição do bot atualizada com sucesso']);
            }

            return response()->json(['error' => $result->json()['description'] ?? 'Erro ao atualizar descrição'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Define a descrição curta do bot
     */
    public function setMyShortDescription(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'short_description' => 'required|string|max:120',
            'language_code' => 'nullable|string|max:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $bot = Bot::findOrFail($botId);
            $user = auth()->user();

            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $data = ['short_description' => $request->short_description];
            if ($request->has('language_code')) {
                $data['language_code'] = $request->language_code;
            }

            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/setMyShortDescription", $data);

            if ($result->successful() && $result->json()['ok']) {
                return response()->json(['success' => true, 'message' => 'Descrição curta do bot atualizada com sucesso']);
            }

            return response()->json(['error' => $result->json()['description'] ?? 'Erro ao atualizar descrição curta'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Define o texto "sobre" do bot
     */
    public function setMyAbout(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'about' => 'required|string|max:120',
            'language_code' => 'nullable|string|max:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $bot = Bot::findOrFail($botId);
            $user = auth()->user();

            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $data = ['about_text' => $request->about];
            if ($request->has('language_code')) {
                $data['language_code'] = $request->language_code;
            }

            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/setMyAbout", $data);

            if ($result->successful() && $result->json()['ok']) {
                return response()->json(['success' => true, 'message' => 'Texto "sobre" do bot atualizado com sucesso']);
            }

            return response()->json(['error' => $result->json()['description'] ?? 'Erro ao atualizar texto "sobre"'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Define o botão de menu do chat
     */
    public function setChatMenuButton(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:default,commands,web_app',
            'text' => 'nullable|string|max:255', // Required for web_app
            'url' => 'nullable|url|max:255', // Required for web_app
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $bot = Bot::findOrFail($botId);
            $user = auth()->user();

            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $data = ['type' => $request->type];
            
            if ($request->type === 'web_app') {
                if (!$request->has('text') || !$request->has('url')) {
                    return response()->json(['error' => 'Texto e URL são obrigatórios para web_app'], 400);
                }
                $data['text'] = $request->text;
                $data['web_app'] = ['url' => $request->url];
            }

            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/setChatMenuButton", $data);

            if ($result->successful() && $result->json()['ok']) {
                return response()->json(['success' => true, 'message' => 'Botão de menu atualizado com sucesso']);
            }

            return response()->json(['error' => $result->json()['description'] ?? 'Erro ao atualizar botão de menu'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Define os direitos padrão de administrador
     */
    public function setMyDefaultAdministratorRights(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rights' => 'required|array',
            'for_channels' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            Log::error('BotFather: Validação falhou ao atualizar direitos', [
                'bot_id' => $botId,
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $bot = Bot::findOrFail($botId);
            $user = auth()->user();

            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $forChannels = $request->has('for_channels') ? (bool)$request->for_channels : false;
            
            Log::info('BotFather: Processando direitos de administrador', [
                'bot_id' => $botId,
                'request_rights' => $request->rights,
                'for_channels' => $forChannels,
                'rights_count' => count($request->rights),
                'rights_keys' => array_keys($request->rights)
            ]);
            
            // Lista completa de todas as permissões possíveis do Telegram
            $allPermissions = [
                'is_anonymous',
                'can_manage_chat',
                'can_delete_messages',
                'can_manage_video_chats',
                'can_restrict_members',
                'can_promote_members',
                'can_change_info',
                'can_invite_users',
                'can_post_messages',
                'can_edit_messages',
                'can_pin_messages',
                'can_manage_topics',
                'can_post_stories',
                'can_edit_stories',
                'can_delete_stories',
            ];
            
            // Para grupos (não canais), adiciona can_read_all_group_messages
            if (!$forChannels) {
                $allPermissions[] = 'can_read_all_group_messages';
            }
            
            // Prepara os direitos finais usando APENAS os valores enviados pelo frontend
            // O frontend deve enviar TODAS as permissões (marcadas e desmarcadas)
            $finalRights = [];
            
            // Processa todas as permissões conhecidas
            foreach ($allPermissions as $permission) {
                // Se a permissão foi enviada na requisição, usa o valor enviado
                // Caso contrário, define como false
                if (isset($request->rights[$permission])) {
                    // Converte explicitamente para boolean
                    $value = $request->rights[$permission];
                    $finalRights[$permission] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                } else {
                    $finalRights[$permission] = false;
                }
            }
            
            // Adiciona quaisquer permissões adicionais que foram enviadas na requisição
            // (caso o Telegram adicione novas permissões no futuro)
            foreach ($request->rights as $key => $value) {
                if (!in_array($key, $allPermissions)) {
                    $finalRights[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                }
            }
            
            // Garante que todas as permissões sejam booleanas (segunda verificação)
            foreach ($finalRights as $key => $value) {
                $finalRights[$key] = (bool)$value;
            }
            
            // IMPORTANTE: O Telegram requer que pelo menos uma permissão seja true
            // Se todas forem false, o Telegram pode ignorar ou rejeitar a requisição
            $hasAtLeastOneTrue = false;
            foreach ($finalRights as $value) {
                if ($value === true) {
                    $hasAtLeastOneTrue = true;
                    break;
                }
            }
            
            if (!$hasAtLeastOneTrue) {
                Log::warning('BotFather: Todas as permissões estão como false. O Telegram pode não aplicar os direitos padrão.', [
                    'bot_id' => $botId,
                    'rights' => $finalRights
                ]);
            }
            
            Log::info('BotFather: Direitos finais preparados para envio', [
                'bot_id' => $botId,
                'final_rights' => $finalRights,
                'rights_count' => count($finalRights),
                'has_at_least_one_true' => $hasAtLeastOneTrue,
                'true_permissions' => array_keys(array_filter($finalRights, fn($v) => $v === true))
            ]);

            // Prepara os dados para envio ao Telegram
            // IMPORTANTE: Segundo a documentação do Telegram, o método setMyDefaultAdministratorRights
            // aceita um objeto ChatAdministratorRights com todas as permissões como propriedades booleanas.
            // O Telegram aceita todas as permissões explicitamente (true e false).
            // 
            // ESTRATÉGIA: Enviamos todas as permissões explicitamente quando houver pelo menos uma true.
            // Se todas forem false, enviamos um objeto vazio para limpar os direitos padrão.
            
            if (!$hasAtLeastOneTrue) {
                // Se todas as permissões são false, envia um objeto vazio para limpar direitos padrão
                Log::warning('BotFather: Todas as permissões estão como false. Enviando objeto vazio para limpar direitos padrão.', [
                    'bot_id' => $botId,
                    'all_rights' => $finalRights
                ]);
                $rightsToSend = [];
            } else {
                // Envia todas as permissões explicitamente (true e false)
                // Isso garante que o Telegram aplique corretamente todas as permissões
                $rightsToSend = $finalRights;
            }
            
            $data = ['rights' => $rightsToSend];
            // Sempre envia for_channels para garantir que está configurado corretamente
            $data['for_channels'] = $forChannels;
            
            Log::info('BotFather: Direitos preparados para envio', [
                'bot_id' => $botId,
                'rights_to_send' => $rightsToSend,
                'rights_count' => count($rightsToSend),
                'all_rights' => $finalRights,
                'for_channels' => $forChannels,
                'data' => $data
            ]);

            // Envia a requisição ao Telegram
            // O Telegram espera um objeto ChatAdministratorRights com todas as permissões
            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/setMyDefaultAdministratorRights", $data);

            $responseData = $result->json();
            
            Log::info('BotFather: Resposta da API do Telegram', [
                'bot_id' => $botId,
                'successful' => $result->successful(),
                'status' => $result->status(),
                'status_code' => $result->status(),
                'response' => $responseData,
                'response_body' => $result->body()
            ]);
            
            if ($result->successful() && ($responseData['ok'] ?? false)) {
                // Verifica se o resultado contém os direitos aplicados
                $appliedRights = $responseData['result'] ?? null;
                
                Log::info('BotFather: Direitos de administrador atualizados com sucesso', [
                    'bot_id' => $botId,
                    'response' => $responseData,
                    'sent_rights' => $rightsToSend,
                    'all_rights' => $finalRights,
                    'applied_rights' => $appliedRights
                ]);
                
                if ($appliedRights) {
                    Log::info('BotFather: Direitos aplicados pelo Telegram confirmados', [
                        'bot_id' => $botId,
                        'applied_rights' => $appliedRights,
                        'applied_rights_keys' => array_keys($appliedRights ?? [])
                    ]);
                } else {
                    Log::warning('BotFather: Resposta do Telegram não contém direitos aplicados', [
                        'bot_id' => $botId,
                        'response' => $responseData
                    ]);
                }
                
                // IMPORTANTE: Os direitos padrão são apenas sugestões quando o bot é adicionado a um grupo
                // Eles não são aplicados automaticamente a grupos onde o bot já é administrador
                // Para aplicar os direitos a um grupo existente, é necessário usar promoteChatMember
                
                return response()->json([
                    'success' => true, 
                    'message' => 'Direitos padrão de administrador atualizados com sucesso. Estes direitos serão sugeridos quando o bot for adicionado a novos grupos.',
                    'rights' => $finalRights,
                    'rights_sent' => $rightsToSend,
                    'telegram_response' => $responseData,
                    'applied_rights' => $appliedRights,
                    'note' => 'Os direitos padrão são aplicados apenas quando o bot é adicionado a novos grupos. Para grupos existentes, é necessário promover o bot novamente.'
                ]);
            }

            $errorDescription = $responseData['description'] ?? 'Erro ao atualizar direitos';
            
            // Se a resposta não foi bem-sucedida, loga detalhes adicionais
            if (!$result->successful()) {
                Log::error('BotFather: Resposta HTTP não foi bem-sucedida', [
                    'bot_id' => $botId,
                    'status' => $result->status(),
                    'body' => $result->body()
                ]);
            }
            Log::error('BotFather: Erro ao atualizar direitos de administrador', [
                'bot_id' => $botId,
                'error' => $errorDescription,
                'response' => $responseData,
                'sent_data' => $data
            ]);

            return response()->json(['error' => $errorDescription], 400);
        } catch (\Exception $e) {
            Log::error('BotFather: Exceção ao atualizar direitos de administrador', [
                'bot_id' => $botId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Deleta comandos do bot
     */
    public function deleteMyCommands(Request $request, string $botId): JsonResponse
    {
        try {
            $bot = Bot::findOrFail($botId);
            $user = auth()->user();

            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $data = [];
            if ($request->has('scope')) {
                $data['scope'] = $request->scope;
            }
            if ($request->has('language_code')) {
                $data['language_code'] = $request->language_code;
            }

            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$bot->token}/deleteMyCommands", $data);

            if ($result->successful() && $result->json()['ok']) {
                return response()->json(['success' => true, 'message' => 'Comandos deletados com sucesso']);
            }

            return response()->json(['error' => $result->json()['description'] ?? 'Erro ao deletar comandos'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Métodos auxiliares para obter informações

    private function getMyName(string $token): ?string
    {
        try {
            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/getMyName");
            
            if ($result->successful() && $result->json()['ok']) {
                return $result->json()['result']['name'] ?? null;
            }
        } catch (\Exception $e) {
            // Ignora erros
        }
        return null;
    }

    private function getMyDescription(string $token, ?string $languageCode = null): ?string
    {
        try {
            $data = [];
            if ($languageCode) {
                $data['language_code'] = $languageCode;
            }
            
            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/getMyDescription", $data);
            
            if ($result->successful() && $result->json()['ok']) {
                return $result->json()['result']['description'] ?? null;
            }
        } catch (\Exception $e) {
            // Ignora erros
        }
        return null;
    }

    private function getMyShortDescription(string $token, ?string $languageCode = null): ?string
    {
        try {
            $data = [];
            if ($languageCode) {
                $data['language_code'] = $languageCode;
            }
            
            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/getMyShortDescription", $data);
            
            if ($result->successful() && $result->json()['ok']) {
                return $result->json()['result']['short_description'] ?? null;
            }
        } catch (\Exception $e) {
            // Ignora erros
        }
        return null;
    }

    private function getMyAbout(string $token, ?string $languageCode = null): ?string
    {
        try {
            $data = [];
            if ($languageCode) {
                $data['language_code'] = $languageCode;
            }
            
            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/getMyAbout", $data);
            
            if ($result->successful() && $result->json()['ok']) {
                return $result->json()['result']['about_text'] ?? null;
            }
        } catch (\Exception $e) {
            // Ignora erros
        }
        return null;
    }

    private function getMyCommands(string $token, ?array $scope = null, ?string $languageCode = null): array
    {
        try {
            $data = [];
            if ($scope) {
                $data['scope'] = $scope;
            }
            if ($languageCode) {
                $data['language_code'] = $languageCode;
            }
            
            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/getMyCommands", $data);
            
            if ($result->successful() && $result->json()['ok']) {
                return $result->json()['result'] ?? [];
            }
        } catch (\Exception $e) {
            // Ignora erros
        }
        return [];
    }

    private function getChatMenuButton(string $token, ?string $chatId = null): ?array
    {
        try {
            $data = [];
            if ($chatId) {
                $data['chat_id'] = $chatId;
            }
            
            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/getChatMenuButton", $data);
            
            if ($result->successful() && $result->json()['ok']) {
                return $result->json()['result'] ?? null;
            }
        } catch (\Exception $e) {
            // Ignora erros
        }
        return null;
    }

    private function getMyDefaultAdministratorRights(string $token, ?bool $forChannels = null): ?array
    {
        try {
            $data = [];
            if ($forChannels !== null) {
                $data['for_channels'] = $forChannels;
            }
            
            $result = $this->http()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/getMyDefaultAdministratorRights", $data);
            
            $responseData = $result->json();
            
            if ($result->successful() && ($responseData['ok'] ?? false)) {
                $rights = $responseData['result'] ?? null;
                Log::debug('BotFather: Direitos atuais obtidos', [
                    'for_channels' => $forChannels,
                    'rights' => $rights
                ]);
                return $rights;
            }
            
            Log::warning('BotFather: Erro ao obter direitos atuais', [
                'for_channels' => $forChannels,
                'response' => $responseData
            ]);
        } catch (\Exception $e) {
            Log::error('BotFather: Exceção ao obter direitos atuais', [
                'for_channels' => $forChannels,
                'error' => $e->getMessage()
            ]);
        }
        return null;
    }
}

