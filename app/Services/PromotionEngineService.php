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
            if (!$this->passesConditionEngine($promotion, $baseItems, $context)) {
                continue;
            }

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
     * Motor de condiciones inspirado en reglas de carrito.
     *
     * settings.conditions (opcional):
     * {
     *   "aggregator": "all",
     *   "conditions": [
     *     {"type":"cart_quantity","item_type":"ticket_seat","operator":">=","value":2},
     *     {"type":"cart_quantity","item_type":"product","item_codes":["COMBO_2G_PG"],"operator":">=","value":1}
     *   ]
     * }
     *
     * Soporta grupos anidados por "conditions" + "aggregator" ("all"|"any").
     */
    private function passesConditionEngine(Promotion $promotion, array $baseItems, array $context): bool
    {
        $settings = is_array($promotion->settings) ? $promotion->settings : [];
        $root = $settings['conditions'] ?? null;
        if (!is_array($root)) {
            return true;
        }

        return $this->evaluateConditionNode($root, $baseItems, $context);
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
        $targetCodes = $this->normalizeTargetCodes($settings['target_codes'] ?? []);
        $maxDiscount = isset($settings['max_discount']) ? (float) $settings['max_discount'] : null;

        if ($percentage <= 0) {
            return ['discount_amount' => 0.0];
        }

        $subtotal = $this->subtotalByType($baseItems, $targetType, $targetCodes);
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
                'target_codes' => $targetCodes,
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
        $targetCodes = $this->normalizeTargetCodes($settings['target_codes'] ?? []);

        if ($amount <= 0) {
            return ['discount_amount' => 0.0];
        }

        $subtotal = $this->subtotalByType($baseItems, $targetType, $targetCodes);
        if ($subtotal <= 0) {
            return ['discount_amount' => 0.0];
        }

        return [
            'discount_amount' => min(round($amount, 2), round($subtotal, 2)),
            'details' => [
                'amount' => round($amount, 2),
                'target_item_type' => $targetType,
                'target_codes' => $targetCodes,
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
        $targetCodes = $this->normalizeTargetCodes($settings['target_codes'] ?? []);

        if ($buyQty <= 0 || $payQty < 0 || $payQty >= $buyQty) {
            return ['discount_amount' => 0.0];
        }

        $eligibleUnitPrices = [];
        foreach ($baseItems as $item) {
            if (!$this->matchesTarget($item, $targetType, $targetCodes)) {
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
                'target_codes' => $targetCodes,
                'eligible_units' => $eligibleCount,
                'applied_sets' => $sets,
                'free_units' => $freeUnits,
            ],
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $items
     */
    private function subtotalByType(array $items, string $itemType, array $targetCodes = []): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            if (!$this->matchesTarget($item, $itemType, $targetCodes)) {
                continue;
            }
            $sum += (float) ($item['subtotal'] ?? 0);
        }
        return round($sum, 2);
    }

    /**
     * @param mixed $value
     * @return array<int,string>
     */
    private function normalizeTargetCodes($value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $code) {
            $code = strtoupper(trim((string) $code));
            if ($code === '') {
                continue;
            }
            $normalized[$code] = true;
        }

        return array_keys($normalized);
    }

    /**
     * @param array<string,mixed> $item
     * @param array<int,string> $targetCodes
     */
    private function matchesTarget(array $item, string $itemType, array $targetCodes = []): bool
    {
        if (!$this->matchesItemType((string) ($item['item_type'] ?? ''), $itemType)) {
            return false;
        }

        if (empty($targetCodes)) {
            return true;
        }

        $itemCode = strtoupper(trim((string) ($item['item_code'] ?? '')));
        if ($itemCode === '') {
            return false;
        }

        return in_array($itemCode, $targetCodes, true);
    }

    /**
     * @param array<string,mixed> $node
     */
    private function evaluateConditionNode(array $node, array $baseItems, array $context): bool
    {
        $children = $node['conditions'] ?? null;
        if (is_array($children)) {
            $aggregator = strtolower(trim((string) ($node['aggregator'] ?? 'all')));
            $aggregator = in_array($aggregator, ['all', 'any'], true) ? $aggregator : 'all';

            if (empty($children)) {
                return true;
            }

            if ($aggregator === 'any') {
                foreach ($children as $child) {
                    if (!is_array($child)) {
                        continue;
                    }
                    if ($this->evaluateConditionNode($child, $baseItems, $context)) {
                        return true;
                    }
                }
                return false;
            }

            foreach ($children as $child) {
                if (!is_array($child)) {
                    return false;
                }
                if (!$this->evaluateConditionNode($child, $baseItems, $context)) {
                    return false;
                }
            }

            return true;
        }

        return $this->evaluateLeafCondition($node, $baseItems, $context);
    }

    /**
     * @param array<string,mixed> $condition
     */
    private function evaluateLeafCondition(array $condition, array $baseItems, array $context): bool
    {
        $type = strtolower(trim((string) ($condition['type'] ?? '')));
        $operator = strtolower(trim((string) ($condition['operator'] ?? '>=')));
        $expected = $condition['value'] ?? null;

        return match ($type) {
            'cart_quantity' => $this->compareValues(
                $this->matchedQuantity($baseItems, $condition),
                $operator,
                $expected
            ),
            'cart_subtotal' => $this->compareValues(
                $this->matchedSubtotal($baseItems, $condition),
                $operator,
                $expected
            ),
            'context_value' => $this->compareValues(
                $this->extractContextValue($context, (string) ($condition['context_key'] ?? '')),
                $operator,
                $expected
            ),
            default => false,
        };
    }

    /**
     * @param array<string,mixed> $condition
     */
    private function matchedQuantity(array $baseItems, array $condition): int
    {
        $itemType = trim((string) ($condition['item_type'] ?? ''));
        $targetCodes = $this->normalizeTargetCodes($condition['item_codes'] ?? ($condition['target_codes'] ?? []));
        $sum = 0;

        foreach ($baseItems as $item) {
            if (!$this->matchesTargetByCondition($item, $itemType, $targetCodes)) {
                continue;
            }
            $sum += max(1, (int) ($item['quantity'] ?? 1));
        }

        return $sum;
    }

    /**
     * @param array<string,mixed> $condition
     */
    private function matchedSubtotal(array $baseItems, array $condition): float
    {
        $itemType = trim((string) ($condition['item_type'] ?? ''));
        $targetCodes = $this->normalizeTargetCodes($condition['item_codes'] ?? ($condition['target_codes'] ?? []));
        $sum = 0.0;

        foreach ($baseItems as $item) {
            if (!$this->matchesTargetByCondition($item, $itemType, $targetCodes)) {
                continue;
            }
            $sum += (float) ($item['subtotal'] ?? 0);
        }

        return round($sum, 2);
    }

    /**
     * @param array<string,mixed> $item
     * @param array<int,string> $targetCodes
     */
    private function matchesTargetByCondition(array $item, string $itemType = '', array $targetCodes = []): bool
    {
        if ($itemType !== '' && !$this->matchesItemType((string) ($item['item_type'] ?? ''), $itemType)) {
            return false;
        }

        if (empty($targetCodes)) {
            return true;
        }

        $itemCode = strtoupper(trim((string) ($item['item_code'] ?? '')));
        if ($itemCode === '') {
            return false;
        }

        return in_array($itemCode, $targetCodes, true);
    }

    private function matchesItemType(string $actualType, string $requestedType): bool
    {
        $actualType = strtolower(trim($actualType));
        $requestedType = strtolower(trim($requestedType));

        if ($requestedType === '') {
            return true;
        }

        if ($actualType === $requestedType) {
            return true;
        }

        // Compatibilidad: considerar "product" como categoría madre que incluye combos.
        if ($requestedType === OrderItem::TYPE_PRODUCT && $actualType === OrderItem::TYPE_COMBO) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function extractContextValue(array $context, string $contextKey)
    {
        $contextKey = trim($contextKey);
        if ($contextKey === '') {
            return null;
        }

        return $context[$contextKey] ?? null;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     */
    private function compareValues($left, string $operator, $right): bool
    {
        if (in_array($operator, ['in', 'not_in'], true)) {
            $set = is_array($right) ? $right : [$right];
            $contains = in_array($left, $set, true);
            return $operator === 'in' ? $contains : !$contains;
        }

        $leftIsNumeric = is_numeric($left);
        $rightIsNumeric = is_numeric($right);

        // Comparaciones de orden requieren operandos numéricos válidos.
        if (in_array($operator, ['>', '>=', '<', '<='], true) && !($leftIsNumeric && $rightIsNumeric)) {
            return false;
        }

        if ($leftIsNumeric && $rightIsNumeric) {
            $left = (float) $left;
            $right = (float) $right;
        } else {
            $left = is_scalar($left) || $left === null ? (string) $left : json_encode($left);
            $right = is_scalar($right) || $right === null ? (string) $right : json_encode($right);
        }

        return match ($operator) {
            '==', '=' => $left == $right,
            '!=', '<>' => $left != $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            default => false,
        };
    }
}
