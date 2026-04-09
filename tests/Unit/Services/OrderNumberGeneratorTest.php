<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\Screening;
use App\Models\User;
use App\Services\OrderNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_starts_at_one_when_no_orders_exist(): void
    {
        Carbon::setTestNow('2026-04-09 10:00:00');

        $expected = $this->makeOrderNumber('20260409', 1);
        $generated = OrderNumberGenerator::generate();

        $this->assertSame($expected, $generated);
    }

    public function test_generate_uses_last_order_number_even_if_soft_deleted(): void
    {
        Carbon::setTestNow('2026-04-09 10:00:00');

        $orderNumber = $this->makeOrderNumber('20260409', 47);
        $order = $this->createOrder($orderNumber, Carbon::now());
        $order->delete();

        $expected = $this->makeOrderNumber('20260409', 48);
        $generated = OrderNumberGenerator::generate();

        $this->assertSame($expected, $generated);
    }

    public function test_generate_uses_latest_sequence_not_row_count(): void
    {
        Carbon::setTestNow('2026-04-09 10:00:00');

        $this->createOrder($this->makeOrderNumber('20260409', 1), Carbon::now());
        $this->createOrder($this->makeOrderNumber('20260409', 5), Carbon::now());

        $expected = $this->makeOrderNumber('20260409', 6);
        $generated = OrderNumberGenerator::generate();

        $this->assertSame($expected, $generated);
    }

    private function createOrder(string $orderNumber, Carbon $createdAt): Order
    {
        $user = User::factory()->create();
        $screening = Screening::factory()->create();

        return Order::create([
            'uuid' => (string) Str::uuid(),
            'order_number' => $orderNumber,
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'customer_phone' => null,
            'user_id' => $user->id,
            'screening_id' => $screening->id,
            'total_amount' => 100.00,
            'currency' => 'ARS',
            'status' => Order::STATUS_PENDING,
            'purchase_device' => 'web',
            'ip_address' => '127.0.0.1',
            'reserved_until' => $createdAt->copy()->addMinutes(10),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function makeOrderNumber(string $date, int $sequence): string
    {
        $sequencePart = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        $checkDigit = OrderNumberGenerator::calculateCheckDigit($date . $sequencePart);

        return sprintf('ORD-%s-%s%d', $date, $sequencePart, $checkDigit);
    }
}
