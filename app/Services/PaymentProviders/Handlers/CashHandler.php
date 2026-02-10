<?php

namespace App\Services\PaymentProviders\Handlers;

use App\Services\PaymentProviders\PaymentProviderHandler;
use App\Models\PaymentProviderTicket;
use Illuminate\Http\Request;

class CashHandler extends PaymentProviderHandler
{
    /**
     * Procesar pago en efectivo (sin redirección)
     */
    public function processPayment(PaymentProviderTicket $paymentTicket, array $additionalData = []): array
    {
        try {
            // Para pagos en efectivo, simplemente registrar como pendiente
            // El admin debe confirmar manualmente o a través de un sistema POS
            
            // Usar total_price si está disponible (batch payment de múltiples asientos)
            $amount = !empty($additionalData['total_price']) 
                ? floatval($additionalData['total_price'])
                : $paymentTicket->ticket->price;
            
            $paymentTicket->update([
                'status' => 'pending',
                'initiated_at' => now(),
                'response_data' => [
                    'payment_method' => 'cash',
                    'amount' => $amount,
                    'seat_count' => intval($additionalData['seat_count'] ?? 1),
                ],
            ]);
            
            return [
                'success' => true,
                'requires_redirect' => false,
                'message' => 'Pago en efectivo registrado. Pendiente de confirmación.',
                'payment_id' => $paymentTicket->id,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * No se usa para pagos en efectivo, pero se implementa por compatibilidad
     */
    public function handleWebhook(Request $request): bool
    {
        // Los pagos en efectivo no tienen webhooks
        return false;
    }

    /**
     * Validar configuración
     */
    public function validateConfiguration(): bool
    {
        // Los pagos en efectivo no requieren configuración
        return true;
    }

    /**
     * Refund
     */
    public function refund(PaymentProviderTicket $paymentTicket, string $reason = ''): bool
    {
        try {
            // Marcar como reembolsado
            $paymentTicket->refund($reason ?? 'Reembolso en efectivo');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtener estado de pago
     */
    public function getPaymentStatus(PaymentProviderTicket $paymentTicket): string
    {
        return $paymentTicket->status;
    }
}
