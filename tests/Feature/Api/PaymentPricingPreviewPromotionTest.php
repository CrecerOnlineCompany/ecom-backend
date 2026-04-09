<?php

namespace Tests\Feature\Api;

use App\Models\Movie;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\Screening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentPricingPreviewPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_preview_applies_100_percent_discount_on_two_tickets_when_combo_is_present(): void
    {
        Product::create([
            'code' => 'COMBO_2G_PG',
            'name' => 'Combo 2 gaseosas + pochoclo grande',
            'type' => Product::TYPE_PRODUCT,
            'unit_price' => 500.00,
            'currency' => 'ARS',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Promotion::create([
            'code' => 'PROMO-COMBO-ENTRADAS-100',
            'name' => '100% en 2 entradas con combo',
            'type' => Promotion::TYPE_PERCENTAGE,
            'is_active' => true,
            'is_automatic' => false,
            'is_stackable' => false,
            'priority' => 1,
            'settings' => [
                'percentage' => 100,
                'target_item_type' => OrderItem::TYPE_TICKET_SEAT,
                'conditions' => [
                    'aggregator' => 'all',
                    'conditions' => [
                        [
                            'type' => 'cart_quantity',
                            'item_type' => OrderItem::TYPE_TICKET_SEAT,
                            'operator' => '==',
                            'value' => 2,
                        ],
                        [
                            'type' => 'cart_quantity',
                            'item_type' => OrderItem::TYPE_PRODUCT,
                            'item_codes' => ['COMBO_2G_PG'],
                            'operator' => '>=',
                            'value' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $movie = Movie::factory()->create();
        $room = Room::factory()->create([
            'rows' => 2,
            'columns' => 2,
            'total_seats' => 4,
            'is_active' => true,
        ]);

        $screening = Screening::factory()->create([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'price' => 100.00,
            'is_active' => true,
        ]);

        $seatA1 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 1,
            'seat_code' => 'A1',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $seatA2 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 2,
            'seat_code' => 'A2',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/payment-pricing-preview', [
            'screening_id' => $screening->id,
            'seat_ids' => [$seatA1->id, $seatA2->id],
            'products' => [
                ['code' => 'COMBO_2G_PG', 'quantity' => 1],
            ],
            'promotion_code' => 'PROMO-COMBO-ENTRADAS-100',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'seat_subtotal' => 200.0,
                'product_subtotal' => 500.0,
                'base_subtotal' => 700.0,
                'total_discount' => 200.0,
                'total_price' => 500.0,
            ]);
    }

    public function test_pricing_preview_does_not_apply_promotion_when_combo_is_missing(): void
    {
        Promotion::create([
            'code' => 'PROMO-COMBO-ENTRADAS-100',
            'name' => '100% en 2 entradas con combo',
            'type' => Promotion::TYPE_PERCENTAGE,
            'is_active' => true,
            'is_automatic' => false,
            'is_stackable' => false,
            'priority' => 1,
            'settings' => [
                'percentage' => 100,
                'target_item_type' => OrderItem::TYPE_TICKET_SEAT,
                'conditions' => [
                    'aggregator' => 'all',
                    'conditions' => [
                        [
                            'type' => 'cart_quantity',
                            'item_type' => OrderItem::TYPE_TICKET_SEAT,
                            'operator' => '==',
                            'value' => 2,
                        ],
                        [
                            'type' => 'cart_quantity',
                            'item_type' => OrderItem::TYPE_PRODUCT,
                            'item_codes' => ['COMBO_2G_PG'],
                            'operator' => '>=',
                            'value' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $movie = Movie::factory()->create();
        $room = Room::factory()->create([
            'rows' => 2,
            'columns' => 2,
            'total_seats' => 4,
            'is_active' => true,
        ]);

        $screening = Screening::factory()->create([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'price' => 100.00,
            'is_active' => true,
        ]);

        $seatA1 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 1,
            'seat_code' => 'A1',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $seatA2 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 2,
            'seat_code' => 'A2',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/payment-pricing-preview', [
            'screening_id' => $screening->id,
            'seat_ids' => [$seatA1->id, $seatA2->id],
            'promotion_code' => 'PROMO-COMBO-ENTRADAS-100',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'seat_subtotal' => 200.0,
                'product_subtotal' => 0.0,
                'base_subtotal' => 200.0,
                'total_discount' => 0.0,
                'total_price' => 200.0,
            ]);
    }

    public function test_pricing_preview_applies_when_condition_uses_combo_item_type(): void
    {
        Product::create([
            'code' => 'COMBO_2G_PG',
            'name' => 'Combo 2 gaseosas + pochoclo grande',
            'type' => Product::TYPE_COMBO,
            'unit_price' => 500.00,
            'currency' => 'ARS',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Promotion::create([
            'code' => 'PROMO-COMBO-TIPO-COMBO',
            'name' => '100% en 2 entradas con item_type combo',
            'type' => Promotion::TYPE_PERCENTAGE,
            'is_active' => true,
            'is_automatic' => false,
            'is_stackable' => false,
            'priority' => 1,
            'settings' => [
                'percentage' => 100,
                'target_item_type' => OrderItem::TYPE_TICKET_SEAT,
                'conditions' => [
                    'aggregator' => 'all',
                    'conditions' => [
                        [
                            'type' => 'cart_quantity',
                            'item_type' => OrderItem::TYPE_TICKET_SEAT,
                            'operator' => '==',
                            'value' => 2,
                        ],
                        [
                            'type' => 'cart_quantity',
                            'item_type' => OrderItem::TYPE_COMBO,
                            'item_codes' => ['COMBO_2G_PG'],
                            'operator' => '>=',
                            'value' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $movie = Movie::factory()->create();
        $room = Room::factory()->create([
            'rows' => 2,
            'columns' => 2,
            'total_seats' => 4,
            'is_active' => true,
        ]);

        $screening = Screening::factory()->create([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'price' => 100.00,
            'is_active' => true,
        ]);

        $seatA1 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 1,
            'seat_code' => 'A1',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $seatA2 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 2,
            'seat_code' => 'A2',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/payment-pricing-preview', [
            'screening_id' => $screening->id,
            'seat_ids' => [$seatA1->id, $seatA2->id],
            'products' => [
                ['code' => 'COMBO_2G_PG', 'quantity' => 1],
            ],
            'promotion_code' => 'PROMO-COMBO-TIPO-COMBO',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'total_discount' => 200.0,
                'total_price' => 500.0,
            ]);
    }

    public function test_pricing_preview_applies_when_condition_uses_product_item_type_for_combo_product(): void
    {
        Product::create([
            'code' => 'COMBO_2G_PG',
            'name' => 'Combo 2 gaseosas + pochoclo grande',
            'type' => Product::TYPE_COMBO,
            'unit_price' => 500.00,
            'currency' => 'ARS',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Promotion::create([
            'code' => 'PROMO-COMBO-TIPO-PRODUCT',
            'name' => '100% en 2 entradas con item_type product',
            'type' => Promotion::TYPE_PERCENTAGE,
            'is_active' => true,
            'is_automatic' => false,
            'is_stackable' => false,
            'priority' => 1,
            'settings' => [
                'percentage' => 100,
                'target_item_type' => OrderItem::TYPE_TICKET_SEAT,
                'conditions' => [
                    'aggregator' => 'all',
                    'conditions' => [
                        [
                            'type' => 'cart_quantity',
                            'item_type' => OrderItem::TYPE_TICKET_SEAT,
                            'operator' => '==',
                            'value' => 2,
                        ],
                        [
                            'type' => 'cart_quantity',
                            'item_type' => OrderItem::TYPE_PRODUCT,
                            'item_codes' => ['COMBO_2G_PG'],
                            'operator' => '>=',
                            'value' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $movie = Movie::factory()->create();
        $room = Room::factory()->create([
            'rows' => 2,
            'columns' => 2,
            'total_seats' => 4,
            'is_active' => true,
        ]);

        $screening = Screening::factory()->create([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'price' => 100.00,
            'is_active' => true,
        ]);

        $seatA1 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 1,
            'seat_code' => 'A1',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $seatA2 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 2,
            'seat_code' => 'A2',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/payment-pricing-preview', [
            'screening_id' => $screening->id,
            'seat_ids' => [$seatA1->id, $seatA2->id],
            'products' => [
                ['code' => 'COMBO_2G_PG', 'quantity' => 1],
            ],
            'promotion_code' => 'PROMO-COMBO-TIPO-PRODUCT',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'total_discount' => 200.0,
                'total_price' => 500.0,
            ]);
    }

    public function test_pricing_preview_with_multiple_items_and_combined_promotions(): void
    {
        Product::create([
            'code' => 'COMBO_2G_PG',
            'name' => 'Combo 2 gaseosas + pochoclo grande',
            'type' => Product::TYPE_COMBO,
            'unit_price' => 500.00,
            'currency' => 'ARS',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Product::create([
            'code' => 'GASEOSA_L',
            'name' => 'Gaseosa grande',
            'type' => Product::TYPE_PRODUCT,
            'unit_price' => 100.00,
            'currency' => 'ARS',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Promotion::create([
            'code' => 'AUTO10SEATS',
            'name' => '10% automático en entradas',
            'type' => Promotion::TYPE_PERCENTAGE,
            'is_active' => true,
            'is_automatic' => true,
            'is_stackable' => true,
            'priority' => 1,
            'settings' => [
                'percentage' => 10,
                'target_item_type' => OrderItem::TYPE_TICKET_SEAT,
            ],
        ]);

        Promotion::create([
            'code' => 'COMBO50',
            'name' => '50% en combo específico',
            'type' => Promotion::TYPE_PERCENTAGE,
            'is_active' => true,
            'is_automatic' => false,
            'is_stackable' => true,
            'priority' => 2,
            'settings' => [
                'percentage' => 50,
                'target_item_type' => OrderItem::TYPE_PRODUCT,
                'target_codes' => ['COMBO_2G_PG'],
            ],
        ]);

        $movie = Movie::factory()->create();
        $room = Room::factory()->create([
            'rows' => 2,
            'columns' => 2,
            'total_seats' => 4,
            'is_active' => true,
        ]);

        $screening = Screening::factory()->create([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'price' => 100.00,
            'is_active' => true,
        ]);

        $seatA1 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 1,
            'seat_code' => 'A1',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);
        $seatA2 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 2,
            'seat_code' => 'A2',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/payment-pricing-preview', [
            'screening_id' => $screening->id,
            'seat_ids' => [$seatA1->id, $seatA2->id],
            'products' => [
                ['code' => 'COMBO_2G_PG', 'quantity' => 1],
                ['code' => 'GASEOSA_L', 'quantity' => 2],
            ],
            'promotion_code' => 'COMBO50',
        ]);

        // subtotal: seats 200 + combo 500 + drinks 200 = 900
        // discounts: auto seats 10% => 20, combo 50% => 250, total => 270
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'seat_subtotal' => 200.0,
                'product_subtotal' => 700.0,
                'base_subtotal' => 900.0,
                'total_discount' => 270.0,
                'total_price' => 630.0,
            ]);

        $this->assertCount(2, $response->json('applied_promotions', []));
    }

    public function test_pricing_preview_for_three_tickets_with_2x1_charges_one_extra_ticket(): void
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

        $movie = Movie::factory()->create();
        $room = Room::factory()->create([
            'rows' => 2,
            'columns' => 2,
            'total_seats' => 4,
            'is_active' => true,
        ]);

        $screening = Screening::factory()->create([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'price' => 100.00,
            'is_active' => true,
        ]);

        $seatA1 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 1,
            'seat_code' => 'A1',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);
        $seatA2 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 2,
            'seat_code' => 'A2',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);
        $seatB1 = $room->seats()->create([
            'row_number' => 2,
            'seat_number' => 1,
            'seat_code' => 'B1',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/payment-pricing-preview', [
            'screening_id' => $screening->id,
            'seat_ids' => [$seatA1->id, $seatA2->id, $seatB1->id],
            'promotion_code' => 'PROMO2X1',
        ]);

        // 3 entradas de 100, promo 2x1 => descuenta 1 entrada (100), total 200.
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'seat_subtotal' => 300.0,
                'product_subtotal' => 0.0,
                'base_subtotal' => 300.0,
                'total_discount' => 100.0,
                'total_price' => 200.0,
            ]);
    }

    public function test_pricing_preview_returns_422_for_invalid_promotion_code(): void
    {
        $movie = Movie::factory()->create();
        $room = Room::factory()->create([
            'rows' => 1,
            'columns' => 1,
            'total_seats' => 1,
            'is_active' => true,
        ]);

        $screening = Screening::factory()->create([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'price' => 100.00,
            'is_active' => true,
        ]);

        $seatA1 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 1,
            'seat_code' => 'A1',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/payment-pricing-preview', [
            'screening_id' => $screening->id,
            'seat_ids' => [$seatA1->id],
            'promotion_code' => 'PROMO_NO_EXISTE',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
