<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'botId' => 'required|exists:bots,id',
                'page' => 'sometimes|integer|min:1',
                'limit' => 'sometimes|integer|min:1|max:100',
                'search' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $query = Contact::where('bot_id', $request->botId);

            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('telegram_id', 'like', "%{$search}%");
                });
            }

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $offset = ($page - 1) * $limit;

            $contacts = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            $totalPages = ceil($total / $limit);

            return response()->json([
                'contacts' => $contacts,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'totalPages' => $totalPages,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch contacts'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bot_id' => 'required|exists:bots,id',
            'telegram_id' => 'required|integer',
            'username' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'is_bot' => 'sometimes|boolean',
            'is_blocked' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            // Check if contact already exists
            $existing = Contact::where('bot_id', $request->bot_id)
                ->where('telegram_id', $request->telegram_id)
                ->first();

            if ($existing) {
                // Update existing contact
                $existing->update($request->only(['username', 'first_name', 'last_name', 'is_bot', 'is_blocked']));
                return response()->json(['contact' => $existing]);
            }

            // Create new contact
            $contact = Contact::create([
                'bot_id' => $request->bot_id,
                'telegram_id' => $request->telegram_id,
                'username' => $request->username,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'is_bot' => $request->is_bot ?? false,
                'is_blocked' => $request->is_blocked ?? false,
            ]);

            return response()->json(['contact' => $contact], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create contact'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'botId' => 'required|exists:bots,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $contact = Contact::where('id', $id)
                ->where('bot_id', $request->botId)
                ->firstOrFail();

            // Carrega a última transação aprovada para identificar o plano e expiração
            $lastTransaction = $contact->transactions()
                ->where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->with(['paymentPlan', 'paymentCycle'])
                ->first();

            $planName = null;
            $expiresAt = null;
            $daysRemaining = null;

            if ($lastTransaction && $lastTransaction->paymentPlan) {
                $planName = $lastTransaction->paymentPlan->title ?? $lastTransaction->paymentPlan->name;

                if ($lastTransaction->paymentCycle) {
                    $expiresAtDate = \Carbon\Carbon::parse($lastTransaction->created_at)
                        ->addDays($lastTransaction->paymentCycle->days);
                    $expiresAt = $expiresAtDate->format('Y-m-d H:i:s');
                    $daysRemaining = (int) ceil(now()->diffInDays($expiresAtDate, false));
                }
            }

            // Adiciona atributos virtuais ao objeto contact
            $contact->current_plan = $planName;
            $contact->plan_expires_at = $expiresAt;
            $contact->plan_days_remaining = $daysRemaining;

            return response()->json(['contact' => $contact]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Contact not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch contact: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'is_bot' => 'sometimes|boolean',
            'is_blocked' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $contact = Contact::findOrFail($id);
            $contact->update($request->only(['username', 'first_name', 'last_name', 'is_bot', 'is_blocked']));

            return response()->json(['contact' => $contact]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Contact not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update contact'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'botId' => 'required|exists:bots,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $contact = Contact::where('id', $id)
                ->where('bot_id', $request->botId)
                ->firstOrFail();

            $contact->delete();

            return response()->json(['message' => 'Contact deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Contact not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete contact'], 500);
        }
    }

    /**
     * Block/unblock a contact
     */
    public function block(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'botId' => 'required|exists:bots,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $contact = Contact::where('id', $id)
                ->where('bot_id', $request->botId)
                ->firstOrFail();

            $wasBlocked = $contact->is_blocked;
            $contact->is_blocked = !$contact->is_blocked;
            $contact->save();

            // Registra ação de bloqueio/desbloqueio
            $bot = \App\Models\Bot::findOrFail($request->botId);
            $actionService = new \App\Services\ContactActionService();
            $actionService->logBlockAction($bot, $contact, $contact->is_blocked, 'Ação manual via interface');

            return response()->json([
                'message' => $contact->is_blocked ? 'Contact blocked successfully' : 'Contact unblocked successfully',
                'contact' => $contact,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Contact not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to block/unblock contact'], 500);
        }
    }

    /**
     * Get contact statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'botId' => 'required|exists:bots,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $botId = $request->botId;
            $total = Contact::where('bot_id', $botId)->count();
            $active = Contact::where('bot_id', $botId)
                ->where('telegram_status', 'active')
                ->where('is_blocked', false)
                ->count();
            $inactive = Contact::where('bot_id', $botId)
                ->where(function($query) {
                    $query->where('telegram_status', 'inactive')
                        ->orWhere('is_blocked', true);
                })
                ->count();

            return response()->json([
                'stats' => [
                    'total_count' => $total,
                    'active_count' => $active,
                    'inactive_count' => $inactive,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch contact statistics'], 500);
        }
    }

    /**
     * Get latest contacts
     */
    public function latest(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'botId' => 'required|exists:bots,id',
                'limit' => 'sometimes|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $limit = $request->get('limit', 10);
            $contacts = Contact::where('bot_id', $request->botId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json(['contacts' => $contacts]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch latest contacts'], 500);
        }
    }

    /**
     * Sincroniza membros do grupo salvando-os como contatos
     * Obtém administradores do grupo via API do Telegram e os salva no banco
     */
    public function syncGroupMembers(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'botId' => 'required|exists:bots,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $bot = \App\Models\Bot::findOrFail($request->botId);

            if (empty($bot->telegram_group_id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Bot não tem grupo configurado',
                    'synced_count' => 0
                ], 400);
            }

            $telegramService = new \App\Services\TelegramService();
            $result = $telegramService->syncGroupMembers($bot);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'synced_count' => $result['synced_count']
                ], 400);
            }

            // Atualiza status de todos os contatos do bot após sincronização
            $contacts = Contact::where('bot_id', $bot->id)->get();
            foreach ($contacts as $contact) {
                $telegramService->updateContactTelegramStatus($bot, $contact);
            }

            return response()->json([
                'success' => true,
                'message' => "{$result['synced_count']} membro(s) sincronizado(s) e status atualizado com sucesso",
                'synced_count' => $result['synced_count'],
                'details' => $result['details']
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to sync group members: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza o status do Telegram de todos os contatos de um bot
     */
    public function updateAllContactsStatus(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'botId' => 'required|exists:bots,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $bot = \App\Models\Bot::findOrFail($request->botId);
            $contacts = Contact::where('bot_id', $bot->id)->get();

            $telegramService = new \App\Services\TelegramService();
            $updatedCount = 0;

            foreach ($contacts as $contact) {
                try {
                    $telegramService->updateContactTelegramStatus($bot, $contact);
                    $updatedCount++;
                } catch (\Exception $e) {
                    // Continua com os próximos contatos mesmo se um falhar
                    continue;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Status de {$updatedCount} contato(s) atualizado(s) com sucesso",
                'updated_count' => $updatedCount
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update contacts status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envia lembrete de expiração para um contato específico
     */
    public function sendExpirationReminder(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'botId' => 'required|exists:bots,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $contact = Contact::where('id', $id)
                ->where('bot_id', $request->botId)
                ->firstOrFail();

            $bot = \App\Models\Bot::findOrFail($request->botId);

            // Busca a última transação aprovada para identificar o plano e expiração
            $lastTransaction = $contact->transactions()
                ->where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->with(['paymentPlan', 'paymentCycle'])
                ->first();

            if (!$lastTransaction || !$lastTransaction->paymentCycle) {
                return response()->json(['error' => 'Contato não possui plano ativo ou ciclo de pagamento definido'], 400);
            }

            $expiresAt = \Carbon\Carbon::parse($lastTransaction->created_at)
                ->addDays($lastTransaction->paymentCycle->days);

            $daysRemaining = now()->diffInDays($expiresAt, false);
            $daysRemaining = (int) ceil($daysRemaining);

            $planName = $lastTransaction->paymentPlan->title ?? $lastTransaction->paymentPlan->name;

            $message = "Olá {$contact->first_name}!\n\n";
            $message .= "Seu plano *{$planName}* ";

            if ($daysRemaining > 0) {
                $message .= "expira em *{$daysRemaining} dias* ({$expiresAt->format('d/m/Y')}).\n";
                $message .= "Renove agora para continuar aproveitando os benefícios!";
            } elseif ($daysRemaining == 0) {
                $message .= "expira *hoje*!\n";
                $message .= "Renove agora para não perder o acesso.";
            } else {
                $message .= "expirou em *{$expiresAt->format('d/m/Y')}*.\n";
                $message .= "Renove agora para recuperar o acesso.";
            }

            // Envia a mensagem
            $telegramService = new \App\Services\TelegramService();
            $telegramService->sendMessage($bot, $contact->telegram_id, $message);

            return response()->json(['message' => 'Lembrete enviado com sucesso']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao enviar lembrete: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Envia lembrete de expiração para todos os contatos do bot que têm plano ativo
     */
    public function sendGroupExpirationReminder(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'botId' => 'required|exists:bots,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $bot = \App\Models\Bot::findOrFail($request->botId);

            // Chama o comando artisan para verificar expirações
            // O comando deve ser implementado para aceitar --bot-id e --force (para enviar para todos)
            \Illuminate\Support\Facades\Artisan::call('expiration:check', [
                '--bot-id' => $bot->id,
                '--force' => true
            ]);

            return response()->json(['message' => 'Processo de envio de lembretes iniciado']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao iniciar envio em massa: ' . $e->getMessage()], 500);
        }
    }
}
