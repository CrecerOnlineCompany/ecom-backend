<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProviderTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'order_id',
        'payment_provider_id',
        'status',
        'transaction_id',
        'reference_number',
        'response_data',
        'initiated_at',
        'completed_at',
    ];

    protected $casts = [
        'response_data' => 'json', // JSON en BD, automáticamente decodificado a array
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ============================================================================
    // STATUS CONSTANTS - Using PaymentStatus enum as source of truth
    // ============================================================================
    
    const STATUS_PENDING = PaymentStatus::STATUS_PENDING;           // pending → pending
    const STATUS_PROCESSING = PaymentStatus::STATUS_PROCESSING;     // queued, processing → processing
    const STATUS_COMPLETED = PaymentStatus::STATUS_COMPLETED;       // approved → completed
    const STATUS_FAILED = PaymentStatus::STATUS_FAILED;             // declined, failed, finalization_failed → failed
    const STATUS_CANCELLED = PaymentStatus::STATUS_CANCELLED;
    const STATUS_EXPIRED = PaymentStatus::STATUS_EXPIRED;
    const STATUS_REFUNDED = PaymentStatus::STATUS_REFUNDED;
    
    // Legacy aliases (for backward compatibility during migration)
    const STATUS_APPROVED = PaymentStatus::STATUS_COMPLETED;
    const STATUS_DECLINED = PaymentStatus::STATUS_FAILED;
    const STATUS_QUEUED = PaymentStatus::STATUS_PROCESSING;
    const STATUS_FINALIZATION_FAILED = PaymentStatus::STATUS_FAILED;

    public static array $statuses = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
        self::STATUS_REFUNDED,
    ];

    /**
     * Relación con ticket
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Relación con order (nullable para compatibilidad)
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relación con payment provider
     */
    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class);
    }

    /**
     * Verificar si el pago fue aprobado
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Verificar si el pago está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Marcar pago como aprobado
     * 
     * ORDEN-FIRST:
     * - Si payment tiene order_id: Finalize order (genera tickets, ticket_number, QR)
     * - Si no tiene order_id (legacy): Solo marcar ticket como confirmed
     * 
     * IMPORTANTE: Si la finalización falla, lanza excepción (no marca como approved)
     * Esto permite que el webhook detecte el error y pueda reintentar
     * 
     * @param array $responseData Datos del webhook del provider
     * @throws \Exception Si finalización de orden falla
     */
    public function approve(array $responseData = []): void
    {
        // Ruta 1: Pago vinculado a ORDER (order-first flow)
        if ($this->order_id) {
            \Log::info("PaymentProviderTicket::approve() - Order-first flow", [
                'payment_ticket_id' => $this->id,
                'order_id' => $this->order_id,
            ]);

            // Intentar finalizar orden
            $finalizationService = app(\App\Services\OrderFinalizationService::class);
            $finalizationResult = $finalizationService->finalizeOrderAfterApproval(
                $this->order_id,
                $responseData
            );

            // ⚠️ CRÍTICO: Si finalización falla, lanzar excepción
            if (!($finalizationResult['success'] ?? false)) {
                $errorMsg = $finalizationResult['message'] ?? 'Unknown finalization error';
                $errorCode = $finalizationResult['error_code'] ?? 'FINALIZATION_ERROR';
                
                \Log::error("PaymentProviderTicket::approve() - Order finalization failed", [
                    'payment_ticket_id' => $this->id,
                    'order_id' => $this->order_id,
                    'error_code' => $errorCode,
                    'error_msg' => $errorMsg,
                    'full_result' => $finalizationResult,
                ]);

                // Marcar payment como 'finalization_failed' para auditoría
                $this->update([
                    'status' => 'finalization_failed',
                    'response_data' => array_merge($this->response_data ?? [], [
                        'finalization_error' => $errorMsg,
                        'finalization_error_code' => $errorCode,
                        'failed_at' => now()->toIso8601String(),
                    ]),
                    'completed_at' => now(),
                ]);

                throw new \Exception(
                    "Order finalization failed: {$errorMsg} ({$errorCode})",
                    0
                );
            }

            // Si llegas aquí, finalización fue exitosa
            \Log::info("PaymentProviderTicket::approve() - Order finalized successfully", [
                'payment_ticket_id' => $this->id,
                'order_id' => $this->order_id,
                'finalized_tickets' => $finalizationResult['finalized_tickets'] ?? 0,
            ]);

            // Marcar payment como aprobado
            $this->update([
                'status' => 'approved',
                'response_data' => array_merge($this->response_data ?? [], [
                    'finalization_status' => 'success',
                    'finalized_at' => now()->toIso8601String(),
                ] + $responseData),
                'completed_at' => now(),
            ]);

        } else {
            // Ruta 2: Pago SIN order (legacy flow - vendimia/viejo)
            \Log::info("PaymentProviderTicket::approve() - Legacy flow (no order)", [
                'payment_ticket_id' => $this->id,
                'ticket_id' => $this->ticket_id,
            ]);

            // Solo marcar payment como aprobado, el ticket ya debería existir
            $this->update([
                'status' => 'approved',
                'response_data' => array_merge($this->response_data ?? [], $responseData),
                'completed_at' => now(),
            ]);

            // Si hay ticket vinculado, marcar como confirmed
            if ($this->ticket) {
                $this->ticket->update(['status' => 'confirmed']);
                
                \Log::info("Ticket marcado como confirmed (legacy)", [
                    'ticket_id' => $this->ticket->id,
                    'payment_ticket_id' => $this->id,
                ]);
            }
        }
    }

    /**
     * Marcar pago como rechazado
     */
    public function decline(array $responseData = []): void
    {
        $this->update([
            'status' => 'declined',
            'response_data' => array_merge($this->response_data ?? [], $responseData),
            'completed_at' => now(),
        ]);
    }

    /**
     * Marcar pago como reembolsado
     */
    public function refund(string $reason = ''): void
    {
        $this->update([
            'status' => 'refunded',
            'response_data' => array_merge($this->response_data ?? [], ['refund_reason' => $reason]),
            'completed_at' => now(),
        ]);
    }

    /**
     * Marcar como queued/processing con response_data
     */
    public function markQueued(array $responseData = []): void
    {
        $this->update([
            'status' => 'queued',
            'response_data' => array_merge($this->response_data ?? [], $responseData, [
                'queued_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Marcar como cancelada
     */
    public function markCancelled(string $reason = ''): void
    {
        $this->update([
            'status' => 'cancelled',
            'response_data' => array_merge($this->response_data ?? [], [
                'cancelled_at' => now()->toIso8601String(),
                'cancel_reason' => $reason,
            ]),
            'completed_at' => now(),
        ]);
    }

    /**
     * Marcar como expirada
     */
    public function markExpired(string $reason = ''): void
    {
        $this->update([
            'status' => 'expired',
            'response_data' => array_merge($this->response_data ?? [], [
                'expired_at' => now()->toIso8601String(),
                'expiry_reason' => $reason,
            ]),
            'completed_at' => now(),
        ]);
    }

    /**
     * Obtener del response_data con fallback a null
     */
    public function getResponseData(string $key, $default = null)
    {
        $data = $this->response_data ?? [];
        return data_get($data, $key, $default);
    }

    /**
     * Actualizar response_data mergeando con lo existente
     */
    public function updateResponseData(array $data): void
    {
        $this->update([
            'response_data' => array_merge($this->response_data ?? [], $data),
        ]);
    }

    /**
     * Encontrar por transaction_id o si no existe, por payment_provider_ticket_id
     * Usado en webhooks y status checks
     */
    public static function findByTransactionOrId($transactionId): ?self
    {
        // Primero intentar por transaction_id
        $ticket = static::where('transaction_id', $transactionId)->first();
        
        if ($ticket) {
            return $ticket;
        }
        
        // Fallback: intentar como payment_provider_ticket_id
        if (is_numeric($transactionId)) {
            $ticket = static::find((int)$transactionId);
            if ($ticket) {
                return $ticket;
            }
        }
        
        return null;
    }

    /**
     * Generar external_reference para usar en payment providers
     * Formato: CINEA-ORDER-{order_number}-ATT-{payment_ticket_id}
     * O fallback: CINEA-POINT-{payment_ticket_id} si no hay order
     * O terminal: CINEA-TERMINAL-{payment_ticket_id}
     */
    public function generateExternalReference(string $prefix = 'POINT'): string
    {
        // Si tiene order asociada, usar order_number
        if ($this->order && $this->order->order_number) {
            return "CINEA-ORDER-{$this->order->order_number}-ATT-{$this->id}";
        }
        
        // Fallback a payment_provider por nombre o prefix dado
        if ($this->paymentProvider) {
            if (strpos($this->paymentProvider->name, 'terminal') !== false) {
                return "CINEA-TERMINAL-{$this->id}";
            }
            if (strpos($this->paymentProvider->name, 'qr') !== false) {
                return "CINEA-QR-{$this->id}";
            }
        }
        
        return "CINEA-{$prefix}-{$this->id}";
    }

    /**
     * Scopes para búsquedas comunes
     */
    public function scopeByOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeByTicket($query, $ticketId)
    {
        return $query->where('ticket_id', $ticketId);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }}
