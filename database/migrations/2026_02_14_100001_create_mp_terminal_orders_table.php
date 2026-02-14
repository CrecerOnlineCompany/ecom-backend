<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla para tracking de órdenes Point activas por terminal
     * Permite detectar órdenes colgadas aunque no estén en payment_provider_tickets
     */
    public function up(): void
    {
        Schema::create('mp_terminal_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_provider_id')->constrained('payment_providers')->onDelete('cascade');
            $table->foreignId('payment_provider_ticket_id')->nullable()->constrained('payment_provider_tickets')->nullOnDelete();
            
            // Identificadores de orden
            $table->string('terminal_id')->index(); // PAX_A910__SMARTPOS...
            $table->string('order_id')->unique(); // ID de orden en MP
            $table->string('external_reference')->nullable(); // CINEA-POINT-xxx
            
            // Estado de la orden
            $table->enum('status', ['active', 'pending', 'processing', 'approved', 'declined', 'cancelled', 'expired', 'unknown'])->default('active')->index();
            
            // Metadata y historial
            $table->json('response_data')->nullable(); // Último estado conocido
            $table->string('cancel_reason')->nullable(); // 'user_changed_method', 'auto_cancel', 'next_order_retry'
            
            // Timestamps
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Índices para búsquedas frecuentes
            $table->index(['payment_provider_id', 'terminal_id', 'created_at'], 'mp_to_pp_term_created_idx');
            $table->index(['terminal_id', 'status'], 'mp_to_term_status_idx');
        });
    
    }

    /**
     * Reverse
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_terminal_orders');
    }
};
