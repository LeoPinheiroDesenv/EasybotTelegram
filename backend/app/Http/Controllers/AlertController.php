<?php

namespace App\Http\Controllers;

use App\Jobs\SendAlert;
use App\Models\Alert;
use App\Models\Bot;
use App\Models\Contact;
use App\Services\PermissionService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AlertController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Lista todos os alertas
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = Alert::query();

            // Filtro por bot_id se fornecido
            if ($request->has('botId')) {
                $botId = $request->botId;
                
                // Verifica permissão
                if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'read')) {
                    return response()->json(['error' => 'Acesso negado'], 403);
                }

                $query->where('bot_id', $botId);
            } else {
                // Se não há botId, retorna apenas alertas dos bots do usuário
                $botIds = Bot::where('user_id', $user->id)->pluck('id');
                $query->whereIn('bot_id', $botIds);
            }

            // Filtros opcionais
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('alert_type')) {
                $query->where('alert_type', $request->alert_type);
            }

            $alerts = $query->with(['bot', 'plan'])->orderBy('created_at', 'desc')->get();

            return response()->json(['alerts' => $alerts]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao carregar alertas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cria um novo alerta
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bot_id' => 'required|exists:bots,id',
            'plan_id' => 'nullable|exists:payment_plans,id',
            'alert_type' => 'required|in:scheduled,periodic,common',
            'message' => 'required|string',
            'scheduled_date' => 'required_if:alert_type,scheduled|date',
            'scheduled_time' => 'required_if:alert_type,scheduled|date_format:H:i',
            'user_language' => 'nullable|in:pt,en,es',
            'user_category' => 'nullable|in:all,premium,free',
            'file_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            $botId = $request->bot_id;

            // Verifica permissão
            if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            // Verifica se o bot pertence ao usuário
            $bot = Bot::where('id', $botId)
                ->where('user_id', $user->id)
                ->first();

            if (!$bot) {
                return response()->json(['error' => 'Bot não encontrado ou não pertence ao usuário'], 404);
            }

            $alert = Alert::create([
                'bot_id' => $botId,
                'plan_id' => $request->plan_id,
                'alert_type' => $request->alert_type,
                'message' => $request->message,
                'scheduled_date' => $request->scheduled_date,
                'scheduled_time' => $request->scheduled_time,
                'user_language' => $request->user_language ?? 'pt',
                'user_category' => $request->user_category ?? 'all',
                'file_url' => $request->file_url,
                'status' => 'active',
            ]);

            // Se for alerta comum (common), processa imediatamente
            if ($request->alert_type === 'common') {
                try {
                    $this->processAlertImmediately($alert);
                } catch (\Exception $e) {
                    Log::error('Erro ao processar alerta comum imediatamente', [
                        'alert_id' => $alert->id,
                        'error' => $e->getMessage()
                    ]);
                    // Não falha a criação do alerta, apenas loga o erro
                }
            }

            return response()->json(['alert' => $alert->load(['bot', 'plan'])], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao criar alerta: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Exibe um alerta específico
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $alert = Alert::with(['bot', 'plan'])->findOrFail($id);

            // Verifica permissão
            if (!$this->permissionService->hasBotPermission($user, (int)$alert->bot_id, 'read')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            return response()->json(['alert' => $alert]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Alerta não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao carregar alerta: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza um alerta
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'nullable|exists:payment_plans,id',
            'alert_type' => 'sometimes|in:scheduled,periodic,common',
            'message' => 'sometimes|string',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'user_language' => 'nullable|in:pt,en,es',
            'user_category' => 'nullable|in:all,premium,free',
            'file_url' => 'nullable|url',
            'status' => 'sometimes|in:active,inactive,sent',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = auth()->user();
            $alert = Alert::findOrFail($id);

            // Verifica permissão
            if (!$this->permissionService->hasBotPermission($user, (int)$alert->bot_id, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $alert->update($request->only([
                'plan_id',
                'alert_type',
                'message',
                'scheduled_date',
                'scheduled_time',
                'user_language',
                'user_category',
                'file_url',
                'status',
            ]));

            return response()->json(['alert' => $alert->load(['bot', 'plan'])]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Alerta não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao atualizar alerta: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove um alerta
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $alert = Alert::findOrFail($id);

            // Verifica permissão
            if (!$this->permissionService->hasBotPermission($user, (int)$alert->bot_id, 'write')) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $alert->delete();

            return response()->json(['message' => 'Alerta excluído com sucesso']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Alerta não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao excluir alerta: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Processa e envia alertas que estão prontos
     */
    public function process(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $botId = $request->input('bot_id');

            // Se bot_id fornecido e usuário autenticado, verifica permissão
            if ($botId && $user) {
                if (!$this->permissionService->hasBotPermission($user, (int)$botId, 'write')) {
                    return response()->json(['error' => 'Acesso negado'], 403);
                }
            }

            // Busca alertas ativos que estão prontos para serem enviados
            $query = Alert::where('status', 'active')
                ->where(function ($q) {
                    // Alertas comuns (sem agendamento)
                    $q->where('alert_type', 'common')
                        // Ou alertas agendados que já passaram da data/hora
                        ->orWhere(function ($subQ) {
                            $subQ->where('alert_type', 'scheduled')
                                ->where('scheduled_date', '<=', now()->toDateString())
                                ->where(function ($timeQ) {
                                    $timeQ->whereNull('scheduled_time')
                                        ->orWhere('scheduled_time', '<=', now()->toTimeString());
                                });
                        });
                });

            // Filtra por bot se fornecido
            if ($botId) {
                $query->where('bot_id', $botId);
            } elseif ($user) {
                // Se não há botId mas há usuário autenticado, processa apenas alertas dos bots do usuário
                $botIds = Bot::where('user_id', $user->id)->pluck('id');
                if ($botIds->isNotEmpty()) {
                    $query->whereIn('bot_id', $botIds);
                } else {
                    // Se o usuário não tem bots, retorna vazio
                    return response()->json([
                        'success' => true,
                        'message' => 'Nenhum alerta para processar',
                        'processed' => 0,
                        'sent' => 0
                    ]);
                }
            }
            // Se não há botId nem usuário (endpoint público), processa todos os alertas prontos

            $alerts = $query->with(['bot'])->get();

            if ($alerts->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Nenhum alerta para processar',
                    'processed' => 0,
                    'sent' => 0
                ]);
            }

            $totalSent = 0;
            $processedAlerts = 0;

            foreach ($alerts as $alert) {
                try {
                    // Verifica se o alerta tem bot associado
                    if (!$alert->bot_id || !$alert->bot) {
                        Log::warning("Alerta ID: {$alert->id} não tem bot associado ou bot não encontrado");
                        continue;
                    }

                    // Verifica se o bot está ativo
                    if (!$alert->bot->active || !$alert->bot->activated) {
                        Log::info("Bot ID: {$alert->bot_id} não está ativo, pulando alerta ID: {$alert->id}");
                        continue;
                    }

                    // Busca contatos que devem receber o alerta
                    $contacts = $this->getTargetContacts($alert);

                    if ($contacts->isEmpty()) {
                        Log::info("Nenhum contato encontrado para o alerta ID: {$alert->id}");
                        continue;
                    }

                    // Envia alertas (síncrono para garantir envio imediato, não depende de fila)
                    $telegramService = app(TelegramService::class);
                    foreach ($contacts as $contact) {
                        try {
                            // Verifica se o contato está bloqueado
                            if ($contact->is_blocked) {
                                continue;
                            }

                            // Envia a mensagem diretamente (síncrono)
                            $telegramService->sendMessage($alert->bot, $contact->telegram_id, $alert->message);

                            // Se houver arquivo, envia também
                            if ($alert->file_url) {
                                $this->sendAlertMedia($telegramService, $alert->bot, $contact->telegram_id, $alert);
                            }

                            $totalSent++;
                        } catch (\Exception $e) {
                            Log::error('Erro ao enviar alerta para contato', [
                                'alert_id' => $alert->id,
                                'contact_id' => $contact->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    // Atualiza contador de envios uma vez após enviar para todos os contatos
                    if ($totalSent > 0) {
                        $alert->increment('sent_count', $totalSent);
                        if (!$alert->sent_at) {
                            $alert->update(['sent_at' => now()]);
                        }
                    }

                    // Se for alerta agendado, marca como enviado
                    if ($alert->alert_type === 'scheduled') {
                        $alert->update(['status' => 'sent']);
                    }

                    $processedAlerts++;
                } catch (\Exception $e) {
                    Log::error('Erro ao processar alerta', [
                        'alert_id' => $alert->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Processamento concluído. {$processedAlerts} alerta(s) processado(s), {$totalSent} mensagem(s) enviada(s).",
                'processed' => $processedAlerts,
                'sent' => $totalSent
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao processar alertas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Erro ao processar alertas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Busca contatos que devem receber o alerta
     */
    protected function getTargetContacts(Alert $alert): \Illuminate\Database\Eloquent\Collection
    {
        $query = Contact::where('bot_id', $alert->bot_id)
            ->where('is_bot', false)
            ->where('is_blocked', false)
            ->where('telegram_status', 'active');

        // Filtro por idioma
        if ($alert->user_language) {
            $query->where('language', $alert->user_language);
        }

        // Filtro por plano específico
        if ($alert->plan_id) {
            // Busca contatos que têm transações aprovadas com o plano especificado
            // e que ainda não expiraram (baseado no ciclo de pagamento)
            $contactIds = Transaction::where('bot_id', $alert->bot_id)
                ->where('payment_plan_id', $alert->plan_id)
                ->whereIn('status', ['approved', 'paid', 'completed'])
                ->whereHas('paymentCycle', function ($cycleQ) {
                    // Verifica se a transação ainda está dentro do período do ciclo
                    $cycleQ->whereRaw('DATE_ADD(transactions.created_at, INTERVAL payment_cycles.days DAY) >= NOW()');
                })
                ->pluck('contact_id')
                ->unique()
                ->toArray();
            
            if (empty($contactIds)) {
                // Se não há contatos com o plano, retorna coleção vazia
                return collect([]);
            }
            
            $query->whereIn('id', $contactIds);
        }

        // Filtro por categoria (premium/free)
        if ($alert->user_category !== 'all') {
            // Busca IDs de contatos premium (com transações ativas) para este bot
            $premiumContactIds = Transaction::where('bot_id', $alert->bot_id)
                ->whereIn('status', ['approved', 'paid', 'completed'])
                ->whereHas('paymentCycle', function ($cycleQ) {
                    $cycleQ->whereRaw('DATE_ADD(transactions.created_at, INTERVAL payment_cycles.days DAY) >= NOW()');
                })
                ->pluck('contact_id')
                ->unique()
                ->toArray();
            
            if ($alert->user_category === 'premium') {
                // Usuários premium: têm transações aprovadas ativas
                if (empty($premiumContactIds)) {
                    return collect([]);
                }
                $query->whereIn('id', $premiumContactIds);
            } elseif ($alert->user_category === 'free') {
                // Usuários free: não têm transações aprovadas ativas
                if (!empty($premiumContactIds)) {
                    $query->whereNotIn('id', $premiumContactIds);
                }
            }
        }

        return $query->get();
    }

    /**
     * Processa um alerta imediatamente (síncrono)
     */
    protected function processAlertImmediately(Alert $alert): void
    {
        // Recarrega o alerta com relacionamentos
        $alert->refresh();
        $alert->load(['bot']);

        // Verifica se o alerta tem bot associado
        if (!$alert->bot_id || !$alert->bot) {
            Log::warning("Alerta ID: {$alert->id} não tem bot associado ou bot não encontrado");
            return;
        }

        // Verifica se o bot está ativo
        if (!$alert->bot->active || !$alert->bot->activated) {
            Log::info("Bot ID: {$alert->bot_id} não está ativo, não processando alerta ID: {$alert->id}");
            return;
        }

        // Busca contatos que devem receber o alerta
        $contacts = $this->getTargetContacts($alert);

        if ($contacts->isEmpty()) {
            Log::info("Nenhum contato encontrado para o alerta ID: {$alert->id}");
            return;
        }

        // Envia alertas síncronamente
        $telegramService = app(TelegramService::class);
        $sentCount = 0;

        foreach ($contacts as $contact) {
            try {
                // Verifica se o contato está bloqueado
                if ($contact->is_blocked) {
                    continue;
                }

                // Envia a mensagem
                $telegramService->sendMessage($alert->bot, $contact->telegram_id, $alert->message);

                // Se houver arquivo, envia também
                if ($alert->file_url) {
                    $this->sendAlertMedia($telegramService, $alert->bot, $contact->telegram_id, $alert);
                }

                $sentCount++;
            } catch (\Exception $e) {
                Log::error('Erro ao enviar alerta para contato', [
                    'alert_id' => $alert->id,
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Atualiza contador de envios
        if ($sentCount > 0) {
            $alert->increment('sent_count', $sentCount);
            if (!$alert->sent_at) {
                $alert->update(['sent_at' => now()]);
            }
            Log::info("Alerta ID: {$alert->id} processado imediatamente. {$sentCount} mensagem(s) enviada(s).");
        }
    }

    /**
     * Envia mídia do alerta
     */
    protected function sendAlertMedia(TelegramService $telegramService, Bot $bot, int $chatId, Alert $alert): void
    {
        try {
            $fileUrl = $alert->file_url;
            
            // Determina o tipo de mídia pela extensão
            $extension = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
            
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $videoExtensions = ['mp4', 'avi', 'mov', 'mkv'];
            
            $message = '';
            if (in_array($extension, $imageExtensions)) {
                $message = "📷 Imagem: {$fileUrl}";
            } elseif (in_array($extension, $videoExtensions)) {
                $message = "🎥 Vídeo: {$fileUrl}";
            } else {
                $message = "📎 Arquivo: {$fileUrl}";
            }
            
            // Envia a URL como mensagem
            $telegramService->sendMessage($bot, $chatId, $message);
        } catch (\Exception $e) {
            Log::warning('Erro ao enviar mídia do alerta', [
                'alert_id' => $alert->id,
                'file_url' => $alert->file_url,
                'error' => $e->getMessage()
            ]);
        }
    }
}
