<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanupIncompletePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:cleanup {--hours=2 : Delete tickets older than X hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina tickets pendientes de pago que no fueron completados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $threshold = Carbon::now()->subHours($hours);

        $this->line("🧹 Limpiando tickets incompletos más antiguos de {$hours} horas...");
        
        try {
            // Obtener tickets pendientes antiguos
            $oldTickets = Ticket::whereIn('status', ['pending_payment', 'processing', 'payment_failed'])
                ->where('created_at', '<', $threshold)
                ->get();

            if ($oldTickets->isEmpty()) {
                $this->info('✅ No hay tickets incompletos para limpiar.');
                return Command::SUCCESS;
            }

            $count = $oldTickets->count();
            $this->warn("⚠️  Se van a eliminar {$count} tickets incompletos");

            if (!$this->confirm('¿Continuar con la eliminación?')) {
                return Command::SUCCESS;
            }

            // Eliminar tickets
            foreach ($oldTickets as $ticket) {
                $this->line("  Eliminando: {$ticket->ticket_number} (ID: {$ticket->id}, Asiento: {$ticket->seat_id}, Screening: {$ticket->screening_id})");
                $ticket->delete();
            }

            Log::info("Cleanup completed: deleted {$count} old pending payment tickets");
            $this->info("✅ Se eliminaron {$count} tickets incompletos.");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            Log::error("Cleanup error: " . $e->getMessage());
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
