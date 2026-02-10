<?php

namespace App\Console\Commands;

use App\Models\PaymentProvider;
use App\Models\PaymentProviderTicket;
use App\Models\Ticket;
use App\Models\Screening;
use App\Services\PaymentProviders\PaymentProviderManager;
use Illuminate\Console\Command;

class TestMercadoPago extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:test-mercado-pago {--seat=1592 : Seat ID to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Mercado Pago payment processing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->line('🧪 Testing Mercado Pago Payment...');
        
        try {
            // Create a test ticket
            $screening = Screening::find(197);
            if (!$screening) {
                $this->error('❌ Screening 197 not found');
                return Command::FAILURE;
            }

            $seatId = $this->option('seat');
            
            // Check if seat is already booked
            $existing = Ticket::where('screening_id', 197)
                ->where('seat_id', $seatId)
                ->whereIn('status', ['confirmed', 'pending_payment', 'processing'])
                ->first();
            
            if ($existing) {
                $this->warn("⚠️  Seat {$seatId} already has a ticket in status: {$existing->status}");
                $this->line("Deleting it first...");
                $existing->delete();
            }

            $ticket = Ticket::create([
                'screening_id' => 197,
                'seat_id' => $seatId,
                'user_id' => 1,
                'ticket_number' => 'TKT-TEST-' . time(),
                'price' => 100,
                'status' => 'pending_payment',
            ]);

            $this->line("✅ Created test ticket: {$ticket->ticket_number}");

            // Get payment provider
            $paymentProvider = PaymentProvider::find(1);
            if (!$paymentProvider) {
                $this->error('❌ Payment provider not found');
                return Command::FAILURE;
            }

            $this->line('📋 Provider config:');
            $config = $paymentProvider->config;
            $this->line('  - Type: ' . gettype($config));
            if (is_array($config)) {
                $this->line('  - Keys: ' . implode(', ', array_keys($config)));
                $this->line('  - Token prefix: ' . substr($config['access_token'] ?? 'NONE', 0, 30) . '...');
            }

            // Create payment ticket
            $paymentTicket = PaymentProviderTicket::create([
                'ticket_id' => $ticket->id,
                'payment_provider_id' => 1,
                'status' => 'pending',
            ]);

            $this->line("✅ Created payment ticket record");

            // Get handler via PaymentProviderManager
            $manager = app(PaymentProviderManager::class);
            $handler = $manager->getHandler($paymentProvider);
            $this->line("🚀 Calling handler->processPayment()...");
            
            $result = $handler->processPayment($paymentTicket, []);
            
            if ($result['success']) {
                $this->info("✅ Payment processing successful!");
                $this->line("  - Transaction ID: {$result['transaction_id']}");
                $this->line("  - Redirect URL: {$result['redirect_url']}");
            } else {
                $this->error("❌ Payment processing failed!");
                $this->line("  - Message: {$result['message']}");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Exception: " . $e->getMessage());
            $this->error("File: {$e->getFile()} Line: {$e->getLine()}");
            return Command::FAILURE;
        }
    }
}
