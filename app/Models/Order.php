<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'user_id',
        'screening_id',
        'total_amount',
        'currency',
        'status',
        'purchase_device',
        'ip_address',
        'reserved_until',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'reserved_until' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ============================================================================
    // STATUS CONSTANTS - Using PaymentStatus enum as source of truth
    // ============================================================================
    
    // Primary unified states
    const STATUS_PENDING = PaymentStatus::STATUS_PENDING;           // draft, reserved → pending
    const STATUS_PROCESSING = PaymentStatus::STATUS_PROCESSING;     // payment_processing → processing
    const STATUS_COMPLETED = PaymentStatus::STATUS_COMPLETED;       // paid → completed
    const STATUS_FAILED = PaymentStatus::STATUS_FAILED;             // payment_failed → failed
    const STATUS_CANCELLED = PaymentStatus::STATUS_CANCELLED;
    const STATUS_EXPIRED = PaymentStatus::STATUS_EXPIRED;
    const STATUS_REFUNDED = PaymentStatus::STATUS_REFUNDED;
    
    // Legacy aliases (for backward compatibility during migration)
    const STATUS_DRAFT = PaymentStatus::STATUS_PENDING;
    const STATUS_RESERVED = PaymentStatus::STATUS_PENDING;
    const STATUS_PAYMENT_PROCESSING = PaymentStatus::STATUS_PROCESSING;
    const STATUS_PAID = PaymentStatus::STATUS_COMPLETED;
    const STATUS_PAYMENT_FAILED = PaymentStatus::STATUS_FAILED;

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
     * Relación: Order pertenece a un User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Order pertenece a una Screening (función)
     */
    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    /**
     * Relación: Order tiene muchos Tickets
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Relación: Order tiene muchos PaymentProviderTickets
     */
    public function paymentProviderTickets(): HasMany
    {
        return $this->hasMany(PaymentProviderTicket::class);
    }

    /**
     * Acceso a la película a través de screening
     */
    public function movie()
    {
        return $this->screening->movie();
    }

    /**
     * Acceso al cine a través de screening
     */
    public function cinema()
    {
        return $this->screening->room->cinema();
    }

    /**
     * Acceso a la sala a través de screening
     */
    public function room()
    {
        return $this->screening->room();
    }

    /**
     * Valida si la orden está expirada
     */
    public function isExpired(): bool
    {
        return $this->reserved_until && now()->isAfter($this->reserved_until);
    }

    /**
     * Marca la orden como pagada
     */
    public function markAsPaid(): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    /**
     * Marca la orden como cancelada
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Scopes para filtrar órdenes
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            self::STATUS_DRAFT,
            self::STATUS_RESERVED,
            self::STATUS_PAYMENT_PROCESSING,
        ]);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED)
            ->orWhere(function ($q) {
                $q->where('reserved_until', '<', now())
                  ->whereIn('status', [self::STATUS_RESERVED, self::STATUS_PAYMENT_PROCESSING]);
            });
    }
}
