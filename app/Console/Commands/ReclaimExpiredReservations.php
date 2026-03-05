<?php

namespace App\Console\Commands;

use App\Models\ScreeningSeat;
use App\Services\SeatInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReclaimExpiredReservations extends Command
{
    protected $signature = 'seats:reclaim-expired-reservations {--screening-id= : Reclaim only one screening} {--dry-run : Show how many rows would be reclaimed}';
    protected $description = 'Reclaim expired seat reservations and set them back to available';

    public function handle(SeatInventoryService $inventoryService): int
    {
        $screeningId = $this->option('screening-id');
        $dryRun = (bool) $this->option('dry-run');

        if ($screeningId !== null && !is_numeric($screeningId)) {
            $this->error('The --screening-id option must be numeric.');
            return self::FAILURE;
        }

        $screeningId = $screeningId !== null ? (int) $screeningId : null;

        if ($dryRun) {
            $query = ScreeningSeat::expiredReservations();
            if ($screeningId !== null) {
                $query->where('screening_id', $screeningId);
            }

            $count = $query->count();

            $this->info("Dry run: {$count} expired reservation(s) would be reclaimed.");
            return self::SUCCESS;
        }

        try {
            $reclaimed = $inventoryService->reclaimExpiredReservations($screeningId);

            $this->info("Reclaimed {$reclaimed} expired reservation(s).");

            Log::info('ReclaimExpiredReservations command executed', [
                'screening_id' => $screeningId,
                'reclaimed_count' => $reclaimed,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to reclaim expired reservations: ' . $e->getMessage());

            Log::error('ReclaimExpiredReservations command failed', [
                'screening_id' => $screeningId,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
