<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Contact;
use App\Services\PaymentStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PaymentStatusController extends Controller
{
    protected $paymentStatusService;

    public function __construct(PaymentStatusService $paymentStatusService)
    {
        $this->paymentStatusService = $paymentStatusService;
    }

    /**
     * Obtém status de pagamento de um contato específico
     *
     * @param Request $request
     * @param int $contactId
     * @return JsonResponse
     */
    public function getContactStatus(Request $request, int $contactId): JsonResponse
    {
        try {
            $contact = Contact::findOrFail($contactId);

            // Verifica se o usuário tem permissão para ver este contato
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $status = $this->paymentStatusService->getContactPaymentStatus($contact);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter status de pagamento do contato', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao obter status de pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém status de pagamentos de todos os contatos de um bot
     *
     * @param Request $request
     * @param int $botId
     * @return JsonResponse
     */
    public function getBotStatuses(Request $request, int $botId): JsonResponse
    {
        try {
            $bot = Bot::findOrFail($botId);

            // Verifica se o usuário tem permissão para ver este bot
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $statuses = $this->paymentStatusService->getBotPaymentStatuses($bot);

            // Filtros opcionais
            $statusFilter = $request->query('status'); // active, expired, expiring_soon, no_payment
            if ($statusFilter) {
                $statuses = array_filter($statuses, function ($status) use ($statusFilter) {
                    return $status['status'] === $statusFilter;
                });
            }

            // Ordenação
            $sortBy = $request->query('sort_by', 'contact.first_name');
            $sortOrder = $request->query('sort_order', 'asc');

            usort($statuses, function ($a, $b) use ($sortBy, $sortOrder) {
                $keys = explode('.', $sortBy);
                $valueA = $a;
                $valueB = $b;
                foreach ($keys as $key) {
                    $valueA = $valueA[$key] ?? null;
                    $valueB = $valueB[$key] ?? null;
                }

                if ($sortOrder === 'desc') {
                    return $valueB <=> $valueA;
                }
                return $valueA <=> $valueB;
            });

            return response()->json([
                'success' => true,
                'data' => array_values($statuses),
                'summary' => [
                    'total' => count($statuses),
                    'active' => count(array_filter($statuses, fn($s) => $s['status'] === 'active')),
                    'expired' => count(array_filter($statuses, fn($s) => $s['status'] === 'expired')),
                    'expiring_soon' => count(array_filter($statuses, fn($s) => $s['status'] === 'expiring_soon')),
                    'no_payment' => count(array_filter($statuses, fn($s) => $s['status'] === 'no_payment')),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter status de pagamentos do bot', [
                'bot_id' => $botId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao obter status de pagamentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica e processa pagamentos expirados
     *
     * @param Request $request
     * @param int|null $botId
     * @return JsonResponse
     */
    public function checkExpiredPayments(Request $request, ?int $botId = null): JsonResponse
    {
        try {
            $bot = $botId ? Bot::findOrFail($botId) : null;

            $result = $this->paymentStatusService->checkAndProcessExpiredPayments($bot);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao verificar pagamentos expirados', [
                'bot_id' => $botId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao verificar pagamentos expirados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica e notifica pagamentos próximos de expirar
     *
     * @param Request $request
     * @param int|null $botId
     * @return JsonResponse
     */
    public function checkExpiringPayments(Request $request, ?int $botId = null): JsonResponse
    {
        try {
            $bot = $botId ? Bot::findOrFail($botId) : null;
            $daysBeforeExpiration = (int) $request->query('days', 7);

            $result = $this->paymentStatusService->checkAndNotifyExpiringPayments($bot, $daysBeforeExpiration);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao verificar pagamentos próximos de expirar', [
                'bot_id' => $botId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao verificar pagamentos próximos de expirar: ' . $e->getMessage()
            ], 500);
        }
    }
}

