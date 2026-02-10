<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'poster_image',
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

    protected $appends = [
        'poster_image_url',
    ];

    public function screenings(): HasMany
    {
        return $this->hasMany(Screening::class);
    }

    /**
     * Get the public URL of the poster image
     */
    public function getPosterImageUrlAttribute(): ?string
    {
        if ($this->poster_image) {
            return url('/images/movies/' . $this->poster_image);
        }
        return null;
    }

    /**
     * Rename poster image to a clean filename based on movie title
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (request()->hasFile('poster_image')) {
                $file = request()->file('poster_image');
                
                // Eliminar imagen anterior si existe
                if ($model->getOriginal('poster_image')) {
                    Storage::disk('admin')->delete($model->getOriginal('poster_image'));
                }
                
                // Generar nombre limpio basado en el título
                $slug = Str::slug($model->title ?? 'movie') . '-' . time();
                $extension = $file->getClientOriginalExtension();
                $filename = $slug . '.' . $extension;
                
                // Guardar el archivo con el nuevo nombre
                Storage::disk('admin')->put($filename, file_get_contents($file));
                
                // Actualizar el campo
                $model->poster_image = $filename;
            }
        });
    }
}
