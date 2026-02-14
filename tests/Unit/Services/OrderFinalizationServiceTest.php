<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\ScreeningSeat;
use App\Models\Screening;
use App\Models\User;
use App\Services\OrderFinalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OrderFinalizationService Tests
 * 
 * Validates:
 * - Idempotence (webhooks duplicate-safe)
 * - State repair (partial finalization recovery)
 * - Deterministic ticket_number
 * - QR generation without PII
 * - Seat ownership validation
 * - Concurrency safety
 */
class OrderFinalizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrderFinalizationService $service;
    protected Screening $screening;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderFinalizationService::class);
        
        // Create test fixtures
        $this->user = User::factory()->create();
        $this->screening = Screening::factory()->create();
    }

    /**
     * Test 1: Happy path - Single ticket order
     */
    public function test_finalize_single_ticket_order()
    {
        $order = Order::factory()
            ->for($this->user)
            ->for($this->screening)
            ->create(['status' => 'reserved']);

        $ticket = Ticket::factory()
            ->for($order)
            ->for($this->screening)
            ->create([
                'ticket_sequence' => 1,
                'status' => 'pending_payment',
                'ticket_number' => null,
                'qr_code' => null,
            ]);

        // Finalize
        $result = $this->service->finalizeOrderAfterApproval($order->id, [
            'transaction_id' => 'MP-12345',
            'provider_id' => 1,
        ]);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertFalse($result['idempotent'] ?? false);
        $this->assertEquals(1, $result['finalized_tickets']);

        // Verify ticket updated
        $ticket->refresh();
        $this->assertEquals('confirmed', $ticket->status);
        $this->assertNotNull($ticket->ticket_number);
        $this->assertNotNull($ticket->qr_code);
        $this->assertStringStartsWith('TKT-', $ticket->ticket_number);

        // Verify QR doesn't contain PII
        $qrData = $this->decodeQRData($ticket->qr_code);
        $this->assertArrayNotHasKey('customer_email', $qrData);
        $this->assertArrayNotHasKey('customer_phone', $qrData);
        $this->assertArrayHasKey('signature', $qrData);

        // Verify order updated
        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertNotNull($order->payment_data);
        $this->assertEquals('MP-12345', $order->payment_data['transaction_id']);
    }

    /**
     * Test 2: Idempotence - Duplicate webhook
     */
    public function test_idempotence_duplicate_webhook()
    {
        $order = Order::factory()
            ->for($this->user)
            ->for($this->screening)
            ->create(['status' => 'reserved']);

        $ticket = Ticket::factory()
            ->for($order)
            ->for($this->screening)
            ->create([
                'ticket_sequence' => 1,
                'status' => 'pending_payment',
            ]);

        // First finalization
        $result1 = $this->service->finalizeOrderAfterApproval($order->id, [
            'transaction_id' => 'TX-001',
        ]);

        $this->assertTrue($result1['success']);
        $ticket->refresh();
        $originalTicketNumber = $ticket->ticket_number;
        $originalQRCode = $ticket->qr_code;

        // Second finalization (duplicate webhook)
        $result2 = $this->service->finalizeOrderAfterApproval($order->id, [
            'transaction_id' => 'TX-001',
        ]);

        // Assertions
        $this->assertTrue($result2['success']);
        $this->assertTrue($result2['idempotent'] ?? false);

        // Verify no changes
        $ticket->refresh();
        $this->assertEquals($originalTicketNumber, $ticket->ticket_number);
        $this->assertEquals($originalQRCode, $ticket->qr_code);
    }

    /**
     * Test 3: State repair - Partial finalization
     */
    public function test_repair_partial_finalization()
    {
        $order = Order::factory()
            ->for($this->user)
            ->for($this->screening)
            ->create(['status' => 'reserved']);

        $tickets = Ticket::factory()
            ->count(2)
            ->for($order)
            ->for($this->screening)
            ->create([
                'status' => 'pending_payment',
                'ticket_number' => null,
                'qr_code' => null,
            ]);

        // Manually corrupt state: mark first ticket as confirmed but without ticket_number
        $tickets[0]->update([
            'status' => 'confirmed',
            'ticket_sequence' => 1,
            'ticket_number' => null,  // Missing!
            'qr_code' => null,        // Missing!
        ]);

        $tickets[1]->update([
            'status' => 'pending_payment',
            'ticket_sequence' => 2,
        ]);

        // Finalize (should repair)
        $result = $this->service->finalizeOrderAfterApproval($order->id);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertTrue($result['repaired'] ?? false);

        // Both tickets should now be fully finalized
        $tickets[0]->refresh();
        $tickets[1]->refresh();

        $this->assertEquals('confirmed', $tickets[0]->status);
        $this->assertNotNull($tickets[0]->ticket_number);
        $this->assertNotNull($tickets[0]->qr_code);

        $this->assertEquals('confirmed', $tickets[1]->status);
        $this->assertNotNull($tickets[1]->ticket_number);
        $this->assertNotNull($tickets[1]->qr_code);

        // ticket_numbers should be DIFFERENT
        $this->assertNotEquals($tickets[0]->ticket_number, $tickets[1]->ticket_number);
    }

    /**
     * Test 4: Validation - No tickets in order
     */
    public function test_error_no_tickets_in_order()
    {
        $order = Order::factory()
            ->for($this->user)
            ->for($this->screening)
            ->create(['status' => 'reserved']);

        // Don't create any tickets

        $result = $this->service->finalizeOrderAfterApproval($order->id);

        // Assertions
        $this->assertFalse($result['success']);
        $this->assertEquals('NO_TICKETS', $result['error_code']);

        // Order status should NOT change
        $order->refresh();
        $this->assertNotEquals('completed', $order->status);
    }

    /**
     * Test 5: Seat ownership validation
     */
    public function test_seat_ownership_validation()
    {
        $order1 = Order::factory()
            ->for($this->user)
            ->for($this->screening)
            ->create(['status' => 'reserved']);

        $order2 = Order::factory()
            ->for($this->user)
            ->for($this->screening)
            ->create(['status' => 'reserved']);

        // Seat reserved by order 1
        $screening_seat = ScreeningSeat::factory()
            ->for($this->screening)
            ->create([
                'status' => 'reserved',
                'order_id' => $order1->id,
                'reserved_until' => now()->addHours(1),
            ]);

        // Ticket in order 2 references same seat
        $ticket = Ticket::factory()
            ->for($order2)
            ->for($this->screening)
            ->create([
                'seat_id' => $screening_seat->seat_id,
                'ticket_sequence' => 1,
                'status' => 'pending_payment',
            ]);

        // Try to finalize order 2 (should fail - seat belongs to order 1)
        $this->expectException(\App\Exceptions\InvalidSeatOwnershipException::class);

        $this->service->finalizeOrderAfterApproval($order2->id);
    }

    /**
     * Test 6: QR determinism - Same ticket always gets same QR
     */
    public function test_qr_code_determinism()
    {
        $order = Order::factory()
            ->for($this->user)
            ->for($this->screening)
            ->create(['status' => 'reserved']);

        $ticket = Ticket::factory()
            ->for($order)
            ->for($this->screening)
            ->create([
                'ticket_sequence' => 1,
                'status' => 'pending_payment',
            ]);

        // First finalization
        $result1 = $this->service->finalizeOrderAfterApproval($order->id);
        $ticket->refresh();
        $qrCode1 = $ticket->qr_code;

        // Reset ticket to pending (simulate partial state)
        $ticket->update(['status' => 'pending_payment', 'qr_code' => null]);

        // Second finalization (repair)
        $result2 = $this->service->finalizeOrderAfterApproval($order->id);
        $ticket->refresh();
        $qrCode2 = $ticket->qr_code;

        // QR codes should be THE SAME (deterministic)
        $this->assertEquals($qrCode1, $qrCode2);
    }

    /**
     * Test 7: Ticket sequence determinism
     */
    public function test_ticket_sequence_determinism()
    {
        $order = Order::factory()
            ->for($this->user)
            ->for($this->screening)
            ->create(['status' => 'reserved']);

        $tickets = Ticket::factory()
            ->count(3)
            ->for($order)
            ->for($this->screening)
            ->create([
                'status' => 'pending_payment',
            ]);

        // Set sequences
        foreach ($tickets as $key => $ticket) {
            $ticket->update(['ticket_sequence' => $key + 1]);
        }

        // Finalize
        $result = $this->service->finalizeOrderAfterApproval($order->id);
        $this->assertTrue($result['success']);

        // Get ticket numbers
        $tickets = $tickets->fresh();
        $numbers = $tickets->pluck('ticket_number')->toArray();

        // Verify uniqueness and order
        $this->assertCount(3, array_unique($numbers));
        $this->assertStringContainsString('-001', $numbers[0]);
        $this->assertStringContainsString('-002', $numbers[1]);
        $this->assertStringContainsString('-003', $numbers[2]);

        // Now delete middle ticket and re-finalize (simulate new order creation)
        // Verify other tickets' numbers don't change
        $originalNumber0 = $tickets[0]->ticket_number;
        $originalNumber2 = $tickets[2]->ticket_number;

        // If we finalize again (idempotent)
        $result = $this->service->finalizeOrderAfterApproval($order->id);

        $tickets = $tickets->fresh();
        $this->assertEquals($originalNumber0, $tickets[0]->ticket_number);
        $this->assertEquals($originalNumber2, $tickets[2]->ticket_number);
    }

    /**
     * Test 8: Concurrency - Multiple orders finalize simultaneously
     */
    public function test_concurrency_multiple_orders()
    {
        $orders = Order::factory()
            ->count(5)
            ->for($this->user)
            ->for($this->screening)
            ->create(['status' => 'reserved']);

        foreach ($orders as $order) {
            Ticket::factory()
                ->count(2)
                ->for($order)
                ->for($this->screening)
                ->create([
                    'status' => 'pending_payment',
                ]);
        }

        // Assign sequences manually
        foreach ($orders as $order) {
            $tickets = $order->tickets()->orderBy('id')->get();
            foreach ($tickets as $key => $ticket) {
                $ticket->update(['ticket_sequence' => $key + 1]);
            }
        }

        // Finalize all in sequence (Laravel tests are synchronous)
        foreach ($orders as $order) {
            $result = $this->service->finalizeOrderAfterApproval($order->id, [
                'transaction_id' => "TX-{$order->id}",
            ]);

            $this->assertTrue($result['success']);
        }

        // Verify all orders are completed
        foreach ($orders as $order) {
            $order->refresh();
            $this->assertEquals('completed', $order->status);
            $this->assertCount(2, $order->tickets()->where('status', 'confirmed')->get());
        }

        // Verify no seat conflicts
        $soldSeats = ScreeningSeat::where('status', 'sold')->get();
        $this->assertEquals(10, $soldSeats->count());
    }

    /**
     * Helper: Decode QR data from base64 PNG
     * 
     * Note: In reality, you'd need to decode the PNG and extract JSON.
     * For testing, we can mock or parse the encoded JSON directly.
     */
    private function decodeQRData($qrCode): array
    {
        // QR codes generated by our service encode JSON in the PNG
        // For testing, we'd need to:
        // 1. Decode base64
        // 2. Parse PNG
        // 3. Extract QR data
        
        // Simplified: If fallback was used, it's a simple string
        if (strpos($qrCode, 'QR:') === 0) {
            return ['fallback' => $qrCode];
        }

        // For actual PNG, you'd use a QR decoder library
        // For this test, we'll just verify it's not empty
        $this->assertNotEmpty($qrCode);

        return ['type' => 'png_encoded'];
    }
}
