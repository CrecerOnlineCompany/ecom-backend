<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CinemaController extends Controller
{
    /**
     * Display a listing of cinemas.
     */
    public function index(): JsonResponse
    {
        $cinemas = Cinema::with(['rooms'])->where('is_active', true)->get();
        return response()->json($cinemas);
    }

    /**
     * Store a newly created cinema.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:cinemas',
            'city' => 'required|string',
            'address' => 'required|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $cinema = Cinema::create($validated);
        return response()->json($cinema, 201);
    }

    /**
     * Display the specified cinema.
     */
    public function show(Cinema $cinema): JsonResponse
    {
        $cinema->load(['rooms.screenings.movie']);
        return response()->json($cinema);
    }

    /**
     * Update the specified cinema.
     */
    public function update(Request $request, Cinema $cinema): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:cinemas,name,' . $cinema->id,
            'city' => 'sometimes|string',
            'address' => 'sometimes|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $cinema->update($validated);
        return response()->json($cinema);
    }

    /**
     * Remove the specified cinema.
     */
    public function destroy(Cinema $cinema): JsonResponse
    {
        $cinema->delete();
        return response()->json(['message' => 'Cinema deleted successfully']);
    }
}
