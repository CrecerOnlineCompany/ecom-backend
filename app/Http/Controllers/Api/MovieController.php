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

        $movies = $query->orderBy('release_date', 'desc')->paginate(15);
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
        $movie->load(['screenings.room.cinema']);
        return response()->json($movie);
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
}
