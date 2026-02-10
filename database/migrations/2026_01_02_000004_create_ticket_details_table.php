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
        Schema::create('ticket_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade')->comment('Referencia al ticket padre');
            $table->foreignId('screening_id')->constrained('screenings')->onDelete('cascade')->comment('Función a la que pertenece el asiento');
            $table->foreignId('seat_id')->nullable()->constrained('seats')->onDelete('set null')->comment('Asiento reservado');
            
            // Información de asiento desnormalizada
            $table->string('seat_code')->comment('Código del asiento (ej: A1, B2)');
            $table->integer('row_number')->comment('Número de fila');
            $table->integer('seat_number')->comment('Número de asiento en la fila');
            
            // Información de la entrada individual
            $table->decimal('price', 8, 2)->comment('Precio de esta entrada');
            $table->string('status')->default('confirmed')->comment('Estado del asiento (confirmed, cancelled, used)');
            $table->string('qr_code')->nullable()->comment('Código QR individual para esta entrada');
            $table->dateTime('used_at')->nullable()->comment('Fecha y hora cuando se utilizó la entrada');
            
            $table->timestamps();
            
            // Índices
            $table->index('ticket_id');
            $table->index('screening_id');
            $table->index('seat_id');
            $table->index('status');
            $table->unique(['screening_id', 'seat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_details');
    }
};
