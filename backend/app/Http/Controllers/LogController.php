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
            $query = Log::query();

            // Filter by level
            if ($request->has('level') && !empty($request->level)) {
                $query->where('level', $request->level);
            }

            // Filter by date range
            if ($request->has('startDate') && !empty($request->startDate)) {
                $query->whereDate('created_at', '>=', $request->startDate);
            }

            if ($request->has('endDate') && !empty($request->endDate)) {
                $query->whereDate('created_at', '<=', $request->endDate);
            }

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination
            $limit = $request->get('limit', 100);
            $offset = $request->get('offset', 0);
            
            $logs = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            return response()->json([
                'logs' => $logs,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch logs'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $log = Log::findOrFail($id);
            return response()->json(['log' => $log]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Log not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch log'], 500);
        }
    }
}
