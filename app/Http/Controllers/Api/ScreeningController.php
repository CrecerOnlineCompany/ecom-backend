<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Screening;
use App\Models\ScreeningSeat;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ScreeningController extends Controller
{
    /**
     * Display a listing of screenings.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Screening::with(['movie', 'room.cinema']);

        if ($request->has('movie_id')) {
            $query->where('movie_id', $request->movie_id);
        }

        if ($request->has('cinema_id')) {
            $query->whereHas('room', function ($q) {
                $q->where('cinema_id', request()->cinema_id);
            });
        }

        if ($request->has('date')) {
            $date = $request->date;
            $query->whereDate('start_time', $date);
        }

        $screenings = $query->orderBy('start_time')->paginate(20);
        return response()->json($screenings);
    }

    /**
     * Store a newly created screening.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'movie_id' => 'required|exists:movies,id',
            'room_id' => 'required|exists:rooms,id',
            'start_time' => 'required|date_format:Y-m-d H:i:s',
            'end_time' => 'required|date_format:Y-m-d H:i:s|after:start_time',
            'price' => 'required|numeric|min:0.01',
            'format' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $room = Room::find($validated['room_id']);
        $validated['available_seats'] = $room->total_seats;

        $screening = Screening::create($validated);
        return response()->json($screening, 201);
    }

    /**
     * Display the specified screening.
     */
    public function show(Screening $screening): JsonResponse
    {
        $screening->load(['movie', 'room.cinema', 'tickets.seat']);
        return response()->json($screening);
    }

    /**
     * Update the specified screening.
     */
    public function update(Request $request, Screening $screening): JsonResponse
    {
        $validated = $request->validate([
            'movie_id' => 'sometimes|exists:movies,id',
            'room_id' => 'sometimes|exists:rooms,id',
            'start_time' => 'sometimes|date_format:Y-m-d H:i:s',
            'end_time' => 'sometimes|date_format:Y-m-d H:i:s',
            'price' => 'sometimes|numeric|min:0.01',
            'format' => 'sometimes|string',
            'is_active' => 'boolean',
        ]);

        $screening->update($validated);
        return response()->json($screening);
    }

    /**
     * Remove the specified screening.
     */
    public function destroy(Screening $screening): JsonResponse
    {
        $screening->delete();
        return response()->json(['message' => 'Screening deleted successfully']);
    }

    /**
     * Get available seats for a screening
     * Fuente de verdad: screening_seats (order-first inventory)
     * Excluye asientos SOLD y RESERVED vigentes (no expirados)
     */
    public function availableSeats(Screening $screening): JsonResponse
    {

        $blockedSeatIds = ScreeningSeat::where('screening_id', $screening->id)
            ->where(function ($query) {
                $query->where('status', ScreeningSeat::STATUS_SOLD)
                    ->orWhere(function ($reservedQuery) {
                        $reservedQuery->where('status', ScreeningSeat::STATUS_RESERVED);
                    });
            })
            ->pluck('seat_id')
            ->toArray();
        
        $availableSeats = $screening->room->seats()
            ->whereNotIn('id', $blockedSeatIds)
            ->where('is_active', true)
            ->select('id', 'seat_code', 'type', 'row_number', 'seat_number')
            ->get();

        return response()->json([
            'screening_id' => $screening->id,
            'total_seats' => $screening->room->total_seats,
            'booked_seats_count' => count($blockedSeatIds),
            'available_seats_count' => $availableSeats->count(),
            'seats' => $availableSeats,
        ]);
    }
}
