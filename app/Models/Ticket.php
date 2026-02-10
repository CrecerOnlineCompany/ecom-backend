<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        // Información de asiento
        'seat_code',
        'row_number',
        'seat_number',
        // Información desnormalizada del cliente
        'customer_name',
        'customer_email',
        'customer_phone',
        // Información desnormalizada de la función
        'movie_title',
        'room_name',
        'cinema_name',
        'screening_start_time',
        'screening_format',
        // Metadatos para estadísticas
        'payment_method',
        'original_price',
        'discount_amount',
        'discount_code',
        'purchased_at',
        'purchase_device',
        'ip_address',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'used_at' => 'datetime',
        'screening_start_time' => 'datetime',
        'purchased_at' => 'datetime',
    ];

    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con asiento individual (opcional, para compatibilidad)
     * En la mayoría de casos, los asientos están en ticket_details
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function movie()
    {
        return $this->screening->movie();
    }

    public function paymentProviders(): HasMany
    {
        return $this->hasMany(PaymentProviderTicket::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(TicketDetail::class);
    }

    /**
     * Rellena automáticamente los campos desnormalizados del ticket
     * Esto mantiene la tabla de tickets independiente para estadísticas
     */
    public function populateDenormalizedFields(): void
    {
        // Información del cliente
        if ($this->user) {
            $this->customer_name = $this->user->name;
            $this->customer_email = $this->user->email;
            $this->customer_phone = $this->user->phone ?? null;
        }

        // Información de la función
        if ($this->screening) {
            $this->screening_start_time = $this->screening->start_time;
            $this->screening_format = $this->screening->format;

            // Información de la película
            if ($this->screening->movie) {
                $this->movie_title = $this->screening->movie->title;
            }

            // Información de la sala y cine
            if ($this->screening->room) {
                $this->room_name = $this->screening->room->name;

                if ($this->screening->room->cinema) {
                    $this->cinema_name = $this->screening->room->cinema->name;
                }
            }
        }

        // Información de asiento
        if ($this->seat) {
            $this->seat_code = $this->seat->seat_code;
            $this->row_number = $this->seat->row_number;
            $this->seat_number = $this->seat->seat_number;
        }

        // Fecha de compra (si no está establecida, usar fecha actual)
        if (!$this->purchased_at) {
            $this->purchased_at = now();
        }
    }
}
