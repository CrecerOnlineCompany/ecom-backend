<?php

/**
 * EJEMPLOS DE USO - Acceso a imágenes de películas
 * 
 * Archivo: app/Http/Controllers/Api/MovieController.php (Ejemplo)
 */

// Ejemplo 1: Obtener URL de la imagen desde el modelo
$movie = Movie::find(1);
$imageUrl = $movie->getPosterImageUrl(); // Retorna: /images/movies/1702360000_abc123.jpg

// Ejemplo 2: En la respuesta del API
return response()->json([
    'id' => $movie->id,
    'title' => $movie->title,
    'description' => $movie->description,
    'poster_image_url' => $movie->getPosterImageUrl(),
    'poster_url' => $movie->poster_url,
    'genre' => $movie->genre,
    'duration' => $movie->duration,
    'rating' => $movie->rating,
    'language' => $movie->language,
    'release_date' => $movie->release_date->format('Y-m-d'),
    'is_active' => $movie->is_active,
]);

// Ejemplo 3: En una colección de películas
$movies = Movie::active()->get();
return response()->json(
    $movies->map(function ($movie) {
        return [
            'id' => $movie->id,
            'title' => $movie->title,
            'genre' => $movie->genre,
            'poster_image_url' => $movie->getPosterImageUrl(),
            'rating' => $movie->rating,
        ];
    })
);

// Ejemplo 4: En el frontend (JavaScript/Vue/React)
// Acceso directo a la URL:
// fetch('/images/movies/1702360000_abc123.jpg')

// O usarla en HTML:
// <img src="/images/movies/1702360000_abc123.jpg" alt="Movie Poster">

/**
 * ESTRUCTURA DE ACCESO
 * 
 * Las imágenes se almacenan en:
 * public/images/movies/[timestamp]_[id_unico].[extension]
 * 
 * Y se acceden desde el navegador usando:
 * http://tudominio.com/images/movies/[timestamp]_[id_unico].[extension]
 * 
 * O relativo:
 * /images/movies/[timestamp]_[id_unico].[extension]
 */
