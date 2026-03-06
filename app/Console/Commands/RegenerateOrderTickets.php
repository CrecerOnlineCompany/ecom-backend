<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ScreeningSeat;
use App\Models\Ticket;
use App\Services\OrderFinalizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Comando para regenerar tickets de órdenes que tienen reservas pero sin tickets
 * 
 * Casos de uso:
 * - Órdenes finalizadas sin tickets (error en FinalizeOrderPaymentAction)
 * - Recuperación después de fallos en el sistema
 * - Sincronización después de cambios de flujo
 */
class RegenerateOrderTickets extends Command
{
    protected $signature = 'orders:regenerate-tickets {--order-id=} {--screening-id=} {--dry-run} {--force : Ejecutar sin confirmación interactiva}';
    protected $description = 'Regenerar tickets para órdenes sin tickets pero con asientos reservados';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $orderId = $this->option('order-id');
        $screeningId = $this->option('screening-id');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN - No se realizarán cambios');
        }

        $this->info('🔍 Buscando órdenes sin tickets pero con reservas...');

        // Build query
        $query = Order::query()
            ->whereDoesntHave('tickets')  // Sin tickets
            ->whereHas('screeningSeats', function ($q) {
                $q->where('status', 'reserved');
            });  // Con reservas

        if ($orderId) {
            $query->where('id', $orderId);
            $this->info("Filtrando por order_id: {$orderId}");
        }

        if ($screeningId) {
            $query->where('screening_id', $screeningId);
            $this->info("Filtrando por screening_id: {$screeningId}");
        }

        $ordersToProcess = $query->with('screeningSeats')->get();

        if ($ordersToProcess->isEmpty()) {
            $this->info('✓ No hay órdenes que procesar');
            return self::SUCCESS;
        }

        $this->info("📦 Encontradas " . $ordersToProcess->count() . " órdenes para procesar\n");

        $this->table(['Order ID', 'Order#', 'Screening', 'Reserved Seats', 'Status'], 
            $ordersToProcess->map(function ($order) {
                $reservedSeats = $order->screeningSeats->where('status', 'reserved')->count();
                return [
                    $order->id,
                    $order->order_number,
                    $order->screening_id,
                    $reservedSeats,
                    $order->status,
                ];
            })->toArray()
        );

        if (!$force) {
            if (!$this->input->isInteractive()) {
                $this->error('Ejecución no interactiva detectada. Re-ejecuta con --force.');
                return self::FAILURE;
            }

            if (!$this->confirm('¿Proceder con la regeneración de tickets?')) {
                $this->info('Operación cancelada');
                return self::SUCCESS;
            }
        }

        $finalizationService = app(OrderFinalizationService::class);
        $processed = 0;
        $failed = 0;

        foreach ($ordersToProcess as $order) {
            try {
                $this->line("\n[{$processed}/{$ordersToProcess->count()}] Procesando Orden #{$order->order_number}...");
                
                $reservedCount = $order->screeningSeats->where('status', 'reserved')->count();
                $this->line("  Asientos reservados: {$reservedCount}");

                if ($dryRun) {
                    $this->info("  [DRY RUN] Sería procesada");
                    $processed++;
                    continue;
                }

                // REGENERATE: Create tickets and finalize
                $result = $finalizationService->finalizeOrderAfterApproval($order->id, [
                    'regeneration_reason' => 'recovery_from_incomplete_finalization',
                    'regenerated_at' => now()->toIso8601String(),
                ]);

                if ($result['success']) {
                    $this->info("  ✓ Finalizados {$result['finalized_tickets']} tickets");
                    
                    // Verify tickets were created
                    $ticketCount = Ticket::where('order_id', $order->id)->count();
                    $this->info("  ✓ Total de tickets en BD: {$ticketCount}");

                    // Verify reservations were cleaned
                    $reservedSeatsAfter = ScreeningSeat::where('order_id', $order->id)
                        ->where('status', 'reserved')
                        ->count();
                    $soldSeatsAfter = ScreeningSeat::where('order_id', $order->id)
                        ->where('status', 'sold')
                        ->count();
                    
                    $this->info("  ✓ Reservas posteriores: {$reservedSeatsAfter}");
                    $this->info("  ✓ Asientos vendidos: {$soldSeatsAfter}");

                    $processed++;
                } else {
                    $this->error("  ❌ Error: {$result['message']}");
                    $failed++;
                }

            } catch (\Exception $e) {
                $this->error("  ❌ Exception: {$e->getMessage()}");
                Log::error('RegenerateOrderTickets: Exception', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->info("\n" . str_repeat('=', 50));
        $this->info("✓ Procesadas: {$processed}/{$ordersToProcess->count()}");
        $this->error("✗ Fallos: {$failed}");
        $this->info(str_repeat('=', 50));

        if ($failed === 0 && !$dryRun) {
            $this->info("\n🎉 ¡Regeneración completada exitosamente!");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
