<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\PaymentProviderTicket;
use App\Models\Screening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentIdempotencyResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_returns_new_key_when_old_key_was_rotated(): void
    {
        [$order, $provider] = $this->createOrderAndProvider();

        $oldKey = (string) Str::uuid();
        $newKey = (string) Str::uuid();

        PaymentProviderTicket::create([
            'order_id' => $order->id,
            'ticket_id' => null,
            'payment_provider_id' => $provider->id,
            'status' => 'processing',
            'transaction_id' => 'MP-ORDER-ROTATED-1',
            'response_data' => [
                'idempotency_key' => $newKey,
                'previous_idempotency_key' => $oldKey,
                'idempotency_key_rotated' => true,
            ],
        ]);

        $response = $this->postJson('/api/payment-idempotency/resolve', [
            'order_number' => $order->order_number,
            'old_idempotency_key' => $oldKey,
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'order_number' => $order->order_number,
            'old_idempotency_key' => $oldKey,
            'new_idempotency_key' => $newKey,
            'rotated' => true,
            'matches_current' => false,
        ]);
    }

    public function test_resolve_returns_current_key_when_old_is_already_current(): void
    {
        [$order, $provider] = $this->createOrderAndProvider();

        $currentKey = (string) Str::uuid();

        PaymentProviderTicket::create([
            'order_id' => $order->id,
            'ticket_id' => null,
            'payment_provider_id' => $provider->id,
            'status' => 'processing',
            'transaction_id' => 'MP-ORDER-CURRENT-1',
            'response_data' => [
                'idempotency_key' => $currentKey,
            ],
        ]);

        $response = $this->postJson('/api/payment-idempotency/resolve', [
            'order_number' => $order->order_number,
            'old_idempotency_key' => $currentKey,
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'order_number' => $order->order_number,
            'old_idempotency_key' => $currentKey,
            'new_idempotency_key' => $currentKey,
            'rotated' => false,
            'matches_current' => true,
        ]);
    }

    public function test_resolve_returns_not_found_when_key_does_not_belong_to_order(): void
    {
        [$order, $provider] = $this->createOrderAndProvider();

        PaymentProviderTicket::create([
            'order_id' => $order->id,
            'ticket_id' => null,
            'payment_provider_id' => $provider->id,
            'status' => 'processing',
            'transaction_id' => 'MP-ORDER-NOMATCH-1',
            'response_data' => [
                'idempotency_key' => (string) Str::uuid(),
            ],
        ]);

        $response = $this->postJson('/api/payment-idempotency/resolve', [
            'order_number' => $order->order_number,
            'old_idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(404)->assertJson([
            'success' => false,
            'error_code' => 'IDEMPOTENCY_KEY_NOT_FOUND_FOR_ORDER',
        ]);
    }

    private function createOrderAndProvider(): array
    {
        $screening = Screening::factory()->create();

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-TEST-' . Str::upper(Str::random(8)),
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'screening_id' => $screening->id,
            'total_amount' => 100.00,
            'currency' => 'ARS',
            'status' => Order::STATUS_RESERVED,
            'reserved_until' => now()->addMinutes(10),
        ]);

        $provider = PaymentProvider::create([
            'name' => 'mercado_pago_terminal',
            'display_name' => 'Mercado Pago Point',
            'is_active' => true,
            'config' => [
                'access_token' => 'TEST-TOKEN',
                'terminal_id' => 'NEWLAND_N950__TEST',
                'store_id' => 'STORE_TEST',
                'supports_terminal' => true,
            ],
        ]);

        return [$order, $provider];
    }
}

