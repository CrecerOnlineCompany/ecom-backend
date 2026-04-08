<?php

namespace Tests\Unit\Services;

use App\Models\Movie;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\Screening;
use App\Models\User;
use App\Services\OrderItemPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderItemPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_and_sync_seat_items(): void
    {
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
            'price' => 200.00,
            'is_active' => true,
        ]);

        $seatA1 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 1,
            'seat_code' => 'A1',
            'type' => 'standard',
            'price_modifier' => 1.50,
            'is_active' => true,
        ]);
        $seatA2 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 2,
            'seat_code' => 'A2',
            'type' => 'standard',
            'price_modifier' => 0.00, // fallback esperado a 1.0
            'is_active' => true,
        ]);

        /** @var OrderItemPricingService $service */
        $service = app(OrderItemPricingService::class);

        $pricing = $service->calculateSeatItems($screening, [$seatA1->id, $seatA2->id]);

        $this->assertEquals(500.00, (float) $pricing['total_amount']); // 300 + 200
        $this->assertEquals(300.00, (float) $pricing['seat_prices'][$seatA1->id]);
        $this->assertEquals(200.00, (float) $pricing['seat_prices'][$seatA2->id]);
        $this->assertCount(2, $pricing['items']);

        $user = User::factory()->create();
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-TEST-ORDER-ITEMS',
            'customer_name' => 'Cliente',
            'customer_email' => 'cliente@test.com',
            'customer_phone' => '123',
            'user_id' => $user->id,
            'screening_id' => $screening->id,
            'total_amount' => $pricing['total_amount'],
            'currency' => 'ARS',
            'status' => Order::STATUS_RESERVED,
            'purchase_device' => 'web',
            'ip_address' => '127.0.0.1',
            'reserved_until' => now()->addMinutes(5),
        ]);

        $service->syncSeatItems($order, $pricing['items']);
        $order->refresh();

        $items = $order->orderItems()
            ->where('item_type', OrderItem::TYPE_TICKET_SEAT)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $items);
        $this->assertEquals(500.00, (float) $items->sum('subtotal'));

        $priceMap = $service->getSeatPriceMapForOrder($order);
        $this->assertEquals(300.00, (float) $priceMap[$seatA1->id]);
        $this->assertEquals(200.00, (float) $priceMap[$seatA2->id]);
    }

    public function test_calculate_priced_items_with_2x1_promotion_code(): void
    {
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

        Promotion::create([
            'code' => 'PROMO2X1',
            'name' => 'Promo 2x1',
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

        /** @var OrderItemPricingService $service */
        $service = app(OrderItemPricingService::class);

        $pricing = $service->calculatePricedItems(
            $screening,
            [$seatA1->id, $seatA2->id],
            ['promotion_code' => 'PROMO2X1']
        );

        $this->assertEquals(200.00, (float) $pricing['base_subtotal']);
        $this->assertEquals(100.00, (float) $pricing['total_discount']);
        $this->assertEquals(100.00, (float) $pricing['total_amount']);
        $this->assertCount(1, $pricing['applied_promotions']);

        $discountItems = array_values(array_filter($pricing['items'], function ($item) {
            return ($item['item_type'] ?? null) === OrderItem::TYPE_PROMOTION_DISCOUNT;
        }));

        $this->assertCount(1, $discountItems);
        $this->assertEquals(-100.00, (float) $discountItems[0]['subtotal']);
    }

    public function test_calculate_priced_items_with_concession_products_and_combo(): void
    {
        config()->set('concessions.products', [
            'POCHO_SMALL' => [
                'name' => 'Pochoclo chico',
                'type' => 'product',
                'unit_price' => 300.00,
                'currency' => 'ARS',
                'is_active' => true,
            ],
            'COMBO_POCHO_GASEOSA' => [
                'name' => 'Combo pochoclo + gaseosa',
                'type' => 'combo',
                'unit_price' => 700.00,
                'currency' => 'ARS',
                'is_active' => true,
            ],
        ]);

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

        /** @var OrderItemPricingService $service */
        $service = app(OrderItemPricingService::class);

        $pricing = $service->calculatePricedItems(
            $screening,
            [$seatA1->id],
            [
                'products' => [
                    ['code' => 'POCHO_SMALL', 'quantity' => 2],
                    ['code' => 'COMBO_POCHO_GASEOSA', 'quantity' => 1],
                ],
            ]
        );

        $this->assertEquals(100.00, (float) $pricing['seat_subtotal']);
        $this->assertEquals(1300.00, (float) $pricing['product_subtotal']);
        $this->assertEquals(1400.00, (float) $pricing['base_subtotal']);
        $this->assertEquals(1400.00, (float) $pricing['total_amount']);

        $productItems = array_values(array_filter($pricing['items'], function ($item) {
            return in_array($item['item_type'] ?? null, [OrderItem::TYPE_PRODUCT, OrderItem::TYPE_COMBO], true);
        }));

        $this->assertCount(2, $productItems);
    }

    public function test_calculate_priced_items_throws_for_invalid_product_code(): void
    {
        config()->set('concessions.products', [
            'POCHO_SMALL' => [
                'name' => 'Pochoclo chico',
                'type' => 'product',
                'unit_price' => 300.00,
                'currency' => 'ARS',
                'is_active' => true,
            ],
        ]);

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

        /** @var OrderItemPricingService $service */
        $service = app(OrderItemPricingService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Producto inválido o inactivo');

        $service->calculatePricedItems(
            $screening,
            [$seatA1->id],
            [
                'products' => [
                    ['code' => 'INVALID_CODE', 'quantity' => 1],
                ],
            ]
        );
    }
}
