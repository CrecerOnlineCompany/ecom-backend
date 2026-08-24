<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Services\Shop\AimeosCategoryCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CategoryController extends Controller
{
    public function __construct(private readonly AimeosCategoryCatalog $catalog)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->catalog->list(),
        ]);
    }

    public function update(Request $request, string $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'string', 'in:Activo,Borrador'],
        ]);

        try {
            return response()->json([
                'data' => $this->catalog->update($category, $data),
                'message' => 'Categoria guardada.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 500);
        }
    }
}
