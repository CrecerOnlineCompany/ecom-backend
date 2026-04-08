<?php

namespace Tests\Unit\Services;

use App\Enums\PaymentStatus;
use App\Models\Movie;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use App\Models\Screening;
use App\Models\ScreeningSeat;
use App\Models\Ticket;
use App\Models\TicketDetail;
use App\Models\User;
use App\Services\ManualOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_order_uses_same_seat_pricing_rule_and_persists_seat_detail_data(): void
    {
        $adminUser = User::factory()->create();
        $this->actingAs($adminUser);

        $movie = Movie::factory()->create();
        $room = Room::factory()->create([
            'rows' => 2,
            'columns' => 3,
            'total_seats' => 6,
            'non_number' => true,
            'is_active' => true,
        ]);

        $screening = Screening::factory()->create([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'price' => 100.00,
            'is_active' => true,
        ]);

        $seat1 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 1,
            'seat_code' => 'A1',
            'type' => 'standard',
            'price_modifier' => 1.50,
            'is_active' => true,
        ]);

        $seat2 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 2,
            'seat_code' => 'A2',
            'type' => 'standard',
            'price_modifier' => 1.00,
            'is_active' => true,
        ]);

        $seat3 = $room->seats()->create([
            'row_number' => 1,
            'seat_number' => 3,
            'seat_code' => 'A3',
            'type' => 'standard',
            'price_modifier' => 0.00, // fallback esperado a 1.0
            'is_active' => true,
        ]);

        /** @var ManualOrderService $service */
        $service = app(ManualOrderService::class);

        $result = $service->createManualOrder(
            $screening->id,
            [$seat1->id, $seat2->id, $seat3->id],
            'cliente@test.com',
            'Cliente Test',
            '123456789'
        );

        $this->assertTrue($result['success'], $result['message'] ?? 'Manual order failed');
        $this->assertArrayHasKey('order_id', $result);

        $order = Order::findOrFail($result['order_id']);
        $this->assertEquals(PaymentStatus::STATUS_COMPLETED, $order->status);
        $this->assertEquals(350.00, (float) $order->total_amount); // 150 + 100 + 100

        $orderItems = OrderItem::where('order_id', $order->id)
            ->where('item_type', OrderItem::TYPE_TICKET_SEAT)
            ->orderBy('id')
            ->get();
        $this->assertCount(3, $orderItems);
        $this->assertEquals(350.00, (float) $orderItems->sum('subtotal'));

        $tickets = Ticket::where('order_id', $order->id)
            ->orderBy('seat_id')
            ->get()
            ->keyBy('seat_id');

        $this->assertCount(3, $tickets);
        $this->assertEquals(150.00, (float) $tickets[$seat1->id]->price);
        $this->assertEquals(100.00, (float) $tickets[$seat2->id]->price);
        $this->assertEquals(100.00, (float) $tickets[$seat3->id]->price);

        $details = TicketDetail::whereIn('ticket_id', $tickets->pluck('id')->all())
            ->orderBy('seat_id')
            ->get()
            ->keyBy('seat_id');

        $this->assertCount(3, $details);
        $this->assertEquals('A1', $details[$seat1->id]->seat_code);
        $this->assertEquals(1, (int) $details[$seat1->id]->row_number);
        $this->assertEquals(1, (int) $details[$seat1->id]->seat_number);
        $this->assertTrue((bool) $details[$seat1->id]->room_non_number);
        $this->assertEquals((float) $tickets[$seat1->id]->price, (float) $details[$seat1->id]->price);

        $this->assertEquals('A2', $details[$seat2->id]->seat_code);
        $this->assertEquals(1, (int) $details[$seat2->id]->row_number);
        $this->assertEquals(2, (int) $details[$seat2->id]->seat_number);
        $this->assertTrue((bool) $details[$seat2->id]->room_non_number);
        $this->assertEquals((float) $tickets[$seat2->id]->price, (float) $details[$seat2->id]->price);

        $this->assertEquals('A3', $details[$seat3->id]->seat_code);
        $this->assertEquals(1, (int) $details[$seat3->id]->row_number);
        $this->assertEquals(3, (int) $details[$seat3->id]->seat_number);
        $this->assertTrue((bool) $details[$seat3->id]->room_non_number);
        $this->assertEquals((float) $tickets[$seat3->id]->price, (float) $details[$seat3->id]->price);

        $soldSeats = ScreeningSeat::where('screening_id', $screening->id)
            ->where('order_id', $order->id)
            ->where('status', ScreeningSeat::STATUS_SOLD)
            ->count();

        $this->assertEquals(3, $soldSeats);
    }
}
