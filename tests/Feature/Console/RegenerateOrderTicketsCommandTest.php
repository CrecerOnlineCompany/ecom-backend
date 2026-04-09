<?php

namespace Tests\Feature\Console;

use App\Models\Movie;
use App\Models\Order;
use App\Models\Room;
use App\Models\Screening;
use App\Models\ScreeningSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegenerateOrderTicketsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_tickets_from_reserved_seats_and_sells_them(): void
    {
        $user = User::factory()->create();
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

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-CRON-001',
            'customer_name' => 'Cliente Cron',
            'customer_email' => 'cron@test.com',
            'customer_phone' => '123456789',
            'user_id' => $user->id,
            'screening_id' => $screening->id,
            'total_amount' => 200.00,
            'currency' => 'ARS',
            'status' => Order::STATUS_PENDING,
            'purchase_device' => 'web',
            'ip_address' => '127.0.0.1',
            'reserved_until' => now()->addMinutes(30),
        ]);

        ScreeningSeat::create([
            'screening_id' => $screening->id,
            'seat_id' => $seatA1->id,
            'status' => ScreeningSeat::STATUS_RESERVED,
            'reserved_until' => now()->addMinutes(30),
            'order_id' => $order->id,
            'reserved_by_type' => 'order',
            'reserved_by_id' => (string) $order->id,
        ]);

        ScreeningSeat::create([
            'screening_id' => $screening->id,
            'seat_id' => $seatA2->id,
            'status' => ScreeningSeat::STATUS_RESERVED,
            'reserved_until' => now()->addMinutes(30),
            'order_id' => $order->id,
            'reserved_by_type' => 'order',
            'reserved_by_id' => (string) $order->id,
        ]);

        $this->artisan('orders:regenerate-tickets', [
            '--order-id' => $order->id,
            '--force' => true,
        ])->assertExitCode(0);

        $order->refresh();
        $this->assertEquals(Order::STATUS_COMPLETED, $order->status);

        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseHas('tickets', [
            'order_id' => $order->id,
            'seat_id' => $seatA1->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('tickets', [
            'order_id' => $order->id,
            'seat_id' => $seatA2->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('screening_seats', [
            'screening_id' => $screening->id,
            'seat_id' => $seatA1->id,
            'order_id' => $order->id,
            'status' => ScreeningSeat::STATUS_SOLD,
        ]);
        $this->assertDatabaseHas('screening_seats', [
            'screening_id' => $screening->id,
            'seat_id' => $seatA2->id,
            'order_id' => $order->id,
            'status' => ScreeningSeat::STATUS_SOLD,
        ]);
    }
}
