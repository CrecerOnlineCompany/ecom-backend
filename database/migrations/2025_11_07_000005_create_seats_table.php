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
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->integer('row_number');
            $table->integer('seat_number');
            $table->string('seat_code'); // Ej: A1, A2, B1, etc
            $table->string('type')->default('standard'); // standard, vip, accessible
            $table->decimal('price_modifier', 3, 2)->default(1.0); // Multiplicador de precio
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['room_id', 'row_number', 'seat_number']);
            $table->unique(['room_id', 'seat_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
