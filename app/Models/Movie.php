<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'genre',
        'duration',
        'rating',
        'director',
        'cast',
        'language',
        'poster_url',
        'trailer_url',
        'release_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'release_date' => 'date',
        'end_date' => 'date',
    ];

    public function screenings(): HasMany
    {
        return $this->hasMany(Screening::class);
    }
}
