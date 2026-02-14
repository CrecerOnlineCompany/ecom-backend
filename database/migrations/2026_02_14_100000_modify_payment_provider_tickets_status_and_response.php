<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Modifica ENUM de status y convierte response_data a JSON
     */
    public function up(): void
    {
        $connection = DB::connection()->getDriverName();

        if ($connection === 'mysql') {
            // 1. Expandir ENUM status
            DB::statement(
                "ALTER TABLE `payment_provider_tickets` 
                MODIFY COLUMN `status` ENUM(
                    'pending','queued','processing','approved','declined','refunded','expired','cancelled','failed'
                ) DEFAULT 'pending'"
            );

            // 2. Crear columna response_data_json temporal si no existe
            if (!Schema::hasColumn('payment_provider_tickets', 'response_data_json')) {
                Schema::table('payment_provider_tickets', function (Blueprint $table) {
                    $table->json('response_data_json')->nullable()->after('response_data');
                });
            }

            // 3. Migrar datos de TEXT a JSON
            DB::statement(
                "UPDATE `payment_provider_tickets` 
                SET `response_data_json` = 
                    CASE 
                        WHEN `response_data` IS NOT NULL AND `response_data` != '' THEN CAST(`response_data` AS JSON)
                        ELSE NULL
                    END
                WHERE `response_data_json` IS NULL"
            );

            // 4. Borrar columna response_data antigua
            Schema::table('payment_provider_tickets', function (Blueprint $table) {
                $table->dropColumn('response_data');
            });

            // 5. Renombrar response_data_json a response_data
            DB::statement(
                "ALTER TABLE `payment_provider_tickets` 
                CHANGE COLUMN `response_data_json` `response_data` JSON NULL"
            );
        } else {
            // Para otros drivers (PostgreSQL, SQLite, etc), adaptación más simple
            Schema::table('payment_provider_tickets', function (Blueprint $table) {
                // PostgreSQL maneja JSON nativamente
                if ($connection !== 'pgsql') {
                    $table->string('status')->change();
                }
            });
        }
    }

    /**
     * Reverse
     */
    public function down(): void
    {
        $connection = DB::connection()->getDriverName();

        if ($connection === 'mysql') {
            // Crear TEXT semanal con datos JSON convertidos
            Schema::table('payment_provider_tickets', function (Blueprint $table) {
                $table->text('response_data_text')->nullable()->after('response_data');
            });

            DB::statement(
                "UPDATE `payment_provider_tickets` 
                SET `response_data_text` = CAST(`response_data` AS CHAR)
                WHERE `response_data` IS NOT NULL"
            );

            Schema::table('payment_provider_tickets', function (Blueprint $table) {
                $table->dropColumn('response_data');
            });

            DB::statement(
                "ALTER TABLE `payment_provider_tickets` 
                CHANGE COLUMN `response_data_text` `response_data` TEXT NULL"
            );

            // Revertir ENUM
            DB::statement(
                "ALTER TABLE `payment_provider_tickets` 
                MODIFY COLUMN `status` ENUM(
                    'pending','processing','approved','declined','refunded'
                ) DEFAULT 'pending'"
            );
        }
    }
};
