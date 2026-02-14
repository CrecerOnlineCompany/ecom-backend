<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MpTerminalOrder extends Model
{
    protected $table = 'mp_terminal_orders';

    protected $fillable = [
        'payment_provider_id',
        'payment_provider_ticket_id',
        'terminal_id',
        'order_id',
        'external_reference',
        'status',
        'response_data',
        'cancel_reason',
        'last_checked_at',
        'cancelled_at',
    ];

    protected $casts = [
        'response_data' => 'json',
        'created_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Relación: belongs to PaymentProvider
     */
    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'payment_provider_id');
    }

    /**
     * Relación: belongs to PaymentProviderTicket
     */
    public function paymentProviderTicket(): BelongsTo
    {
        return $this->belongsTo(PaymentProviderTicket::class, 'payment_provider_ticket_id');
    }

    /**
     * Obtener la orden más reciente activa por terminal
     */
    public static function getActiveOrderByTerminal(int $providerId, string $terminalId): ?self
    {
        return self::where('payment_provider_id', $providerId)
            ->where('terminal_id', $terminalId)
            ->whereIn('status', ['active', 'pending', 'processing'])
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Obtener órdenes recientes no resueltas (últimas N horas)
     */
    public static function getUnresolvedByTerminal(int $providerId, string $terminalId, int $lookbackMinutes = 120): array
    {
        return self::where('payment_provider_id', $providerId)
            ->where('terminal_id', $terminalId)
            ->whereIn('status', ['active', 'pending', 'processing'])
            ->where('created_at', '>=', now()->subMinutes($lookbackMinutes))
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Marcar como activa
     */
    public function markActive(): self
    {
        $this->update([
            'status' => 'active',
            'last_checked_at' => now(),
        ]);
        return $this;
    }

    /**
     * Marcar como cancelada
     */
    public function markCancelled(string $reason = null): self
    {
        $this->update([
            'status' => 'cancelled',
            'cancel_reason' => $reason,
            'cancelled_at' => now(),
            'last_checked_at' => now(),
        ]);
        return $this;
    }

    /**
     * Marcar como expirada
     */
    public function markExpired(): self
    {
        $this->update([
            'status' => 'expired',
            'last_checked_at' => now(),
        ]);
        return $this;
    }

    /**
     * Actualizar response_data con merge, sin perder datos viejos
     */
    public function mergeResponseData(array $newData): self
    {
        $existing = $this->response_data ?? [];
        $this->update([
            'response_data' => array_merge($existing, $newData),
            'last_checked_at' => now(),
        ]);
        return $this;
    }
}
