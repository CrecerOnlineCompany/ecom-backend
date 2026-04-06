<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoomController extends Controller
{
    /**
     * Display a listing of rooms.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Room::with(['cinema']);

        if ($request->has('cinema_id')) {
            $query->where('cinema_id', $request->cinema_id);
        }

        $rooms = $query->where('is_active', true)->paginate(15);
        return response()->json($rooms);
    }

    /**
     * Store a newly created room.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cinema_id' => 'required|exists:cinemas,id',
            'number' => 'required|string',
            'name' => 'required|string',
            'total_seats' => 'required|integer|min:1',
            'type' => 'required|string',
            'rows' => 'required|integer|min:1',
            'columns' => 'required|integer|min:1',
            'non_number' => 'boolean',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $room = Room::create($validated);

        // Auto-generate seats for the room
        $this->generateSeats($room);

        return response()->json($room->load(['seats']), 201);
    }

    /**
     * Display the specified room.
     */
    public function show(Room $room): JsonResponse
    {
        $room->load(['cinema', 'seats']);
        return response()->json($room);
    }

    /**
     * Update the specified room.
     */
    public function update(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'number' => 'sometimes|string',
            'name' => 'sometimes|string',
            'type' => 'sometimes|string',
            'non_number' => 'sometimes|boolean',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $room->update($validated);
        return response()->json($room);
    }

    /**
     * Remove the specified room.
     */
    public function destroy(Room $room): JsonResponse
    {
        $room->delete();
        return response()->json(['message' => 'Room deleted successfully']);
    }

    /**
     * Generate seats for a room
     */
    private function generateSeats(Room $room): void
    {
        $rows = $room->rows;
        $columns = $room->columns;

        for ($row = 1; $row <= $rows; $row++) {
            for ($col = 1; $col <= $columns; $col++) {
                $seatCode = (string) ((($row - 1) * $columns) + $col);

                $room->seats()->create([
                    'row_number' => $row,
                    'seat_number' => $col,
                    'seat_code' => $seatCode,
                    'type' => $row == 1 || $row == $rows ? 'vip' : 'standard',
                    'price_modifier' => $row == 1 || $row == $rows ? 1.5 : 1.0,
                ]);
            }
        }
    }

}
