<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PaymentMethod::query()
                ->orderBy('name')
                ->get()
                ->map(fn (PaymentMethod $method) => $this->present($method)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $method = PaymentMethod::query()->create($this->validatedData($request));

        return response()->json([
            'data' => $this->present($method),
            'message' => 'Metodo de pago guardado.',
        ], 201);
    }

    public function update(Request $request, PaymentMethod $payment): JsonResponse
    {
        $payment->update($this->validatedData($request));

        return response()->json([
            'data' => $this->present($payment),
            'message' => 'Metodo de pago guardado.',
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'fee' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'string', 'in:Activo,Borrador'],
        ]);
    }

    private function present(PaymentMethod $method): array
    {
        return [
            'id' => (string) $method->id,
            'name' => $method->name,
            'meta' => $method->provider,
            'fee' => $method->fee,
            'status' => $method->status,
        ];
    }
}
