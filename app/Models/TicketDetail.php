<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'screening_id',
        'seat_id',
        'seat_code',
        'row_number',
        'seat_number',
        'price',
        'status',
        'qr_code',
        'used_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'used_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
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
