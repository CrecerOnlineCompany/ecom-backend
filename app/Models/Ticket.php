<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'screening_id',
        'user_id',
        'seat_id',
        'ticket_number',
        'price',
        'status',
        'qr_code',
        'used_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'used_at' => 'datetime',
    ];

    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function movie()
    {
        return $this->screening->movie();
    }
}
