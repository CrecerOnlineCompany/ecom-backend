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
        Schema::table('movies', function (Blueprint $table) {
            $table->json('languages')->nullable()->after('language');
        });

        DB::table('movies')
            ->select(['id', 'language'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $legacy = is_string($row->language) ? strtolower(trim($row->language)) : '';
                    $mapped = match ($legacy) {
                        'es', 'espanol', 'español' => 'espanol',
                        'castellano' => 'castellano',
                        'subtitulado' => 'subtitulado',
                        default => 'espanol',
                    };

                    DB::table('movies')
                        ->where('id', $row->id)
                        ->update([
                            'languages' => json_encode([$mapped]),
                            'language' => $mapped,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('languages');
        });
    }
};
