<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\PdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Validate and retrieve order details with associated tickets
     * GET /api/orders/{order_number}/validate
     */
    public function validateOrder(Request $request, string $orderNumber): JsonResponse
    {
        try {
            $order = Order::where('order_number', $orderNumber)
                ->with([
                    'tickets' => function ($query) {
                        $query->with(['details.seat', 'screening.movie', 'screening.room.cinema']);
                    },
                    'screening.movie',
                    'screening.room.cinema',
                    'user'
                ])
                ->firstOrFail();

            $this->authorize('view', $order);

            $tickets = $order->tickets->map(fn ($ticket) => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'price' => $ticket->price,
                'movie_title' => $ticket->screening->movie->title,
                'screening_date' => $ticket->screening->start_time->format('Y-m-d'),
                'screening_time' => $ticket->screening->start_time->format('H:i'),
                'cinema' => $ticket->screening->room->cinema->name,
                'room' => $ticket->screening->room->name,
                'customer_name' => $ticket->customer_name,
                'customer_email' => $ticket->customer_email,
                'seats_count' => $ticket->details->count(),
                'seats' => $ticket->details->map(fn ($detail) => [
                    'id' => $detail->id,
                    'seat_code' => $detail->seat_code,
                    'row_number' => $detail->row_number,
                    'seat_number' => $detail->seat_number,
                    'room_non_number' => (bool) ($detail->room_non_number ?? false),
                    'status' => $detail->status,
                    'price' => $detail->price,
                ]),
            ]);

            return response()->json([
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'uuid' => $order->uuid,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'customer_phone' => $order->customer_phone,
                    'movie_title' => $order->screening->movie->title,
                    'screening_date' => $order->screening->start_time->format('Y-m-d'),
                    'screening_time' => $order->screening->start_time->format('H:i'),
                    'cinema' => $order->screening->room->cinema->name,
                    'room' => $order->screening->room->name,
                    'purchased_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'paid_at' => $order->paid_at?->format('Y-m-d H:i:s'),
                ],
                'tickets' => $tickets,
                'tickets_count' => $tickets->count(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada',
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado para ver esta orden',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error validando orden: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate PDF for a specific ticket in an order
     * GET /api/orders/{order_number}/tickets/{ticket_id}/pdf
     */
    public function getTicketPdf(Request $request, string $orderNumber, int $ticketId): JsonResponse
    {
        try {
            $order = Order::where('order_number', $orderNumber)->firstOrFail();
            
            $ticket = Ticket::where('id', $ticketId)
                ->where('order_id', $order->id)
                ->with(['details', 'screening.movie', 'screening.room.cinema'])
                ->firstOrFail();

            $this->authorize('view', $order);

            $pdfGenerator = new PdfGenerator();
            $pdf = $pdfGenerator->generateTicketPdf($ticket, $order);

            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', sprintf(
                    'inline; filename="ticket-%s.pdf"',
                    $ticket->ticket_number
                ));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Orden o ticket no encontrado',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generando PDF: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate PDF for entire order with all tickets
     * GET /api/orders/{order_number}/pdf
     */
    public function getOrderPdf(Request $request, string $orderNumber): JsonResponse
    {
        try {
            $order = Order::where('order_number', $orderNumber)
                ->with([
                    'tickets' => function ($query) {
                        $query->with(['details', 'screening.movie', 'screening.room.cinema']);
                    },
                    'screening.movie',
                    'screening.room.cinema'
                ])
                ->firstOrFail();

            $this->authorize('view', $order);

            $pdfGenerator = new PdfGenerator();
            $pdf = $pdfGenerator->generateOrderPdf($order);

            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', sprintf(
                    'inline; filename="order-%s.pdf"',
                    $order->order_number
                ));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generando PDF: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get order by UUID (alternative validation endpoint)
     * GET /api/orders/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $order = Order::where('uuid', $uuid)
                ->with([
                    'tickets' => function ($query) {
                        $query->with(['details.seat', 'screening.movie', 'screening.room.cinema']);
                    },
                    'screening.movie',
                    'screening.room.cinema'
                ])
                ->firstOrFail();

            // Authorization check
            if (auth()->check()) {
                $this->authorize('view', $order);
            }

            $tickets = $order->tickets->map(fn ($ticket) => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'price' => $ticket->price,
                'movie_title' => $ticket->screening->movie->title,
                'seats_count' => $ticket->details->count(),
            ]);

            return response()->json([
                'success' => true,
                'order' => [
                    'uuid' => $order->uuid,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                ],
                'tickets' => $tickets,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada',
            ], 404);
        }
    }
}
