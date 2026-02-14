<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_provider_tickets', function (Blueprint $table) {
            // Agregar orden_id nullable después de ticket_id
            // FK a orders table (opcional para compatibilidad con tickets viejos)
            $table->foreignId('order_id')
                ->nullable()
                ->after('ticket_id')
                ->constrained('orders')
                ->onDelete('cascade')
                ->comment('Order associated with this payment (nullable for legacy compatibility)');
            
            // Índice para búsquedas por order_id
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_provider_tickets', function (Blueprint $table) {
            $table->dropForeignKey(['order_id']);
            $table->dropIndex(['order_id']);
            $table->dropColumn('order_id');
        });
    }
};
