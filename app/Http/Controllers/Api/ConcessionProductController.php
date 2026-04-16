<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Screening;
use App\Services\OrderItemPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConcessionProductController extends Controller
{
    private OrderItemPricingService $orderItemPricingService;

    public function __construct(OrderItemPricingService $orderItemPricingService)
    {
        $this->orderItemPricingService = $orderItemPricingService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'movie_id' => 'nullable|exists:movies,id',
                'screening_id' => 'nullable|exists:screenings,id',
                'format' => 'nullable|string',
            ]);

            $format = strtoupper(trim((string) ($validated['format'] ?? '')));
            if ($format !== '' && !in_array($format, ['2D', '3D'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato no válido. Use 2D o 3D.',
                ], 422);
            }

            $movieId = $validated['movie_id'] ?? null;
            $screeningId = $validated['screening_id'] ?? null;

            if ($screeningId && !$movieId) {
                $screening = Screening::find($screeningId);
                $movieId = $screening?->movie_id;
            }

            $products = $this->orderItemPricingService->getAvailableProducts();
            if ($format === '3D' || $format === '2D') {
                $prefix = $format === '3D' ? 'COMBO_3D' : 'COMBO_2D';
                $products = array_values(array_filter($products, function (array $product) use ($prefix) {
                    $code = strtoupper((string) ($product['code'] ?? ''));
                    return str_starts_with($code, $prefix);
                }));
            }

            return response()->json([
                'success' => true,
                'filters' => [
                    'movie_id' => $movieId,
                    'screening_id' => $screeningId,
                    'format' => $format !== '' ? $format : null,
                ],
                'products' => $products,
                'count' => count($products),
            ]);
        } catch (\Exception $e) {
            Log::error('concession-products error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el catálogo de productos',
            ], 500);
        }
    }
}
