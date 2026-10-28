<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Services\Shop\AimeosOrderCatalog;
use App\Services\Shop\ManagedSite;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly AimeosOrderCatalog $catalog,
        private readonly ManagedSite $site,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->catalog->list($this->site->code()),
        ]);
    }
}
