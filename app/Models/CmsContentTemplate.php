<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsContentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'site',
        'type',
        'name',
        'handle',
        'description',
        'is_active',
        'template_json',
        'preview_image_url',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'template_json' => 'array',
        'metadata' => 'array',
    ];
}
