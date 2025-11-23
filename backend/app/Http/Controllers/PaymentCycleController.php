<?php

namespace App\Http\Controllers;

use App\Models\PaymentCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentCycleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $cycles = PaymentCycle::all();
            return response()->json(['cycles' => $cycles]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch payment cycles'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'days' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $cycle = PaymentCycle::create([
                'name' => $request->name,
                'days' => $request->days,
                'description' => $request->description,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json(['cycle' => $cycle], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create payment cycle'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $cycle = PaymentCycle::findOrFail($id);
            return response()->json(['cycle' => $cycle]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Payment cycle not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch payment cycle'], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'days' => 'sometimes|integer|min:1',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $cycle = PaymentCycle::findOrFail($id);
            $cycle->update($request->only(['name', 'days', 'description', 'is_active']));

            return response()->json(['cycle' => $cycle]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Payment cycle not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update payment cycle'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $cycle = PaymentCycle::findOrFail($id);
            $cycle->delete();

            return response()->json(['message' => 'Payment cycle deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Payment cycle not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete payment cycle'], 500);
        }
    }

    /**
     * Get active payment cycles
     */
    public function active(): JsonResponse
    {
        try {
            $cycles = PaymentCycle::where('is_active', true)->get();
            return response()->json(['cycles' => $cycles]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch active payment cycles'], 500);
        }
    }
}
