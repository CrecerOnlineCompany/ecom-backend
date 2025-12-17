<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Screening;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tickets = Ticket::where('user_id', $user->id)
            ->with(['screening.movie', 'screening.room.cinema', 'seat'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($tickets);
    }

    /**
     * Create a new ticket (purchase)
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'screening_id' => 'required|exists:screenings,id',
            'seat_ids' => 'required|array|min:1',
            'seat_ids.*' => 'exists:seats,id',
        ]);

        $screening = Screening::find($validated['screening_id']);

        // Verify seat availability
        $bookedSeats = Ticket::where('screening_id', $screening->id)
            ->whereIn('seat_id', $validated['seat_ids'])
            ->count();

        if ($bookedSeats > 0) {
            return response()->json(['message' => 'One or more seats are already booked'], 422);
        }

        $tickets = [];
        foreach ($validated['seat_ids'] as $seatId) {
            $seat = $screening->room->seats()->find($seatId);
            $price = $screening->price * $seat->price_modifier;
            
            $ticket = Ticket::create([
                'screening_id' => $screening->id,
                'user_id' => $user->id,
                'seat_id' => $seatId,
                'ticket_number' => $this->generateTicketNumber(),
                'price' => $price,
                'status' => 'confirmed',
            ]);

            $tickets[] = $ticket->load(['seat', 'screening.movie']);
        }

        // Update available seats
        $screening->update([
            'available_seats' => $screening->available_seats - count($tickets)
        ]);

        return response()->json([
            'message' => 'Tickets created successfully',
            'tickets' => $tickets,
        ], 201);
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);
        $ticket->load(['screening.movie', 'screening.room.cinema', 'seat']);
        return response()->json($ticket);
    }

    /**
     * Cancel a ticket
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->authorize('delete', $ticket);
        
        if ($ticket->status === 'cancelled') {
            return response()->json(['message' => 'Ticket is already cancelled'], 422);
        }

        // Check if screening has already started
        if ($ticket->screening->start_time < now()) {
            return response()->json(['message' => 'Cannot cancel a ticket for a past screening'], 422);
        }

        $ticket->update(['status' => 'cancelled']);
        $ticket->screening->update([
            'available_seats' => $ticket->screening->available_seats + 1
        ]);

        return response()->json(['message' => 'Ticket cancelled successfully']);
    }

    /**
     * Get user's tickets for a specific screening
     */
    public function screeningTickets(Screening $screening): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tickets = Ticket::where('screening_id', $screening->id)
            ->where('user_id', $user->id)
            ->with(['seat'])
            ->get();

        return response()->json($tickets);
    }

    /**
     * Generate unique ticket number
     */
    private function generateTicketNumber(): string
    {
        return 'TKT-' . date('Ymd') . '-' . strtoupper(Str::random(8));
    }
}
