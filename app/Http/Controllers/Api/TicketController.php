<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketDetail;
use App\Models\Screening;
use App\Models\Seat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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
            ->with(['details', 'screening.movie', 'screening.room.cinema'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($tickets);
    }

    /**
     * Create a new ticket (purchase) con múltiples asientos
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
            'seat_ids.*' => 'required|integer|exists:seats,id',
            'payment_method' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated, $user, $request) {
                $screening = Screening::with('movie', 'room.cinema')->findOrFail($validated['screening_id']);
                $seatIds = $validated['seat_ids'];

                // Verificar disponibilidad de asientos
                $occupiedDetails = TicketDetail::where('screening_id', $screening->id)
                    ->whereIn('seat_id', $seatIds)
                    ->whereIn('status', ['confirmed', 'used'])
                    ->count();

                if ($occupiedDetails > 0) {
                    return response()->json(
                        ['message' => 'Uno o más asientos ya están reservados'],
                        422
                    );
                }

                // Obtener info de asientos
                $seats = Seat::whereIn('id', $seatIds)->get();
                $totalPrice = 0;
                $seatDetails = [];

                foreach ($seats as $seat) {
                    $seatPrice = $screening->price * (1 + $seat->price_modifier);
                    $totalPrice += $seatPrice;
                    $seatDetails[$seat->id] = [
                        'seat' => $seat,
                        'price' => $seatPrice,
                    ];
                }

                // Crear ticket (transacción de compra)
                $ticket = Ticket::create([
                    'screening_id' => $screening->id,
                    'user_id' => $user->id,
                    'ticket_number' => $this->generateTicketNumber(),
                    'price' => $totalPrice,
                    'status' => 'pending_payment',
                    'payment_method' => $validated['payment_method'] ?? null,
                    // Datos desnormalizados
                    'customer_name' => $user->name,
                    'customer_email' => $user->email,
                    'customer_phone' => $user->phone ?? null,
                    'movie_title' => $screening->movie->title,
                    'room_name' => $screening->room->name,
                    'cinema_name' => $screening->room->cinema->name,
                    'screening_start_time' => $screening->start_time,
                    'screening_format' => $screening->format,
                    'purchased_at' => now(),
                ]);

                // Crear ticket_details para cada asiento
                $details = [];
                foreach ($seatIds as $seatId) {
                    $seatData = $seatDetails[$seatId];
                    $details[] = [
                        'ticket_id' => $ticket->id,
                        'screening_id' => $screening->id,
                        'seat_id' => $seatId,
                        'seat_code' => $seatData['seat']->seat_code,
                        'row_number' => $seatData['seat']->row_number,
                        'seat_number' => $seatData['seat']->seat_number,
                        'price' => $seatData['price'],
                        'status' => 'confirmed',
                        'qr_code' => $this->generateQRCode($ticket->ticket_number . '-' . $seatData['seat']->seat_code),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                TicketDetail::insert($details);

                // Recargar para obtener relaciones
                $ticket->load('details');

                return response()->json([
                    'message' => 'Entradas creadas exitosamente',
                    'ticket' => $ticket,
                    'total_seats' => count($seatIds),
                    'total_price' => $totalPrice,
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Error creando entradas: ' . $e->getMessage()],
                422
            );
        }
    }

    /**
     * Display the specified ticket with all details.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);
        $ticket->load(['details', 'screening.movie', 'screening.room.cinema']);
        return response()->json($ticket);
    }

    /**
     * Update ticket (cambiar estado, actualizar datos desnormalizados, etc)
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'status' => 'nullable|in:pending_payment,confirmed,cancelled',
            'payment_method' => 'nullable|string',
            'discount_code' => 'nullable|string',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $ticket->update($validated);
            return response()->json([
                'message' => 'Ticket actualizado exitosamente',
                'ticket' => $ticket->load('details'),
            ]);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Error actualizando ticket: ' . $e->getMessage()],
                422
            );
        }
    }

    /**
     * Cancel a ticket (cancela todos los detalles)
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->authorize('delete', $ticket);
        
        if ($ticket->status === 'cancelled') {
            return response()->json(['message' => 'La entrada ya está cancelada'], 422);
        }

        // Verificar si la función ya empezó
        if ($ticket->screening->start_time < now()) {
            return response()->json(['message' => 'No se puede cancelar una entrada de una función pasada'], 422);
        }

        try {
            return DB::transaction(function () use ($ticket) {
                // Cancelar todos los detalles de esta entrada
                TicketDetail::where('ticket_id', $ticket->id)
                    ->whereIn('status', ['confirmed', 'used'])
                    ->update(['status' => 'cancelled']);

                // Actualizar estado del ticket
                $ticket->update(['status' => 'cancelled']);

                // Los observers actualizarán automáticamente available_seats

                return response()->json(['message' => 'Entrada cancelada exitosamente']);
            });
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Error cancelando entrada: ' . $e->getMessage()],
                422
            );
        }
    }

    /**
     * Get ticket details (asientos de un ticket)
     */
    public function getDetails(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);
        
        $details = $ticket->details()->with(['seat'])->get();

        return response()->json([
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'total_seats' => $details->count(),
            'confirmed' => $details->where('status', 'confirmed')->count(),
            'cancelled' => $details->where('status', 'cancelled')->count(),
            'used' => $details->where('status', 'used')->count(),
            'details' => $details,
        ]);
    }

    /**
     * Update a specific seat (ticket detail) within a ticket
     */
    public function updateSeat(Request $request, Ticket $ticket, TicketDetail $detail): JsonResponse
    {
        $this->authorize('update', $ticket);

        // Verificar que el detail pertenece al ticket
        if ($detail->ticket_id !== $ticket->id) {
            return response()->json(['message' => 'El asiento no pertenece a esta entrada'], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:confirmed,cancelled,used',
            'used_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        try {
            return DB::transaction(function () use ($detail, $validated) {
                $detail->update($validated);
                return response()->json([
                    'message' => 'Asiento actualizado exitosamente',
                    'detail' => $detail,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Error actualizando asiento: ' . $e->getMessage()],
                422
            );
        }
    }

    /**
     * Cancel a specific seat (ticket_detail) within a ticket
     */
    public function cancelSeat(Ticket $ticket, TicketDetail $detail): JsonResponse
    {
        $this->authorize('delete', $ticket);

        // Verificar que el detail pertenece al ticket
        if ($detail->ticket_id !== $ticket->id) {
            return response()->json(['message' => 'El asiento no pertenece a esta entrada'], 422);
        }

        if ($detail->status === 'cancelled' || $detail->status === 'used') {
            return response()->json(['message' => 'Este asiento no puede ser cancelado'], 422);
        }

        try {
            $detail->update(['status' => 'cancelled']);
            return response()->json(['message' => 'Asiento cancelado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Error cancelando asiento: ' . $e->getMessage()],
                422
            );
        }
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
            ->with('details')
            ->get();

        return response()->json($tickets);
    }

    /**
     * Validate a ticket by QR code (sin autenticación requerida)
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

        try {
            return DB::transaction(function () use ($detail) {
                // Marcar como usada
                $detail->update([
                    'status' => 'used',
                    'used_at' => now(),
                ]);

                return response()->json([
                    'message' => 'Entrada validada exitosamente',
                    'detail' => $detail->load(['ticket', 'screening.movie']),
                ], 200);
            });
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Error validando entrada: ' . $e->getMessage()],
                422
            );
        }
    }

    /**
     * Get statistics for a screening (admin)
     */
    public function screeningStats(Screening $screening): JsonResponse
    {
        $details = TicketDetail::where('screening_id', $screening->id)
            ->get()
            ->groupBy('status');

        return response()->json([
            'screening_id' => $screening->id,
            'total_details' => TicketDetail::where('screening_id', $screening->id)->count(),
            'confirmed' => count($details->get('confirmed', [])),
            'cancelled' => count($details->get('cancelled', [])),
            'used' => count($details->get('used', [])),
            'by_status' => $details,
        ]);
    }

    /**
     * Generate unique ticket number
     */
    private function generateTicketNumber(): string
    {
        return 'TKT-' . date('Ymd') . '-' . strtoupper(Str::random(8));
    }

    /**
     * Generate QR code
     */
    private function generateQRCode(string $data): string
    {
        // En producción, usar una librería de QR como BaconQrCode
        // Por ahora, retornar un hash
        return hash('sha256', $data);
    }

    public function validateTicketForPayment($ticketId): JsonResponse
    {
        try {
            $ticket = Ticket::with(['details', 'screening.movie', 'screening.room.cinema'])
                ->findOrFail($ticketId);

            if ($ticket->status !== 'pending_payment') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket inválido o ya procesado',
                    'status' => $ticket->status,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ticket válido',
                'ticket' => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->status,
                    'total_price' => $ticket->price,
                    'seats_count' => $ticket->details->count(),
                    'movie_title' => $ticket->screening->movie->title,
                    'screening_date' => $ticket->screening->start_time,
                    'cinema' => $ticket->screening->room->cinema->name,
                    'room' => $ticket->screening->room->name,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket no encontrado',
            ], 404);
        }
    }

    public function getTicketQR($ticketId): JsonResponse
    {
        try {
            $ticket = Ticket::with(['details', 'screening.movie', 'screening.room.cinema'])
                ->findOrFail($ticketId);

            if ($ticket->status === 'payment_failed' || $ticket->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede generar QR para este ticket',
                ], 422);
            }

            $details = [];
            foreach ($ticket->details as $detail) {
                $details[] = [
                    'id' => $detail->id,
                    'seat_code' => $detail->seat_code,
                    'qr_code' => $detail->qr_code,
                ];
            }

            return response()->json([
                'success' => true,
                'ticket' => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'movie_title' => $ticket->screening->movie->title,
                    'screening_date' => $ticket->screening->start_time,
                    'cinema' => $ticket->screening->room->cinema->name,
                    'room' => $ticket->screening->room->name,
                    'customer_name' => $ticket->customer_name,
                    'customer_email' => $ticket->customer_email,
                    'price' => $ticket->price,
                ],
                'seats' => $details,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket no encontrado',
            ], 404);
        }
    }

    public function getTicketDetails($ticketId): JsonResponse
    {
        try {
            $ticket = Ticket::with(['details.seat', 'screening.movie', 'screening.room.cinema'])
                ->findOrFail($ticketId);

            $seatsInfo = [];
            foreach ($ticket->details as $detail) {
                $seatsInfo[] = [
                    'id' => $detail->id,
                    'seat_number' => $detail->seat_number,
                    'row_number' => $detail->row_number,
                    'seat_code' => $detail->seat_code,
                    'price' => $detail->price,
                    'status' => $detail->status,
                    'qr_code' => $detail->qr_code,
                ];
            }

            return response()->json([
                'success' => true,
                'ticket' => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->status,
                    'movie_title' => $ticket->screening->movie->title,
                    'screening_date' => $ticket->screening->start_time->format('Y-m-d'),
                    'screening_time' => $ticket->screening->start_time->format('H:i'),
                    'cinema' => $ticket->screening->room->cinema->name,
                    'room' => $ticket->screening->room->name,
                    'customer_name' => $ticket->customer_name,
                    'customer_email' => $ticket->customer_email,
                    'customer_phone' => $ticket->customer_phone,
                    'total_price' => $ticket->price,
                    'purchased_at' => $ticket->purchased_at,
                    'seats' => $seatsInfo,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket no encontrado',
            ], 404);
        }
    }

    public function getThermalPrintFormat($ticketId): JsonResponse
    {
        try {
            $ticket = Ticket::with(['details.seat', 'screening.movie', 'screening.room.cinema'])
                ->findOrFail($ticketId);

            $printContent = [
                'ticket_number' => $ticket->ticket_number,
                'cinema' => $ticket->screening->room->cinema->name,
                'room' => $ticket->screening->room->name,
                'movie' => $ticket->screening->movie->title,
                'screening_date' => $ticket->screening->start_time->format('d/m/Y'),
                'screening_time' => $ticket->screening->start_time->format('H:i'),
                'seats' => [],
                'total_price' => $ticket->price,
                'purchased_at' => $ticket->purchased_at->format('d/m/Y H:i'),
                'customer_name' => $ticket->customer_name,
            ];

            foreach ($ticket->details as $detail) {
                $printContent['seats'][] = [
                    'seat_code' => $detail->seat_code,
                    'row_number' => $detail->row_number,
                    'seat_number' => $detail->seat_number,
                    'price' => number_format($detail->price, 2, ',', '.'),
                    'qr_code' => $detail->qr_code,
                ];
            }

            return response()->json([
                'success' => true,
                'print_format' => [
                    'type' => 'thermal_printer',
                    'width' => 80,
                    'encoding' => 'UTF-8',
                    'content' => $printContent,
                    'html' => $this->generateThermalHTML($printContent),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket no encontrado',
            ], 404);
        }
    }

    private function generateThermalHTML(array $content): string
    {
        $html = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: monospace; width: 80mm; margin: 0; padding: 5mm; }
                .header { text-align: center; font-weight: bold; margin-bottom: 10px; }
                .divider { border-top: 1px dashed #000; margin: 5px 0; }
                .row { display: flex; justify-content: space-between; margin: 2px 0; }
                .seats { margin: 10px 0; }
                .seat-item { margin: 5px 0; }
                .total { text-align: center; font-weight: bold; margin-top: 10px; }
                .qr { text-align: center; margin: 10px 0; }
                .footer { text-align: center; font-size: 10px; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <div>" . htmlspecialchars($content['cinema']) . "</div>
                <div>" . htmlspecialchars($content['room']) . "</div>
            </div>
            <div class='divider'></div>
            
            <div>Película: " . htmlspecialchars($content['movie']) . "</div>
            <div>Fecha: " . htmlspecialchars($content['screening_date']) . "</div>
            <div>Hora: " . htmlspecialchars($content['screening_time']) . "</div>
            
            <div class='divider'></div>
            
            <div class='seats'>";
        
        foreach ($content['seats'] as $seat) {
            $html .= "
                <div class='seat-item'>
                    <div>Asiento: " . htmlspecialchars($seat['seat_code']) . "</div>
                    <div>Precio: \$" . htmlspecialchars($seat['price']) . "</div>
                </div>";
        }
        
        $html .= "
            </div>
            
            <div class='divider'></div>
            
            <div class='total'>
                Total: \$" . number_format($content['total_price'], 2, ',', '.') . "
            </div>
            
            <div class='total'>
                Boleto: " . htmlspecialchars($content['ticket_number']) . "
            </div>
            
            <div class='footer'>
                Comprado: " . htmlspecialchars($content['purchased_at']) . "<br>
                Cliente: " . htmlspecialchars($content['customer_name']) . "<br>
                Válido solo con presentación del QR
            </div>
        </body>
        </html>";
        
        return $html;
    }
}
