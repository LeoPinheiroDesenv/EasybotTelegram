<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Contact;
use App\Services\GroupManagementService;
use App\Services\GroupStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GroupManagementController extends Controller
{
    protected $groupManagementService;
    protected $statisticsService;

    public function __construct(
        GroupManagementService $groupManagementService,
        GroupStatisticsService $statisticsService
    ) {
        $this->groupManagementService = $groupManagementService;
        $this->statisticsService = $statisticsService;
    }

    /**
     * Adiciona um membro manualmente ao grupo
     */
    public function addMember(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact_id' => 'required|integer|exists:contacts,id',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $contact = Contact::where('id', $request->contact_id)
                ->where('bot_id', $botId)
                ->firstOrFail();

            $result = $this->groupManagementService->addMemberManually(
                $bot,
                $contact,
                $request->reason
            );

            if (!$result['success']) {
                return response()->json(['error' => $result['error']], 400);
            }

            return response()->json([
                'message' => $result['message'] ?? 'Membro adicionado com sucesso',
                'success' => true
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot ou contato não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao adicionar membro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove um membro manualmente do grupo
     */
    public function removeMember(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact_id' => 'required|integer|exists:contacts,id',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $contact = Contact::where('id', $request->contact_id)
                ->where('bot_id', $botId)
                ->firstOrFail();

            $result = $this->groupManagementService->removeMemberManually(
                $bot,
                $contact,
                $request->reason
            );

            if (!$result['success']) {
                return response()->json(['error' => $result['error']], 400);
            }

            return response()->json([
                'message' => $result['message'] ?? 'Membro removido com sucesso',
                'success' => true
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot ou contato não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao remover membro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verifica o status de um membro no grupo
     */
    public function checkMemberStatus(string $botId, string $contactId): JsonResponse
    {
        try {
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $contact = Contact::where('id', $contactId)
                ->where('bot_id', $botId)
                ->firstOrFail();

            $result = $this->groupManagementService->checkMemberStatus($bot, $contact);

            if (!$result['success']) {
                return response()->json(['error' => $result['error']], 400);
            }

            return response()->json($result);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot ou contato não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao verificar status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lista informações do grupo
     */
    public function listGroupInfo(string $botId): JsonResponse
    {
        try {
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $result = $this->groupManagementService->listGroupMembers($bot);

            if (!$result['success']) {
                return response()->json(['error' => $result['error']], 400);
            }

            return response()->json($result);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao listar informações: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtém estatísticas de gerenciamento de grupo
     */
    public function getStatistics(string $botId, Request $request): JsonResponse
    {
        try {
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $days = $request->input('days', 30);
            $statistics = $this->statisticsService->getGroupStatistics($bot, $days);

            return response()->json($statistics);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao obter estatísticas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtém histórico de ações para um contato
     */
    public function getContactHistory(string $botId, string $contactId): JsonResponse
    {
        try {
            $bot = Bot::where('id', $botId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $history = $this->statisticsService->getContactHistory($bot, $contactId);

            if (empty($history)) {
                return response()->json(['error' => 'Contato não encontrado ou sem histórico'], 404);
            }

            return response()->json($history);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bot ou contato não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao obter histórico: ' . $e->getMessage()], 500);
        }
    }
}

