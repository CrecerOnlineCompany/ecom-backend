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
    }}
