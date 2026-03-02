<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class CheckOrderPayments extends Command
{
    protected $signature = 'order:check-payments {--status= : Filter by order status} {--payment-status= : Filter by payment status (approved|pending|declined)} {--without-payment} {--recent=24 : Show last N hours} {--limit=20} {--cinema= : Filter by cinema name}';
    protected $description = 'Check orders and their payment status';

    public function handle(): int
    {
        $query = Order::with(['paymentTickets', 'screening.cinema', 'screening.movie', 'user']);

        // Filter by order status
        if ($this->option('status')) {
            $query->where('status', $this->option('status'));
        }

        // Filter by cinema
        if ($this->option('cinema')) {
            $query->whereHas('screening.cinema', function ($q) {
                $q->where('name', 'like', '%' . $this->option('cinema') . '%');
            });
        }

        // Recent orders
        $hours = (int)$this->option('recent');
        if ($hours > 0) {
            $query->where('created_at', '>=', now()->subHours($hours));
        }

        $orders = $query->latest()->limit($this->option('limit'))->get();

        if ($orders->isEmpty()) {
            $this->warn('No orders found with selected filters');
            return 0;
        }

        // Filter by payment status
        if ($this->option('payment-status') || $this->option('without-payment')) {
            $orders = $orders->filter(function ($order) {
                $paymentStatus = $order->paymentTickets->first()?->status ?? 'none';

                if ($this->option('without-payment')) {
                    return $paymentStatus === 'none';
                }

                return $paymentStatus === $this->option('payment-status');
            });

            if ($orders->isEmpty()) {
                $this->warn('No orders found with selected payment status');
                return 0;
            }
        }

        $this->info('=== Order Payment Status ===');
        $this->newLine();

        $headers = ['Order', 'Cinema', 'Movie', 'User', 'Amount', 'Payment Status', 'Provider', 'Created'];
        $rows = [];

        foreach ($orders as $order) {
            $payment = $order->paymentTickets->first();
            $paymentStatus = $payment?->status ?? 'none';

            $statusColor = match($paymentStatus) {
                'approved' => '<fg=green>✓ Approved</>',
                'pending' => '<fg=yellow>⏳ Pending</>',
                'processing' => '<fg=cyan>⟳ Processing</>',
                'declined' => '<fg=red>✗ Declined</>',
                'none' => '<fg=red>✗ NO PAYMENT</>',
                default => $paymentStatus,
            };

            $rows[] = [
                $order->order_number ?? '?' . $order->id,
                $order->screening->cinema->name ?? 'N/A',
                $order->screening->movie->title ?? 'N/A',
                $order->user->name ?? 'Guest',
                '$' . number_format($order->total_amount, 2),
                $statusColor,
                $payment?->paymentProvider->name ?? '—',
                $order->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info('=== Summary ===');

        $total = $orders->count();
        $withPayment = $orders->filter(fn($o) => $o->paymentTickets->count() > 0)->count();
        $withoutPayment = $total - $withPayment;
        $approved = $orders->filter(fn($o) => $o->paymentTickets->first()?->status === 'approved')->count();
        $pending = $orders->filter(fn($o) => $o->paymentTickets->first()?->status === 'pending')->count();
        $declined = $orders->filter(fn($o) => $o->paymentTickets->first()?->status === 'declined')->count();

        $this->line("<fg=cyan>Total Orders:</> $total");
        $this->line("<fg=green>With Payment:</> $withPayment");
        $this->line("<fg=red>Without Payment:</> $withoutPayment");
        $this->line("<fg=green>Approved:</> $approved");
        $this->line("<fg=yellow>Pending:</> $pending");
        $this->line("<fg=red>Declined:</> $declined");

        $this->newLine();

        // Show details if requested
        if ($this->confirm('Show detailed view for an order?', false)) {
            $orderIds = $orders->map(fn($o) => "{$o->id} - {$o->order_number} ({$o->screening->movie->title})")->all();
            $selected = $this->choice('Select an order:', $orderIds);

            preg_match('/^(\d+)/', $selected, $matches);
            $orderId = $matches[1] ?? null;

            if ($orderId) {
                $order = $orders->find($orderId);

                $this->newLine();
                $this->info("=== Order Details: #{$order->id} ===");
                $this->line("Order Number: {$order->order_number}");
                $this->line("Status: {$order->status}");
                $this->line("Total Amount: \${$order->total_amount}");
                $this->line("Cinema: {$order->screening->cinema->name}");
                $this->line("Movie: {$order->screening->movie->title}");
                $this->line("Date: {$order->created_at->format('Y-m-d H:i:s')}");
                $this->line("Updated: {$order->updated_at->format('Y-m-d H:i:s')}");

                if ($order->user) {
                    $this->line("User: {$order->user->name} ({$order->user->email})");
                }

                $this->newLine();
                $this->info("=== Tickets ===");
                $ticketCount = $order->tickets->count();
                $this->line("Total Tickets: $ticketCount");

                foreach ($order->tickets as $ticket) {
                    $seatInfo = $ticket->inventory ? "{$ticket->inventory->seat_number} ({$ticket->inventory->row})" : 'N/A';
                    $this->line("  • Ticket #{$ticket->id}: Seat {$seatInfo} - {$ticket->status}");
                }

                $this->newLine();
                $this->info("=== Payment ===");

                if ($order->paymentTickets->isEmpty()) {
                    $this->warn('No payment ticket found!');
                } else {
                    foreach ($order->paymentTickets as $payment) {
                        $this->line("Payment ID: {$payment->id}");
                        $this->line("Provider: {$payment->paymentProvider->name}");
                        $this->line("Status: {$payment->status}");
                        $this->line("Amount: \${$payment->amount}");
                        $this->line("Transaction ID: {$payment->transaction_id}");
                        $this->line("Reference: {$payment->reference_number}");
                        $this->line("Created: {$payment->created_at->format('Y-m-d H:i:s')}");

                        if ($payment->response_data) {
                            $this->newLine();
                            $this->line("Response Data:");
                            $this->line(json_encode($payment->response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        }
                    }
                }
            }
        }

        return 0;
    }
}
