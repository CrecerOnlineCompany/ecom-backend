<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    public const TYPE_PRODUCT = 'product';
    public const TYPE_COMBO = 'combo';

    protected $fillable = [
        'code',
        'name',
        'image_path',
        'type',
        'unit_price',
        'currency',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];
}
