<?php

namespace App\Services\Shop;

use Illuminate\Support\Facades\DB;

class AimeosOrderCatalog
{
    public function list(): array
    {
        $addresses = DB::table('mshop_order_address')
            ->select(['parentid', 'email', 'firstname', 'lastname'])
            ->where('type', 'payment')
            ->get()
            ->keyBy('parentid');

        return DB::table('mshop_order')
            ->select(['id', 'invoiceno', 'customerid', 'statuspayment', 'statusdelivery', 'price', 'currencyid', 'ctime'])
            ->orderByDesc('ctime')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn ($order) => $this->toArray($order, $addresses[$order->id] ?? null))
            ->all();
    }

    private function toArray(object $order, ?object $address): array
    {
        $customer = trim(($address->firstname ?? '') . ' ' . ($address->lastname ?? ''));
        $customer = $customer ?: ($address->email ?? $order->customerid ?: 'Cliente sin datos');

        return [
            'id' => (string) $order->id,
            'name' => $order->invoiceno ?: 'ORD-' . $order->id,
            'meta' => $customer,
            'total' => number_format((float) $order->price, 2, '.', '') . ' ' . $order->currencyid,
            'delivery' => $this->deliveryStatus((int) $order->statusdelivery),
            'status' => $this->paymentStatus((int) $order->statuspayment),
            'createdAt' => $order->ctime,
        ];
    }

    private function paymentStatus(int $status): string
    {
        return match ($status) {
            -1 => 'Sin finalizar',
            0 => 'Eliminado',
            1 => 'Cancelado',
            2 => 'Rechazado',
            3 => 'Reembolsado',
            4 => 'Pendiente',
            5 => 'Autorizado',
            6 => 'Pagado',
            7 => 'Transferido',
            default => 'Desconocido',
        };
    }

    private function deliveryStatus(int $status): string
    {
        return match ($status) {
            -1 => 'Sin finalizar',
            0 => 'Eliminado',
            1 => 'Pendiente',
            2 => 'En proceso',
            3 => 'Despachado',
            4 => 'Entregado',
            5 => 'Perdido',
            6 => 'Rechazado',
            7 => 'Devuelto',
            default => 'Desconocido',
        };
    }
}
