<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\PaymentProviderTicket;
use App\Models\Screening;
use App\Services\PaymentProviders\Handlers\MercadoPagoPointHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MercadoPagoPointHandlerIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotates_idempotency_key_when_mp_returns_already_used(): void
    {
        [$order, $provider] = $this->createOrderAndProvider();

        $originalKey = (string) Str::uuid();
        $paymentTicket = PaymentProviderTicket::create([
            'order_id' => $order->id,
            'ticket_id' => null,
            'payment_provider_id' => $provider->id,
            'status' => 'pending',
            'response_data' => [
                'idempotency_key' => $originalKey,
            ],
        ]);

        $sentKeys = [];

        Http::fake(function (HttpRequest $request) use (&$sentKeys) {
            if ($request->url() === 'https://api.mercadopago.com/v1/orders') {
                $sentKeys[] = $request->header('X-Idempotency-Key')[0] ?? null;

                if (count($sentKeys) === 1) {
                    return Http::response([
                        'errors' => [
                            [
                                'code' => 'idempotency_key_already_used',
                                'message' => 'X-Idempotency-Key already used. Please retry with a different value.',
                            ],
                        ],
                    ], 409);
                }

                return Http::response([
                    'id' => 'MP-ORDER-RETRY-OK-1',
                ], 201);
            }

            return Http::response([], 200);
        });

        $handler = new MercadoPagoPointHandler($provider);
        $result = $handler->processPayment($paymentTicket, [
            'total_price' => 16.00,
            'seat_count' => 1,
            'idempotency_key' => $originalKey,
        ]);

        $this->assertTrue($result['success'] ?? false);
        $this->assertCount(2, $sentKeys);
        $this->assertSame($originalKey, $sentKeys[0]);
        $this->assertNotSame($originalKey, $sentKeys[1]);

        $paymentTicket->refresh();
        $this->assertSame($sentKeys[1], $paymentTicket->response_data['idempotency_key'] ?? null);
        $this->assertSame($originalKey, $paymentTicket->response_data['previous_idempotency_key'] ?? null);
        $this->assertTrue((bool) ($paymentTicket->response_data['idempotency_key_rotated'] ?? false));
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

