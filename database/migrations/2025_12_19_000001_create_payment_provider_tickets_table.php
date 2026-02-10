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
        Schema::create('payment_provider_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('payment_provider_id')->constrained('payment_providers')->onDelete('cascade');
            
            // Estado del pago
            $table->enum('status', ['pending', 'processing', 'approved', 'declined', 'refunded'])->default('pending');
            
            // Información de transacción
            $table->string('transaction_id')->nullable()->unique();
            $table->string('reference_number')->nullable();
            $table->text('response_data')->nullable(); // JSON con respuesta del provider
            
            // Timestamps
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            
            $table->unique(['ticket_id', 'payment_provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_provider_tickets');
    }
};
