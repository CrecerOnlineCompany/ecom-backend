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
        Schema::create('screening_seats', function (Blueprint $table) {
            $table->id();
            
            // Composite unique key: (screening_id, seat_id)
            $table->foreignId('screening_id')->constrained('screenings')->onDelete('cascade');
            $table->foreignId('seat_id')->constrained('seats')->onDelete('cascade');
            $table->unique(['screening_id', 'seat_id']);
            
            // Inventario state
            $table->string('status')->default('available')->comment('available, reserved, sold');
            
            // Reservation logic
            $table->dateTime('reserved_until')->nullable()->comment('Expiración de reserva');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('cascade');
            
            // Holder identification (flexible: terminal, session, user, etc)
            $table->string('reserved_by_type')->nullable()->comment('Tipo de holder: terminal, session, user, etc');
            $table->string('reserved_by_id')->nullable()->comment('ID del holder (user_id, session_id, etc)');
            
            // Audit
            $table->timestamps();
            $table->dateTime('sold_at')->nullable()->comment('Cuándo se marcó como sold');
            
            // Índices para queries frecuentes
            $table->index(['screening_id', 'status']);
            $table->index(['screening_id', 'reserved_until']);
            $table->index('order_id');
            $table->index(['reserved_by_type', 'reserved_by_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screening_seats');
    }
};
