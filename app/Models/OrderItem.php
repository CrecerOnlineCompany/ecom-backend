<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    public const TYPE_TICKET_SEAT = 'ticket_seat';
    public const TYPE_PROMOTION_DISCOUNT = 'promotion_discount';
    public const TYPE_PRODUCT = 'product';
    public const TYPE_COMBO = 'combo';

    protected $fillable = [
        'order_id',
        'item_type',
        'item_code',
        'description',
        'quantity',
        'unit_price',
        'subtotal',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
