<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Ticket;
use App\Services\OrderNumberGenerator;
use Illuminate\Database\Seeder;

class OrdersTestSeeder extends Seeder
{
    /**
     * Seed the application's database con órdenes de prueba.
     */
    public function run(): void
    {
        // Obtener un ticket existente para vincularlo
        $ticket = Ticket::first();

        if (!$ticket) {
            $this->command->warn('⚠️  No hay tickets en la base de datos.');
            return;
        }

        $this->command->info('📝 Creando órdenes de prueba...');

        // Crear una orden de prueba
        $order = Order::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'order_number' => OrderNumberGenerator::generate(),
            'customer_name' => 'Test Customer ' . now()->timestamp,
            'customer_email' => 'test_' . now()->timestamp . '@example.com',
            'customer_phone' => '1123456789',
            'user_id' => $ticket->user_id,
            'screening_id' => $ticket->screening_id,
            'total_amount' => 500.00,
            'currency' => 'ARS',
            'status' => Order::STATUS_PAID,
            'purchase_device' => 'web',
            'ip_address' => '127.0.0.1',
            'paid_at' => now(),
        ]);

        $this->command->info("✅ Orden creada: {$order->order_number}");

        // Vincular el ticket a la orden
        $ticket->update(['order_id' => $order->id]);

        $this->command->info("✅ Ticket #{$ticket->id} vinculado a Order #{$order->id}");
        $this->command->newLine();
        $this->command->info("📊 Detalles de la orden:");
        $this->command->table(
            ['Campo', 'Valor'],
            [
                ['Order Number', $order->order_number],
                ['Status', $order->status],
                ['Customer', $order->customer_name],
                ['Email', $order->customer_email],
                ['Total', '$' . $order->total_amount],
                ['Screening ID', $order->screening_id],
                ['Created At', $order->created_at],
            ]
        );
    }
}
