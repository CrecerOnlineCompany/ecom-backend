<?php

namespace App\Console\Commands;

use App\Models\Screening;
use App\Services\SeatInventoryService;
use Illuminate\Console\Command;

class EnsureActiveScreeningSeats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'screening-seats:ensure-active {--days=30}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure screening_seats records exist for active/future screenings (default: next 30 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int)$this->option('days');
        $service = app(SeatInventoryService::class);

        $this->info("🔍 Searching for screenings from today to +{$days} days...");

        // Screenings from now until now + days
        $from = now()->startOfDay();
        $to = now()->addDays($days)->endOfDay();

        $screenings = Screening::whereBetween('start_time', [$from, $to])
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        if ($screenings->isEmpty()) {
            $this->warn("⚠️  No active screenings found in the next {$days} days.");
            return Command::SUCCESS;
        }

        $this->info("📺 Found {$screenings->count()} screenings\n");

        $total_created = 0;
        $total_existing = 0;
        $errors = [];

        foreach ($screenings as $screening) {
            try {
                $movie = $screening->movie;
                $room = $screening->room;

                $this->line(
                    "[{$screening->start_time->format('Y-m-d H:i')}] " .
                    "{$movie->title} @ {$room->name}"
                );

                $result = $service->ensureScreeningSeats($screening->id);

                $this->line(
                    "  ✓ Created: {$result['created']}, Existing: {$result['existing']}"
                );

                $total_created += $result['created'];
                $total_existing += $result['existing'];
            } catch (\Exception $e) {
                $errors[] = "Screening #{$screening->id}: {$e->getMessage()}";
                $this->error("  ✗ Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("═══════════════════════════════════════");
        $this->info("📊 SUMMARY:");
        $this->info("  Screenings processed: {$screenings->count()}");
        $this->info("  Seats created: {$total_created}");
        $this->info("  Seats existing: {$total_existing}");

        if (!empty($errors)) {
            $this->newLine();
            $this->error("Errors found (" . count($errors) . "):");
            foreach ($errors as $error) {
                $this->warn("  - {$error}");
            }
        }

        $this->info("═══════════════════════════════════════");

        return Command::SUCCESS;
    }
}
