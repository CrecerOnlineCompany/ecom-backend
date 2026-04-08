<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Screening;
use App\Models\Seat;
use Illuminate\Support\Facades\DB;

class OrderItemPricingService
{
    private PromotionEngineService $promotionEngineService;

    public function __construct(PromotionEngineService $promotionEngineService)
    {
        $this->promotionEngineService = $promotionEngineService;
    }

    /**
     * Calcula líneas de ítems para asientos de una función.
     * Regla vigente: unit_price = screening.price * seat.price_modifier (fallback modifier=1.0)
     *
     * @param Screening $screening
     * @param array<int> $seatIds
     * @return array{items: array<int, array<string,mixed>>, total_amount: float, seat_prices: array<int,float>}
     */
    public function calculateSeatItems(Screening $screening, array $seatIds): array
    {
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        if (empty($seatIds)) {
            throw new \InvalidArgumentException('No seats provided for pricing');
        }

        $seats = Seat::query()
            ->whereIn('id', $seatIds)
            ->where('room_id', $screening->room_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($seats->count() !== count($seatIds)) {
            $validSeatIds = $seats->keys()->all();
            $invalidSeatIds = array_values(array_diff($seatIds, $validSeatIds));
            throw new \InvalidArgumentException(
                'Invalid or unavailable seats for screening room: ' . implode(',', $invalidSeatIds)
            );
        }

        $basePrice = (float) $screening->price;
        $totalAmount = 0.0;
        $items = [];
        $seatPrices = [];

        foreach ($seatIds as $seatId) {
            $seat = $seats->get($seatId);
            $modifier = (float) ($seat?->price_modifier ?? 1.0);
            if ($modifier <= 0) {
                $modifier = 1.0;
            }

            $unitPrice = round($basePrice * $modifier, 2);
            $subtotal = $unitPrice;
            $totalAmount += $subtotal;
            $seatPrices[$seatId] = $unitPrice;

            $items[] = [
                'item_type' => OrderItem::TYPE_TICKET_SEAT,
                'item_code' => 'SEAT:' . $seatId,
                'description' => "Entrada asiento {$seat->seat_code}",
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'metadata' => [
                    'screening_id' => (int) $screening->id,
                    'seat_id' => (int) $seat->id,
                    'seat_code' => $seat->seat_code,
                    'row_number' => (int) $seat->row_number,
                    'seat_number' => (int) $seat->seat_number,
                    'price_modifier' => $modifier,
                ],
            ];
        }

        return [
            'items' => $items,
            'total_amount' => round($totalAmount, 2),
            'seat_prices' => $seatPrices,
        ];
    }

    /**
     * Calcula ítems base + descuentos promocionales.
     *
     * @param array<string,mixed> $context
     * @return array{items: array<int,array<string,mixed>>, total_amount: float, seat_prices: array<int,float>, base_subtotal: float, seat_subtotal: float, product_subtotal: float, total_discount: float, applied_promotions: array<int,array<string,mixed>>}
     */
    public function calculatePricedItems(Screening $screening, array $seatIds, array $context = []): array
    {
        $base = $this->calculateSeatItems($screening, $seatIds);
        $seatItems = $base['items'];
        $seatSubtotal = (float) $base['total_amount'];

        $selectedProducts = $this->extractSelectedProducts($context);
        $productPricing = $this->calculateProductItems($selectedProducts);
        $productItems = $productPricing['items'];
        $productSubtotal = (float) $productPricing['total_amount'];

        $baseItems = array_merge($seatItems, $productItems);
        $baseSubtotal = round($seatSubtotal + $productSubtotal, 2);

        $promotions = $this->promotionEngineService->applyPromotions($baseItems, $context);
        $discountItems = $promotions['discount_items'];
        $totalDiscount = (float) $promotions['total_discount'];

        $allItems = array_merge($baseItems, $discountItems);
        $totalAmount = max(0, round($baseSubtotal - $totalDiscount, 2));

        return [
            'items' => $allItems,
            'total_amount' => $totalAmount,
            'seat_prices' => $base['seat_prices'],
            'base_subtotal' => round($baseSubtotal, 2),
            'seat_subtotal' => round($seatSubtotal, 2),
            'product_subtotal' => round($productSubtotal, 2),
            'total_discount' => round($totalDiscount, 2),
            'applied_promotions' => $promotions['applied_promotions'],
        ];
    }

    /**
     * Reemplaza las líneas de pricing de la orden por las líneas calculadas.
     *
     * @param Order $order
     * @param array<int, array<string,mixed>> $seatItems
     */
    public function syncSeatItems(Order $order, array $seatItems): void
    {
        DB::transaction(function () use ($order, $seatItems) {
            $order->orderItems()
                ->whereIn('item_type', [
                    OrderItem::TYPE_TICKET_SEAT,
                    OrderItem::TYPE_PRODUCT,
                    OrderItem::TYPE_COMBO,
                    OrderItem::TYPE_PROMOTION_DISCOUNT,
                ])
                ->delete();

            if (empty($seatItems)) {
                return;
            }

            $now = now();
            $rows = array_map(function (array $item) use ($order, $now) {
                return [
                    'order_id' => $order->id,
                    'item_type' => $item['item_type'],
                    'item_code' => $item['item_code'] ?? null,
                    'description' => $item['description'] ?? null,
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'subtotal' => (float) ($item['subtotal'] ?? 0),
                    'currency' => $order->currency ?? 'ARS',
                    'metadata' => !empty($item['metadata']) ? json_encode($item['metadata']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $seatItems);

            DB::table('order_items')->insert($rows);
        });
    }

    /**
     * Mapa seat_id => unit_price para líneas tipo ticket_seat.
     *
     * @return array<int,float>
     */
    public function getSeatPriceMapForOrder(Order $order): array
    {
        $priceMap = [];

        $items = $order->orderItems()
            ->where('item_type', OrderItem::TYPE_TICKET_SEAT)
            ->get(['unit_price', 'metadata']);

        foreach ($items as $item) {
            $metadata = is_array($item->metadata) ? $item->metadata : [];
            $seatId = isset($metadata['seat_id']) ? (int) $metadata['seat_id'] : null;
            if ($seatId) {
                $priceMap[$seatId] = (float) $item->unit_price;
            }
        }

        return $priceMap;
    }

    /**
     * Catálogo de productos activos para UI/checkout.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAvailableProducts(): array
    {
        $catalog = config('concessions.products', []);
        $result = [];

        foreach ($catalog as $code => $product) {
            if (!($product['is_active'] ?? true)) {
                continue;
            }

            $result[] = [
                'code' => (string) $code,
                'name' => (string) ($product['name'] ?? $code),
                'type' => (string) ($product['type'] ?? OrderItem::TYPE_PRODUCT),
                'unit_price' => round((float) ($product['unit_price'] ?? 0), 2),
                'currency' => (string) ($product['currency'] ?? 'ARS'),
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array{code:string,quantity:int}>
     */
    private function extractSelectedProducts(array $context): array
    {
        $products = $context['products'] ?? [];
        if (!is_array($products)) {
            return [];
        }

        $normalized = [];
        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $code = strtoupper(trim((string) ($product['code'] ?? '')));
            $quantity = (int) ($product['quantity'] ?? 0);

            if ($code === '' || $quantity < 1) {
                continue;
            }

            if (!isset($normalized[$code])) {
                $normalized[$code] = 0;
            }
            $normalized[$code] += $quantity;
        }

        return array_map(
            fn (string $code, int $quantity) => ['code' => $code, 'quantity' => $quantity],
            array_keys($normalized),
            array_values($normalized)
        );
    }

    /**
     * @param array<int,array{code:string,quantity:int}> $selectedProducts
     * @return array{items: array<int,array<string,mixed>>, total_amount: float}
     */
    private function calculateProductItems(array $selectedProducts): array
    {
        if (empty($selectedProducts)) {
            return [
                'items' => [],
                'total_amount' => 0.0,
            ];
        }

        $catalog = config('concessions.products', []);
        $items = [];
        $totalAmount = 0.0;

        foreach ($selectedProducts as $selected) {
            $code = strtoupper(trim((string) ($selected['code'] ?? '')));
            $quantity = (int) ($selected['quantity'] ?? 0);

            if ($code === '' || $quantity < 1) {
                continue;
            }

            $catalogItem = $catalog[$code] ?? null;
            $isActive = (bool) ($catalogItem['is_active'] ?? false);
            if (!$catalogItem || !$isActive) {
                throw new \InvalidArgumentException("Producto inválido o inactivo: {$code}");
            }

            $unitPrice = round((float) ($catalogItem['unit_price'] ?? 0), 2);
            if ($unitPrice < 0) {
                throw new \InvalidArgumentException("Precio inválido para producto: {$code}");
            }

            $itemType = (string) ($catalogItem['type'] ?? OrderItem::TYPE_PRODUCT);
            if (!in_array($itemType, [OrderItem::TYPE_PRODUCT, OrderItem::TYPE_COMBO], true)) {
                $itemType = OrderItem::TYPE_PRODUCT;
            }

            $subtotal = round($unitPrice * $quantity, 2);
            $totalAmount += $subtotal;

            $items[] = [
                'item_type' => $itemType,
                'item_code' => $code,
                'description' => (string) ($catalogItem['name'] ?? $code),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'metadata' => [
                    'category' => 'concession',
                ],
            ];
        }

        return [
            'items' => $items,
            'total_amount' => round($totalAmount, 2),
        ];
    }
}
