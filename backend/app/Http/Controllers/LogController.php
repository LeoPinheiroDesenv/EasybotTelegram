<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Log::with('bot');

            // Filter by level
            if ($request->has('level') && !empty($request->level)) {
                $query->where('level', $request->level);
            }

            // Filter by bot_id
            if ($request->has('bot_id') && !empty($request->bot_id)) {
                $query->where('bot_id', $request->bot_id);
            }

            // Filter by date range
            if ($request->has('startDate') && !empty($request->startDate)) {
                $query->whereDate('created_at', '>=', $request->startDate);
            }

            if ($request->has('endDate') && !empty($request->endDate)) {
                $query->whereDate('created_at', '<=', $request->endDate);
            }

            // Filter by user_email
            if ($request->has('user_email') && !empty($request->user_email)) {
                $query->where('user_email', 'like', '%' . $request->user_email . '%');
            }

            // Filter by message content
            if ($request->has('message') && !empty($request->message)) {
                $query->where('message', 'like', '%' . $request->message . '%');
            }

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination
            $limit = $request->get('limit', 100);
            $offset = $request->get('offset', 0);
            
            $logs = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($log) {
                    // Formata o contexto e detalhes para melhor visualização
                    $formatted = $log->toArray();
                    
                    // Garante que context seja um array
                    if (is_string($formatted['context'])) {
                        $formatted['context'] = json_decode($formatted['context'], true);
                    }
                    
                    // Garante que details seja um objeto/array se for string JSON
                    if (is_string($formatted['details'])) {
                        $decoded = json_decode($formatted['details'], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $formatted['details'] = $decoded;
                        }
                    }
                    
                    // Garante que request seja um objeto/array se for string JSON
                    if (isset($formatted['request']) && is_string($formatted['request'])) {
                        $decoded = json_decode($formatted['request'], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $formatted['request'] = $decoded;
                        }
                    }
                    
                    // Garante que response seja um objeto/array se for string JSON
                    if (isset($formatted['response']) && is_string($formatted['response'])) {
                        $decoded = json_decode($formatted['response'], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $formatted['response'] = $decoded;
                        }
                    }
                    
                    // Adiciona informações do bot se disponível
                    if ($log->bot) {
                        $formatted['bot_name'] = $log->bot->name;
                        $formatted['bot_username'] = $log->bot->username;
                    }
                    
                    return $formatted;
                });

            return response()->json([
                'logs' => $logs,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch logs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $log = Log::with('bot')->findOrFail($id);
            
            // Formata o contexto e detalhes
            $formatted = $log->toArray();
            
            if (is_string($formatted['context'])) {
                $formatted['context'] = json_decode($formatted['context'], true);
            }
            
            if (is_string($formatted['details'])) {
                $decoded = json_decode($formatted['details'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $formatted['details'] = $decoded;
                }
            }
            
            // Garante que request seja um objeto/array se for string JSON
            if (isset($formatted['request']) && is_string($formatted['request'])) {
                $decoded = json_decode($formatted['request'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $formatted['request'] = $decoded;
                }
            }
            
            // Garante que response seja um objeto/array se for string JSON
            if (isset($formatted['response']) && is_string($formatted['response'])) {
                $decoded = json_decode($formatted['response'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $formatted['response'] = $decoded;
                }
            }
            
            if ($log->bot) {
                $formatted['bot_name'] = $log->bot->name;
                $formatted['bot_username'] = $log->bot->username;
            }
            
            return response()->json(['log' => $formatted]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Log not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch log',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove todos os registros de log
     */
    public function deleteAll(): JsonResponse
    {
        try {
            $deletedCount = Log::query()->delete();

            return response()->json([
                'success' => true,
                'message' => "Todos os logs foram removidos com sucesso. {$deletedCount} registro(s) deletado(s).",
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover logs',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
