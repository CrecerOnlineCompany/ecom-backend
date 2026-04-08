<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Promotion;

class PromotionEngineService
{
    /**
     * @param array<int, array<string,mixed>> $baseItems
     * @param array<string,mixed> $context
     * @return array{discount_items: array<int, array<string,mixed>>, applied_promotions: array<int, array<string,mixed>>, total_discount: float}
     */
    public function applyPromotions(array $baseItems, array $context = []): array
    {
        $promotions = $this->resolvePromotions($context);
        $discountItems = [];
        $applied = [];
        $totalDiscount = 0.0;

        foreach ($promotions as $promotion) {
            $application = $this->applyPromotion($promotion, $baseItems, $context);
            if (($application['discount_amount'] ?? 0) <= 0) {
                continue;
            }

            $discountAmount = round((float) $application['discount_amount'], 2);
            $totalDiscount += $discountAmount;

            $discountItems[] = [
                'item_type' => OrderItem::TYPE_PROMOTION_DISCOUNT,
                'item_code' => 'PROMO:' . ($promotion->code ?: $promotion->id),
                'description' => 'Descuento promo: ' . $promotion->name,
                'quantity' => 1,
                'unit_price' => -$discountAmount,
                'subtotal' => -$discountAmount,
                'metadata' => [
                    'promotion_id' => $promotion->id,
                    'promotion_code' => $promotion->code,
                    'promotion_type' => $promotion->type,
                    'details' => $application['details'] ?? [],
                ],
            ];

            $applied[] = [
                'promotion_id' => $promotion->id,
                'code' => $promotion->code,
                'name' => $promotion->name,
                'type' => $promotion->type,
                'discount_amount' => $discountAmount,
                'details' => $application['details'] ?? [],
            ];

            if (!$promotion->is_stackable) {
                break;
            }
        }

        return [
            'discount_items' => $discountItems,
            'applied_promotions' => $applied,
            'total_discount' => round($totalDiscount, 2),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return \Illuminate\Support\Collection<int, Promotion>
     */
    private function resolvePromotions(array $context)
    {
        $promotionCode = trim((string) ($context['promotion_code'] ?? ''));

        $automatic = Promotion::query()
            ->currentlyActive()
            ->where('is_automatic', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (Promotion $promotion) => $this->isPromotionEligibleForContext($promotion, $context))
            ->values();

        if ($promotionCode === '') {
            return $automatic;
        }

        $coded = Promotion::query()
            ->currentlyActive()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($promotionCode)])
            ->first();

        if (!$coded) {
            throw new \InvalidArgumentException('Código promocional inválido o expirado');
        }

        if (!$this->isPromotionEligibleForContext($coded, $context)) {
            throw new \InvalidArgumentException('La promoción no aplica a esta función');
        }

        $all = $automatic->push($coded)
            ->unique('id')
            ->sortBy([
                ['priority', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        return $all;
    }

    /**
     * Evalúa restricciones por contexto declaradas en settings.
     * Soportadas:
     * - screening_ids: [int,...]
     * - cinema_ids: [int,...]
     * - room_ids: [int,...]
     *
     * @param array<string,mixed> $context
     */
    private function isPromotionEligibleForContext(Promotion $promotion, array $context): bool
    {
        $settings = is_array($promotion->settings) ? $promotion->settings : [];

        $screeningId = isset($context['screening_id']) ? (int) $context['screening_id'] : null;
        $roomId = isset($context['room_id']) ? (int) $context['room_id'] : null;
        $cinemaId = isset($context['cinema_id']) ? (int) $context['cinema_id'] : null;

        if (!empty($settings['screening_ids']) && is_array($settings['screening_ids'])) {
            $allowed = array_map('intval', $settings['screening_ids']);
            if (!$screeningId || !in_array($screeningId, $allowed, true)) {
                return false;
            }
        }

        if (!empty($settings['room_ids']) && is_array($settings['room_ids'])) {
            $allowed = array_map('intval', $settings['room_ids']);
            if (!$roomId || !in_array($roomId, $allowed, true)) {
                return false;
            }
        }

        if (!empty($settings['cinema_ids']) && is_array($settings['cinema_ids'])) {
            $allowed = array_map('intval', $settings['cinema_ids']);
            if (!$cinemaId || !in_array($cinemaId, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string,mixed>> $baseItems
     * @param array<string,mixed> $context
     * @return array{discount_amount: float, details?: array<string,mixed>}
     */
    private function applyPromotion(Promotion $promotion, array $baseItems, array $context): array
    {
        return match ($promotion->type) {
            Promotion::TYPE_PERCENTAGE => $this->applyPercentage($promotion, $baseItems),
            Promotion::TYPE_FIXED_AMOUNT => $this->applyFixedAmount($promotion, $baseItems),
            Promotion::TYPE_BXGY => $this->applyBxgy($promotion, $baseItems),
            default => ['discount_amount' => 0.0],
        };
    }

    /**
     * @param array<int, array<string,mixed>> $baseItems
     */
    private function applyPercentage(Promotion $promotion, array $baseItems): array
    {
        $settings = is_array($promotion->settings) ? $promotion->settings : [];
        $percentage = (float) ($settings['percentage'] ?? 0);
        $targetType = (string) ($settings['target_item_type'] ?? OrderItem::TYPE_TICKET_SEAT);
        $maxDiscount = isset($settings['max_discount']) ? (float) $settings['max_discount'] : null;

        if ($percentage <= 0) {
            return ['discount_amount' => 0.0];
        }

        $subtotal = $this->subtotalByType($baseItems, $targetType);
        if ($subtotal <= 0) {
            return ['discount_amount' => 0.0];
        }

        $discount = round($subtotal * ($percentage / 100), 2);
        if ($maxDiscount !== null) {
            $discount = min($discount, $maxDiscount);
        }

        return [
            'discount_amount' => max(0, round($discount, 2)),
            'details' => [
                'percentage' => $percentage,
                'target_item_type' => $targetType,
                'target_subtotal' => $subtotal,
            ],
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $baseItems
     */
    private function applyFixedAmount(Promotion $promotion, array $baseItems): array
    {
        $settings = is_array($promotion->settings) ? $promotion->settings : [];
        $amount = (float) ($settings['amount'] ?? 0);
        $targetType = (string) ($settings['target_item_type'] ?? OrderItem::TYPE_TICKET_SEAT);

        if ($amount <= 0) {
            return ['discount_amount' => 0.0];
        }

        $subtotal = $this->subtotalByType($baseItems, $targetType);
        if ($subtotal <= 0) {
            return ['discount_amount' => 0.0];
        }

        return [
            'discount_amount' => min(round($amount, 2), round($subtotal, 2)),
            'details' => [
                'amount' => round($amount, 2),
                'target_item_type' => $targetType,
                'target_subtotal' => $subtotal,
            ],
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $baseItems
     */
    private function applyBxgy(Promotion $promotion, array $baseItems): array
    {
        $settings = is_array($promotion->settings) ? $promotion->settings : [];
        $buyQty = (int) ($settings['buy_qty'] ?? 2);
        $payQty = (int) ($settings['pay_qty'] ?? 1);
        $targetType = (string) ($settings['target_item_type'] ?? OrderItem::TYPE_TICKET_SEAT);

        if ($buyQty <= 0 || $payQty < 0 || $payQty >= $buyQty) {
            return ['discount_amount' => 0.0];
        }

        $eligibleUnitPrices = [];
        foreach ($baseItems as $item) {
            if (($item['item_type'] ?? null) !== $targetType) {
                continue;
            }

            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            if ($unitPrice <= 0) {
                continue;
            }

            for ($i = 0; $i < $qty; $i++) {
                $eligibleUnitPrices[] = $unitPrice;
            }
        }

        $eligibleCount = count($eligibleUnitPrices);
        if ($eligibleCount < $buyQty) {
            return ['discount_amount' => 0.0];
        }

        sort($eligibleUnitPrices, SORT_NUMERIC); // regla: se bonifican los más baratos

        $sets = intdiv($eligibleCount, $buyQty);
        $freeUnitsPerSet = $buyQty - $payQty;
        $freeUnits = $sets * $freeUnitsPerSet;
        if ($freeUnits <= 0) {
            return ['discount_amount' => 0.0];
        }

        $discount = 0.0;
        for ($i = 0; $i < $freeUnits && $i < $eligibleCount; $i++) {
            $discount += (float) $eligibleUnitPrices[$i];
        }

        return [
            'discount_amount' => round($discount, 2),
            'details' => [
                'buy_qty' => $buyQty,
                'pay_qty' => $payQty,
                'target_item_type' => $targetType,
                'eligible_units' => $eligibleCount,
                'applied_sets' => $sets,
                'free_units' => $freeUnits,
            ],
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $items
     */
    private function subtotalByType(array $items, string $itemType): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            if (($item['item_type'] ?? null) !== $itemType) {
                continue;
            }
            $sum += (float) ($item['subtotal'] ?? 0);
        }
        return round($sum, 2);
    }
}
