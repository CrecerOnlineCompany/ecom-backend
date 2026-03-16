<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MovieController extends Controller
{
    /**
     * Display a listing of movies.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Movie::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('genre')) {
            $query->where('genre', $request->genre);
        }

        $query->whereHas('screenings', function ($screeningQuery) {
            $screeningQuery->where('is_active', true)
                ->where('start_time', '>=', now());
        });

        $movies = $query->orderBy('release_date', 'desc')->paginate(15);
        
        // Agregar URL de imagen a cada película
        $movies->getCollection()->transform(function ($movie) {
            return $this->transformMovie($movie);
        });
        
        return response()->json($movies);
    }

    /**
     * Store a newly created movie.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|unique:movies',
            'description' => 'nullable|string',
            'genre' => 'required|string',
            'duration' => 'required|integer|min:1',
            'rating' => 'nullable|string',
            'director' => 'nullable|string',
            'cast' => 'nullable|string',
            'language' => 'required|string|default:es',
            'poster_url' => 'nullable|url',
            'trailer_url' => 'nullable|url',
            'release_date' => 'required|date',
            'end_date' => 'nullable|date|after:release_date',
            'is_active' => 'boolean',
        ]);

        $movie = Movie::create($validated);
        return response()->json($movie, 201);
    }

    /**
     * Display the specified movie.
     */
    public function show(Movie $movie): JsonResponse
    {
        $movie = Movie::query()
            ->select([
                'id',
                'title',
                'poster_image',
                'poster_url',
                'genre',
                'duration',
                'director',
                'description',
            ])
            ->findOrFail($movie->id);

        return response()->json([
            'title' => $movie->title,
            'poster_image_url' => $movie->poster_image
                ? url('/images/movies/' . $movie->poster_image)
                : $movie->poster_url,
            'genre' => $movie->genre,
            'duration' => $movie->duration,
            'director' => $movie->director,
            'synopsis' => $movie->description,
        ]);
    }

    /**
     * Update the specified movie.
     */
    public function update(Request $request, Movie $movie): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|unique:movies,title,' . $movie->id,
            'description' => 'nullable|string',
            'genre' => 'sometimes|string',
            'duration' => 'sometimes|integer|min:1',
            'rating' => 'nullable|string',
            'director' => 'nullable|string',
            'cast' => 'nullable|string',
            'language' => 'sometimes|string',
            'poster_url' => 'nullable|url',
            'trailer_url' => 'nullable|url',
            'release_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after:release_date',
            'is_active' => 'boolean',
        ]);

        $movie->update($validated);
        return response()->json($movie);
    }

    /**
     * Remove the specified movie.
     */
    public function destroy(Movie $movie): JsonResponse
    {
        $movie->delete();
        return response()->json(['message' => 'Movie deleted successfully']);
    }

    /**
     * Transform movie data to include poster image URL.
     */
    private function transformMovie(Movie $movie): array
    {
        $data = $movie->toArray();
        
        // Agregar URL de imagen si existe
        if ($movie->poster_image) {
            $data['poster_image_url'] = url('/images/movies/' . $movie->poster_image);
        } else {
            $data['poster_image_url'] = null;
        }
        
        // Remover el campo poster_image para devolver solo la URL
        unset($data['poster_image']);
        
        return $data;
    }
}
