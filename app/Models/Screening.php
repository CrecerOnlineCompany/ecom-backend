<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Screening extends Model
{
    use HasFactory;

    protected $fillable = [
        'movie_id',
        'room_id',
        'start_time',
        'end_time',
        'price',
        'available_seats',
        'format',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'price' => 'decimal:2',
    ];

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function adminExcludedReservations(): HasMany
    {
        return $this->hasMany(ScreeningSeat::class)
            ->where('reserved_by_type', 'admin_exclusion');
    }

    /**
     * Campo virtual usado en OpenAdmin. No se persiste en DB.
     */
    public function setExcludedSeatIdsAttribute($value): void
    {
        // noop: evita "Unknown column excluded_seat_ids" en INSERT/UPDATE.
    }

    public function cinema()
    {
        return $this->room->cinema();
    }
}
