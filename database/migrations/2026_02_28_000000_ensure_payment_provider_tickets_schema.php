<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * SIMPLE: Solo se asegurar que payment_provider_tickets tenga order_id
     * (ya agregado en migración 2026_02_14_000004)
     * 
     * Si ya existe order_id, esta migración no hace nada (idempotente)
     */
    public function up(): void
    {
        Schema::table('payment_provider_tickets', function (Blueprint $table) {
            // Solo si NO existe el campo order_id, agregarlo
            // Esto es para asegurar compatibilidad con datos existentes
            if (!Schema::hasColumn('payment_provider_tickets', 'order_id')) {
                $table->foreignId('order_id')
                    ->nullable()
                    ->after('ticket_id')
                    ->constrained('orders')
                    ->onDelete('cascade')
                    ->comment('Order associated with this payment (for order-first flow)');
                
                $table->index('order_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_provider_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('payment_provider_tickets', 'order_id')) {
                $table->dropForeignKey(['order_id']);
                $table->dropIndex(['order_id']);
                $table->dropColumn('order_id');
            }
        });
    }
};
