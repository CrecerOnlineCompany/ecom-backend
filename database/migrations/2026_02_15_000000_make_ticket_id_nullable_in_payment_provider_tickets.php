<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ORDER-FIRST: ticket_id debe ser nullable porque:
     * - En order-first, creamos PaymentProviderTicket sin tickets aún
     * - Los tickets se crean en el webhook cuando el pago es confirmado
     * - Por eso solo tenemos order_id, no ticket_id
     */
    public function up(): void
    {
        Schema::table('payment_provider_tickets', function (Blueprint $table) {
            // Primero dropear la foreign key y unique constraint si existen
            try {
                $table->dropForeign(['ticket_id']);
            } catch (\Exception $e) {
                \Log::warning('Foreign key already dropped: ' . $e->getMessage());
            }
            
            try {
                $table->dropUnique(['ticket_id', 'payment_provider_id']);
            } catch (\Exception $e) {
                \Log::warning('Unique constraint already dropped: ' . $e->getMessage());
            }
        });
        
        // Ahora modificar la columna para hacerla nullable
        DB::statement('ALTER TABLE `payment_provider_tickets` MODIFY COLUMN `ticket_id` BIGINT UNSIGNED NULL');
        
        // Recrear la foreign key como nullable
        Schema::table('payment_provider_tickets', function (Blueprint $table) {
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_provider_tickets', function (Blueprint $table) {
            try {
                $table->dropForeign(['ticket_id']);
            } catch (\Exception $e) {
                \Log::warning('Foreign key not found: ' . $e->getMessage());
            }
        });
        
        DB::statement('ALTER TABLE `payment_provider_tickets` MODIFY COLUMN `ticket_id` BIGINT UNSIGNED NOT NULL');
        
        Schema::table('payment_provider_tickets', function (Blueprint $table) {
            $table->foreign('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->unique(['ticket_id', 'payment_provider_id']);
        });
    }
};
