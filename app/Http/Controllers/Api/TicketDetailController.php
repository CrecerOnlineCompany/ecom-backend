<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TicketDetailController extends Controller
{
    /**
     * Get all ticket details for a ticket
     */
    public function index(Request $request): JsonResponse
    {
        $ticketId = $request->query('ticket_id');
        
        if (!$ticketId) {
            return response()->json(['message' => 'ticket_id is required'], 400);
        }

        $details = TicketDetail::where('ticket_id', $ticketId)
            ->with(['ticket', 'screening.movie', 'seat'])
            ->get();

        return response()->json($details);
    }

    /**
     * Get a specific ticket detail
     */
    public function show(TicketDetail $detail): JsonResponse
    {
        $detail->load(['ticket', 'screening.movie', 'seat']);
        return response()->json($detail);
    }

    /**
     * Update ticket detail status (validar entrada, cancelar, etc)
     */
    public function update(Request $request, TicketDetail $detail): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:confirmed,cancelled,used',
            'used_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        try {
            $detail->update($validated);
            return response()->json([
                'message' => 'Ticket detail updated successfully',
                'detail' => $detail,
            ]);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Error updating ticket detail: ' . $e->getMessage()],
                422
            );
        }
    }

    /**
     * Validate a ticket by QR code
     */
    public function validateByQR(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $detail = TicketDetail::where('qr_code', $validated['qr_code'])->first();

        if (!$detail) {
            return response()->json(['message' => 'Entrada no encontrada'], 404);
        }

        if ($detail->status === 'used') {
            return response()->json([
                'message' => 'Esta entrada ya fue validada',
                'used_at' => $detail->used_at,
            ], 422);
        }

        if ($detail->status === 'cancelled') {
            return response()->json(['message' => 'Esta entrada fue cancelada'], 422);
        }

        // Marcar como usada
        $detail->update([
            'status' => 'used',
            'used_at' => now(),
        ]);

        return response()->json([
            'message' => 'Entrada validada exitosamente',
            'detail' => $detail->load(['ticket', 'screening.movie']),
        ]);
    }

    /**
     * Get ticket details for a screening (admin)
     */
    public function byScreening(Request $request): JsonResponse
    {
        $screeningId = $request->query('screening_id');
        
        if (!$screeningId) {
            return response()->json(['message' => 'screening_id is required'], 400);
        }

        $details = TicketDetail::where('screening_id', $screeningId)
            ->with(['ticket', 'seat'])
            ->get()
            ->groupBy('status');

        return response()->json([
            'total' => TicketDetail::where('screening_id', $screeningId)->count(),
            'confirmed' => count($details->get('confirmed', [])),
            'cancelled' => count($details->get('cancelled', [])),
            'used' => count($details->get('used', [])),
            'by_status' => $details,
        ]);
    }
}
