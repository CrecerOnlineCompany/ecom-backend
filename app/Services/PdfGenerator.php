<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use App\Services\QRCodeGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

/**
 * Service for generating PDF documents for tickets and orders
 * 
 * Generates dynamic PDFs with:
 * - QR codes linking to ticket verification
 * - Complete ticket details
 * - Customer information
 * - Screening information
 * - Seat information
 */
class PdfGenerator
{
    /**
     * Generate PDF for a single ticket
     * 
     * @param Ticket $ticket
     * @param Order $order
     * @return string PDF content as binary string
     * @throws Exception
     */
    public function generateTicketPdf(Ticket $ticket, Order $order): string
    {
        try {
            // Generate QR code verify URL
            $verifyUrl = config('app.url') . '/api/tickets/' . $ticket->id . '/validate';
            $qrCodeBase64 = $this->generateQrCode($verifyUrl);

            // Prepare ticket data
            $data = [
                'ticket' => $ticket,
                'order' => $order,
                'screening' => $ticket->screening,
                'movie' => $ticket->screening->movie,
                'cinema' => $ticket->screening->room->cinema,
                'room' => $ticket->screening->room,
                'qr_code' => $qrCodeBase64,
                'verify_url' => $verifyUrl,
                'seats' => $ticket->details->map(fn ($detail) => [
                    'seat_code' => $detail->seat_code,
                    'row_number' => $detail->row_number,
                    'seat_number' => $detail->seat_number,
                    'price' => number_format($detail->price, 2, '.', ','),
                ]),
            ];

            // Generate PDF from blade template
            $pdf = Pdf::loadView('pdf.ticket', $data);
            
            // Configure PDF options
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('margin-top', 5);
            $pdf->setOption('margin-right', 5);
            $pdf->setOption('margin-bottom', 5);
            $pdf->setOption('margin-left', 5);

            return $pdf->output();

        } catch (Exception $e) {
            throw new Exception('Error generating ticket PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate thermal-printer-friendly PDF for a ticket.
     *
     * @param Ticket $ticket
     * @return string PDF content as binary string
     * @throws Exception
     */
    public function generateThermalTicketPdf(Ticket $ticket): string
    {
        try {
            $ticket->loadMissing(['details', 'screening.movie', 'screening.room.cinema', 'order', 'seat']);

            $verifyUrl = config('app.url') . '/api/tickets/' . $ticket->id . '/validate';
            $qrCodeBase64 = $this->generateQrCode($verifyUrl);

            $seats = $ticket->details->map(fn ($detail) => [
                'seat_code' => $detail->seat_code,
                'row_number' => $detail->row_number,
                'seat_number' => $detail->seat_number,
                'price' => number_format($detail->price, 2, '.', ','),
            ]);

            // Fallback for legacy/single-seat tickets without ticket_details
            if ($seats->isEmpty()) {
                $fallbackSeatCode = $ticket->seat_code ?: $ticket->seat?->seat_code;
                if ($fallbackSeatCode) {
                    $seats = collect([[
                        'seat_code' => $fallbackSeatCode,
                        'row_number' => $ticket->row_number ?: $ticket->seat?->row_number,
                        'seat_number' => $ticket->seat_number ?: $ticket->seat?->seat_number,
                        'price' => number_format((float) $ticket->price, 2, '.', ','),
                    ]]);
                }
            }

            $data = [
                'ticket' => $ticket,
                'screening' => $ticket->screening,
                'movie' => $ticket->screening?->movie,
                'cinema' => $ticket->screening?->room?->cinema,
                'room' => $ticket->screening?->room,
                'order' => $ticket->order,
                'seats' => $seats,
                'qr_code' => $qrCodeBase64,
                'verify_url' => $verifyUrl,
            ];

            // 80mm width in points (1mm = 2.83465pt). Height is dynamic by seat count.
            $paperWidth = 226.77;
            $paperHeight = max(500, 420 + ($seats->count() * 24));

            $pdf = Pdf::loadView('pdf.ticket-thermal', $data);
            $pdf->setPaper([0, 0, $paperWidth, $paperHeight], 'portrait');
            $pdf->setOption('margin-top', 3);
            $pdf->setOption('margin-right', 3);
            $pdf->setOption('margin-bottom', 3);
            $pdf->setOption('margin-left', 3);

            return $pdf->output();
        } catch (Exception $e) {
            throw new Exception('Error generating thermal ticket PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate PDF for entire order with all tickets
     * 
     * @param Order $order
     * @return string PDF content as binary string
     * @throws Exception
     */
    public function generateOrderPdf(Order $order): string
    {
        try {
            // Load order with all relationships
            $order->load([
                'tickets' => function ($query) {
                    $query->with(['details', 'screening.movie', 'screening.room.cinema']);
                },
                'screening.movie',
                'screening.room.cinema'
            ]);

            // Generate QR codes for all tickets
            $ticketsData = $order->tickets->map(function ($ticket) {
                $verifyUrl = config('app.url') . '/api/tickets/' . $ticket->id . '/validate';
                $qrCodeBase64 = $this->generateQrCode($verifyUrl);

                return [
                    'ticket' => $ticket,
                    'qr_code' => $qrCodeBase64,
                    'verify_url' => $verifyUrl,
                    'seats' => $ticket->details->map(fn ($detail) => [
                        'seat_code' => $detail->seat_code,
                        'row_number' => $detail->row_number,
                        'seat_number' => $detail->seat_number,
                        'price' => number_format($detail->price, 2, '.', ','),
                    ]),
                ];
            });

            // Prepare order data
            $data = [
                'order' => $order,
                'screening' => $order->screening,
                'movie' => $order->screening->movie,
                'cinema' => $order->screening->room->cinema,
                'room' => $order->screening->room,
                'tickets' => $ticketsData,
                'total_amount' => number_format($order->total_amount, 2, '.', ','),
            ];

            // Generate PDF from blade template
            $pdf = Pdf::loadView('pdf.order', $data);
            
            // Configure PDF options
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('margin-top', 5);
            $pdf->setOption('margin-right', 5);
            $pdf->setOption('margin-bottom', 5);
            $pdf->setOption('margin-left', 5);

            return $pdf->output();

        } catch (Exception $e) {
            throw new Exception('Error generating order PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate QR code as base64 data URI
     * 
     * @param string $data Text/URL to encode in QR
     * @return string Base64 data URI for embedding in HTML
     */
    private function generateQrCode(string $data): string
    {
        try {
            // Reuse project QR service (loads local phpqrcode and has fallback strategy)
            return QRCodeGenerator::generateQRDataUri($data, 300);

        } catch (Exception $e) {
            // Fallback: return empty data URI if QR generation fails
            \Log::error('Error generating QR code: ' . $e->getMessage());
            return 'data:image/png;base64,';
        }
    }
}
