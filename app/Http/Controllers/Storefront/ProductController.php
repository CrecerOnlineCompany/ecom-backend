<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Shop\AimeosProductCatalog;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(private readonly AimeosProductCatalog $catalog)
    {
    }

    public function index(string $store): JsonResponse
    {
        return response()->json([
            'data' => $this->catalog->list($this->site($store), true),
        ]);
    }

    public function show(string $store, string $product): JsonResponse
    {
        return response()->json([
            'data' => $this->catalog->get($product, $this->site($store)),
        ]);
    }

    private function site(string $store): string
    {
        return $store === 'demo' ? 'default' : $store;
    }
}
