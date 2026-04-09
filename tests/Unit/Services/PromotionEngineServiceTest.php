<?php

namespace Tests\Unit\Services;

use App\Models\OrderItem;
use App\Models\Promotion;
use App\Services\PromotionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_2x1_bxgy_on_ticket_items(): void
    {
        Promotion::create([
            'code' => 'PROMO2X1',
            'name' => 'Promo 2x1 Entradas',
            'type' => Promotion::TYPE_BXGY,
            'is_active' => true,
            'is_automatic' => false,
            'is_stackable' => false,
            'priority' => 1,
            'settings' => [
                'buy_qty' => 2,
                'pay_qty' => 1,
                'target_item_type' => OrderItem::TYPE_TICKET_SEAT,
            ],
        ]);

        $baseItems = [
            [
                'item_type' => OrderItem::TYPE_TICKET_SEAT,
                'quantity' => 1,
                'unit_price' => 100.00,
                'subtotal' => 100.00,
            ],
            [
                'item_type' => OrderItem::TYPE_TICKET_SEAT,
                'quantity' => 1,
                'unit_price' => 120.00,
                'subtotal' => 120.00,
            ],
            [
                'item_type' => OrderItem::TYPE_TICKET_SEAT,
                'quantity' => 1,
                'unit_price' => 150.00,
                'subtotal' => 150.00,
            ],
        ];

        /** @var PromotionEngineService $engine */
        $engine = app(PromotionEngineService::class);
        $result = $engine->applyPromotions($baseItems, ['promotion_code' => 'PROMO2X1']);

        $this->assertCount(1, $result['discount_items']);
        $this->assertCount(1, $result['applied_promotions']);
        $this->assertEquals(100.00, (float) $result['total_discount']); // bonifica el más barato
        $this->assertEquals(OrderItem::TYPE_PROMOTION_DISCOUNT, $result['discount_items'][0]['item_type']);
    }

    public function test_screening_scoped_promotion_only_applies_to_matching_screening(): void
    {
        Promotion::create([
            'code' => 'AUTO2X1-S77',
            'name' => '2x1 Scoped',
            'type' => Promotion::TYPE_BXGY,
            'is_active' => true,
            'is_automatic' => true,
            'is_stackable' => false,
            'priority' => 1,
            'settings' => [
                'buy_qty' => 2,
                'pay_qty' => 1,
                'target_item_type' => OrderItem::TYPE_TICKET_SEAT,
                'screening_ids' => [77],
            ],
        ]);

        $baseItems = [
            ['item_type' => OrderItem::TYPE_TICKET_SEAT, 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100],
            ['item_type' => OrderItem::TYPE_TICKET_SEAT, 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100],
        ];

        /** @var PromotionEngineService $engine */
        $engine = app(PromotionEngineService::class);

        $noMatch = $engine->applyPromotions($baseItems, ['screening_id' => 10]);
        $this->assertEquals(0.0, (float) $noMatch['total_discount']);

        $match = $engine->applyPromotions($baseItems, ['screening_id' => 77]);
        $this->assertEquals(100.0, (float) $match['total_discount']);
    }

    public function test_apply_2x1_bxgy_only_for_target_codes(): void
    {
        Promotion::create([
            'code' => 'PROMO-COMBO-2X1',
            'name' => '2x1 Combo específico',
            'type' => Promotion::TYPE_BXGY,
            'is_active' => true,
            'is_automatic' => false,
            'is_stackable' => false,
            'priority' => 1,
            'settings' => [
                'buy_qty' => 2,
                'pay_qty' => 1,
                'target_item_type' => OrderItem::TYPE_PRODUCT,
                'target_codes' => ['COMBO_2G_PG'],
            ],
        ]);

        $baseItems = [
            [
                'item_type' => OrderItem::TYPE_PRODUCT,
                'item_code' => 'COMBO_2G_PG',
                'quantity' => 1,
                'unit_price' => 100.00,
                'subtotal' => 100.00,
            ],
            [
                'item_type' => OrderItem::TYPE_PRODUCT,
                'item_code' => 'COMBO_2G_PG',
                'quantity' => 1,
                'unit_price' => 120.00,
                'subtotal' => 120.00,
            ],
            [
                'item_type' => OrderItem::TYPE_PRODUCT,
                'item_code' => 'GASEOSA_GRANDE',
                'quantity' => 1,
                'unit_price' => 80.00,
                'subtotal' => 80.00,
            ],
        ];

        /** @var PromotionEngineService $engine */
        $engine = app(PromotionEngineService::class);
        $result = $engine->applyPromotions($baseItems, ['promotion_code' => 'PROMO-COMBO-2X1']);

        $this->assertEquals(100.00, (float) $result['total_discount']); // solo bonifica COMBO_2G_PG
        $this->assertCount(1, $result['applied_promotions']);
        $this->assertEquals(
            ['COMBO_2G_PG'],
            $result['applied_promotions'][0]['details']['target_codes'] ?? []
        );
    }

    public function test_apply_2x1_bxgy_for_combo_only_when_cart_conditions_match(): void
    {
        Promotion::create([
            'code' => 'PROMO-COMBO-COND',
            'name' => '2x1 Combo con condición de entradas',
            'type' => Promotion::TYPE_BXGY,
            'is_active' => true,
            'is_automatic' => false,
            'is_stackable' => false,
            'priority' => 1,
            'settings' => [
                'buy_qty' => 2,
                'pay_qty' => 1,
                'target_item_type' => OrderItem::TYPE_PRODUCT,
                'target_codes' => ['COMBO_2G_PG'],
                'conditions' => [
                    'aggregator' => 'all',
                    'conditions' => [
                        [
                            'type' => 'cart_quantity',
                            'item_type' => OrderItem::TYPE_TICKET_SEAT,
                            'operator' => '>=',
                            'value' => 2,
                        ],
                    ],
                ],
            ],
        ]);

        /** @var PromotionEngineService $engine */
        $engine = app(PromotionEngineService::class);

        $withoutEnoughTickets = [
            [
                'item_type' => OrderItem::TYPE_TICKET_SEAT,
                'item_code' => 'SEAT:10',
                'quantity' => 1,
                'unit_price' => 100.00,
                'subtotal' => 100.00,
            ],
            [
                'item_type' => OrderItem::TYPE_PRODUCT,
                'item_code' => 'COMBO_2G_PG',
                'quantity' => 2,
                'unit_price' => 120.00,
                'subtotal' => 240.00,
            ],
        ];

        $resultWithoutCondition = $engine->applyPromotions(
            $withoutEnoughTickets,
            ['promotion_code' => 'PROMO-COMBO-COND']
        );
        $this->assertEquals(0.0, (float) $resultWithoutCondition['total_discount']);

        $withEnoughTickets = [
            [
                'item_type' => OrderItem::TYPE_TICKET_SEAT,
                'item_code' => 'SEAT:10',
                'quantity' => 1,
                'unit_price' => 100.00,
                'subtotal' => 100.00,
            ],
            [
                'item_type' => OrderItem::TYPE_TICKET_SEAT,
                'item_code' => 'SEAT:11',
                'quantity' => 1,
                'unit_price' => 120.00,
                'subtotal' => 120.00,
            ],
            [
                'item_type' => OrderItem::TYPE_PRODUCT,
                'item_code' => 'COMBO_2G_PG',
                'quantity' => 2,
                'unit_price' => 120.00,
                'subtotal' => 240.00,
            ],
        ];

        $resultWithCondition = $engine->applyPromotions(
            $withEnoughTickets,
            ['promotion_code' => 'PROMO-COMBO-COND']
        );

        $this->assertEquals(120.0, (float) $resultWithCondition['total_discount']);
    }

    public function test_apply_fixed_amount_on_product_with_quantity_two(): void
    {
        Promotion::create([
            'code' => 'PROMO-FIXED-PROD',
            'name' => 'Monto fijo productos',
            'type' => Promotion::TYPE_FIXED_AMOUNT,
            'is_active' => true,
            'is_automatic' => false,
            'is_stackable' => false,
            'priority' => 1,
            'settings' => [
                'amount' => 150,
                'target_item_type' => OrderItem::TYPE_PRODUCT,
                'target_codes' => ['COMBO_2G_PG'],
            ],
        ]);

        $baseItems = [
            [
                'item_type' => OrderItem::TYPE_PRODUCT,
                'item_code' => 'COMBO_2G_PG',
                'quantity' => 2,
                'unit_price' => 120.00,
                'subtotal' => 240.00,
            ],
        ];

        /** @var PromotionEngineService $engine */
        $engine = app(PromotionEngineService::class);
        $result = $engine->applyPromotions($baseItems, ['promotion_code' => 'PROMO-FIXED-PROD']);

        $this->assertEquals(150.00, (float) $result['total_discount']);
    }

    public function test_apply_percentage_on_product_with_quantity_two(): void
    {
        Promotion::create([
            'code' => 'PROMO-PCT-PROD',
            'name' => 'Porcentaje productos',
            'type' => Promotion::TYPE_PERCENTAGE,
            'is_active' => true,
            'is_automatic' => false,
            'is_stackable' => false,
            'priority' => 1,
            'settings' => [
                'percentage' => 10,
                'target_item_type' => OrderItem::TYPE_PRODUCT,
                'target_codes' => ['COMBO_2G_PG'],
            ],
        ]);

        $baseItems = [
            [
                'item_type' => OrderItem::TYPE_PRODUCT,
                'item_code' => 'COMBO_2G_PG',
                'quantity' => 2,
                'unit_price' => 120.00,
                'subtotal' => 240.00,
            ],
        ];

        /** @var PromotionEngineService $engine */
        $engine = app(PromotionEngineService::class);
        $result = $engine->applyPromotions($baseItems, ['promotion_code' => 'PROMO-PCT-PROD']);

        $this->assertEquals(24.00, (float) $result['total_discount']);
    }
}
