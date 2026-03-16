<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Screening;
use App\Models\ScreeningSeat;
use App\Models\Room;
use Carbon\Carbon;
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
        $query = Screening::query()
            ->join('rooms', 'rooms.id', '=', 'screenings.room_id')
            ->join('cinemas', 'cinemas.id', '=', 'rooms.cinema_id')
            ->select([
                'screenings.id',
                'screenings.start_time',
                'screenings.format',
                'screenings.available_seats',
                'rooms.cinema_id',
                'cinemas.name as cinema_name',
                'rooms.number as room_number',
            ]);

        if ($request->has('movie_id')) {
            $query->where('screenings.movie_id', $request->movie_id);
        }

        if ($request->has('cinema_id')) {
            $query->where('rooms.cinema_id', $request->cinema_id);
        }

        if ($request->has('date')) {

            $date = Carbon::parse($request->date);

            $query->whereBetween('screenings.start_time', [
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay()
            ]);

            if ($date->isToday()) {
                $query->where('screenings.start_time', '>=', now());
            }
        } else {

            $query->where('screenings.start_time', '>=', now());
        }

        if ($request->has('is_active')) {
            $query->where('screenings.is_active', $request->boolean('is_active'));
        } else {
            $query->where('screenings.is_active', true);
        }


        $screenings = $query->orderBy('screenings.start_time')->paginate(20);
        $screenings->getCollection()->transform(function ($screening) {
            $screening->start_time = Carbon::parse($screening->start_time)->utc()->format('Y-m-d\TH:i:s\Z');
            return $screening;
        });

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
        $screening = Screening::query()
            ->select([
                'id',
                'movie_id',
                'room_id',
                'start_time',
                'price',
                'available_seats',
                'format',
            ])
            ->with([
                'movie:id,title',
                'room:id,cinema_id,number',
                'room.cinema:id,name',
            ])
            ->findOrFail($screening->id);

        return response()->json([
            'id' => $screening->id,
            'movie_id' => $screening->movie_id,
            'cinema_id' => $screening->room?->cinema_id,
            'room_id' => $screening->room_id,
            'start_time' => $screening->start_time?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'price' => $screening->price,
            'available_seats' => $screening->available_seats,
            'format' => $screening->format,
            'movie_title' => $screening->movie?->title,
            'cinema_name' => $screening->room?->cinema?->name,
            'room_number' => $screening->room?->number,
        ]);
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
        if ($screening->start_time && $screening->start_time->isPast()) {
            return response()->json([
                'screening_id' => $screening->id,
                'total_seats' => $screening->room->total_seats,
                'booked_seats_count' => 0,
                'available_seats_count' => 0,
                'seats' => [],
            ]);
        }
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
            ->select('id', 'seat_code', 'type', 'row_number', 'seat_number', 'price_modifier')
            ->get();

        $basePrice = (float) $screening->price;
        $availableSeats->transform(function ($seat) use ($basePrice) {
            // Compatibilidad: algunos asientos históricos quedaron con modifier=0.
            // En ese caso usamos precio base (modifier=1.0) para evitar price=0.
            $modifier = (float) ($seat->price_modifier ?? 1);
            if ($modifier <= 0) {
                $modifier = 1.0;
            }
            $seat->price = round($basePrice * $modifier, 2);
            return $seat;
        });

        return response()->json([
            'screening_id' => $screening->id,
            'total_seats' => $screening->room->total_seats,
            'booked_seats_count' => count($blockedSeatIds),
            'available_seats_count' => $availableSeats->count(),
            'seats' => $availableSeats,
        ]);
    }
}
