<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Ticket;
use App\Services\OrderNumberGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOrdersFromTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:backfill-from-tickets {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crear órdenes a partir de tickets existentes y linkear tickets.order_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dry_run = $this->option('dry-run');

        if ($dry_run) {
            $this->info('⚠️  DRY RUN: No se realizarán cambios en la BD');
        } else {
            if (!$this->confirm('¿Crear órdenes desde tickets? Este cambio es irreversible. ¿Continuar?')) {
                $this->info('Operación cancelada.');
                return Command::SUCCESS;
            }
        }

        // Obtener todos los tickets existentes que no tengan order_id
        $tickets = Ticket::whereNull('order_id')
            ->with(['screening', 'user'])
            ->orderBy('id')
            ->get();

        if ($tickets->isEmpty()) {
            $this->info('❌ No hay tickets sin order_id para procesar.');
            return Command::SUCCESS;
        }

        $this->info("📋 Encontrados {$tickets->count()} tickets sin order_id");
        $this->newLine();

        $created_orders = 0;
        $linked_tickets = 0;
        $errors = [];

        foreach ($tickets as $ticket) {
            try {
                // Validar datos necesarios
                if (!$ticket->screening_id) {
                    $errors[] = "Ticket ID {$ticket->id}: falta screening_id";
                    continue;
                }

                // Comprobar si ya existe una orden para este ticket
                $existing_order = Order::where('screening_id', $ticket->screening_id)
                    ->where('customer_email', $ticket->customer_email)
                    ->where('created_at', '>=', $ticket->created_at->subHours(24))
                    ->first();

                if (!$dry_run) {
                    if ($existing_order) {
                        // Usar orden existente
                        $order = $existing_order;
                    } else {
                        // Crear nueva orden
                        $order = Order::create([
                            'uuid' => \Illuminate\Support\Str::uuid(),
                            'order_number' => OrderNumberGenerator::generate(),
                            'customer_name' => $ticket->customer_name ?? 'Unknown',
                            'customer_email' => $ticket->customer_email ?? 'unknown@example.com',
                            'customer_phone' => $ticket->customer_phone,
                            'user_id' => $ticket->user_id,
                            'screening_id' => $ticket->screening_id,
                            'total_amount' => $ticket->price,
                            'currency' => 'ARS',
                            'status' => Order::STATUS_PAID,
                            'purchase_device' => $ticket->purchase_device,
                            'ip_address' => $ticket->ip_address,
                            'paid_at' => $ticket->purchased_at ?? now(),
                        ]);

                        $created_orders++;
                    }

                    // Linkar el ticket a la orden
                    $ticket->update(['order_id' => $order->id]);
                    $linked_tickets++;

                    $this->line("✓ Ticket #{$ticket->id} → Order {$order->order_number}");
                } else {
                    // Dry run: solo mostrar qué se haría
                    $order_number = OrderNumberGenerator::generate();
                    $this->line("✓ [DRY] Ticket #{$ticket->id} → Order {$order_number}");
                    $created_orders++;
                    $linked_tickets++;
                }
            } catch (\Exception $e) {
                $errors[] = "Ticket ID {$ticket->id}: {$e->getMessage()}";
                $this->error("✗ Ticket #{$ticket->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("═══════════════════════════════════");
        $this->info("📊 RESUMEN:");
        $this->info("  Órdenes creadas: {$created_orders}");
        $this->info("  Tickets vinculados: {$linked_tickets}");

        if (!empty($errors)) {
            $this->error("  Errores encontrados: " . count($errors));
            $this->newLine();
            $this->info("Errores:");
            foreach ($errors as $error) {
                $this->warn("  - {$error}");
            }
        }

        if ($dry_run) {
            $this->newLine();
            $this->info('💾 Para ejecutar sin DRY RUN, usa:');
            $this->info('   php artisan orders:backfill-from-tickets');
        }

        $this->info("═══════════════════════════════════");

        return Command::SUCCESS;
    }
}
