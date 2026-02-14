<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningSeat extends Model
{
    use HasFactory;

    protected $table = 'screening_seats';

    protected $fillable = [
        'screening_id',
        'seat_id',
        'status',
        'reserved_until',
        'order_id',
        'reserved_by_type',
        'reserved_by_id',
        'sold_at',
    ];

    protected $casts = [
        'reserved_until' => 'datetime',
        'sold_at' => 'datetime',
    ];

    // Constants for status
    const STATUS_AVAILABLE = 'available';
    const STATUS_RESERVED = 'reserved';
    const STATUS_SOLD = 'sold';

    public static array $statuses = [
        self::STATUS_AVAILABLE,
        self::STATUS_RESERVED,
        self::STATUS_SOLD,
    ];

    /**
     * Relación: ScreeningSeat pertenece a Screening
     */
    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    /**
     * Relación: ScreeningSeat pertenece a Seat
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /**
     * Relación: ScreeningSeat puede pertenecer a Order (nullable)
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Verifica si la reserva ha expirado
     */
    public function isReservationExpired(): bool
    {
        return $this->reserved_until && now()->isAfter($this->reserved_until);
    }

    /**
     * Verifica si el asiento está efectivamente disponible
     * (considera reservas expiradas como disponibles)
     */
    public function isEffectivelyAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE
            || ($this->status === self::STATUS_RESERVED && $this->isReservationExpired());
    }

    /**
     * Scopes
     */
    public function scopeForScreening($query, $screening_id)
    {
        return $query->where('screening_id', $screening_id);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeReserved($query)
    {
        return $query->where('status', self::STATUS_RESERVED);
    }

    public function scopeSold($query)
    {
        return $query->where('status', self::STATUS_SOLD);
    }

    public function scopeExpiredReservations($query)
    {
        return $query->where('status', self::STATUS_RESERVED)
            ->where('reserved_until', '<', now());
    }
}
