<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection()->getDriverName();

        if ($connection === 'mysql') {
            // Para MySQL, usar DB::statement para ALTER TABLE con ENUM modificado
            DB::statement("ALTER TABLE `payment_provider_tickets` MODIFY COLUMN `status` ENUM('pending','queued','processing','approved','declined','refunded','expired','cancelled') DEFAULT 'pending'");
        } else {
            // Para otras bases de datos (ejemplo: PostgreSQL), adaptar si es necesario
            Schema::table('payment_provider_tickets', function (Blueprint $table) {
                // PostgreSQL no soporta ENUM como MySQL, usar check constraint
                $table->string('status')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection()->getDriverName();

        if ($connection === 'mysql') {
            // Revertir al ENUM original
            DB::statement("ALTER TABLE `payment_provider_tickets` MODIFY COLUMN `status` ENUM('pending','processing','approved','declined','refunded') DEFAULT 'pending'");
        } else {
            // Para PostgreSQL, revertir a string
            Schema::table('payment_provider_tickets', function (Blueprint $table) {
                $table->string('status')->change();
            });
        }
    }
};
