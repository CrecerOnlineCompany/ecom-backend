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
        'response_data' => 'array',
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
}
