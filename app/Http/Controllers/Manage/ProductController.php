<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Services\Shop\AimeosProductCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ProductController extends Controller
{
    public function __construct(private readonly AimeosProductCatalog $catalog)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->catalog->list(),
        ]);
    }

    public function show(string $product): JsonResponse
    {
        return response()->json([
            'data' => $this->catalog->get($product),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateProduct($request);

        try {
            return response()->json([
                'data' => $this->catalog->save($data),
                'message' => 'Producto guardado.',
            ], 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $product): JsonResponse
    {
        $data = $this->validateProduct($request);

        try {
            return response()->json([
                'data' => $this->catalog->save($data, $product),
                'message' => 'Producto guardado.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $product): JsonResponse
    {
        try {
            $this->catalog->delete($product);

            return response()->json([
                'message' => 'Producto eliminado.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    private function validateProduct(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'string', 'max:255'],
            'variants.*.color' => ['nullable', 'string', 'max:255'],
            'variants.*.size' => ['nullable', 'string', 'max:255'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'images' => ['nullable', 'array'],
            'images.*.id' => ['nullable', 'string', 'max:255'],
            'images.*.url' => ['required_with:images', 'string', 'max:2048'],
        ]);
    }
}
