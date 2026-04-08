<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED_AMOUNT = 'fixed_amount';
    public const TYPE_BXGY = 'bxgy';

    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'is_active',
        'is_automatic',
        'is_stackable',
        'priority',
        'starts_at',
        'ends_at',
        'usage_limit',
        'usage_count',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_automatic' => 'boolean',
        'is_stackable' => 'boolean',
        'priority' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'settings' => 'array',
    ];

    public function scopeCurrentlyActive($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }

    public function setSettingsAttribute($value): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $this->attributes['settings'] = is_array($decoded)
                ? json_encode($decoded)
                : json_encode([]);
            return;
        }

        if (is_array($value)) {
            $this->attributes['settings'] = json_encode($value);
            return;
        }

        $this->attributes['settings'] = json_encode([]);
    }
}
