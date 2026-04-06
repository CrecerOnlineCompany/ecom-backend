<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_details', function (Blueprint $table) {
            $table->boolean('room_non_number')
                ->default(false)
                ->after('seat_number')
                ->comment('Snapshot del flag non_number de la sala al momento de emitir');

            $table->index('room_non_number');
        });

        // Backfill para detalles existentes usando screening -> room.
        DB::table('ticket_details as td')
            ->join('screenings as s', 's.id', '=', 'td.screening_id')
            ->join('rooms as r', 'r.id', '=', 's.room_id')
            ->select('td.id', 'r.non_number')
            ->orderBy('td.id')
            ->chunk(1000, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('ticket_details')
                        ->where('id', $row->id)
                        ->update([
                            'room_non_number' => (bool) ($row->non_number ?? false),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_details', function (Blueprint $table) {
            $table->dropIndex(['room_non_number']);
            $table->dropColumn('room_non_number');
        });
    }
};
