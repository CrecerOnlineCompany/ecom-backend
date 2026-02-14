<?php

namespace App\Models;

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
     */
    public function approve(array $responseData = []): void
    {
        $this->update([
            'status' => 'approved',
            'response_data' => array_merge($this->response_data ?? [], $responseData),
            'completed_at' => now(),
        ]);

        // Confirmar el ticket asociado
        if ($this->ticket) {
            $this->ticket->update(['status' => 'confirmed']);
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
