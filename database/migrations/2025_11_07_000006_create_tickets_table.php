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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screening_id')->constrained('screenings')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seat_id')->constrained('seats')->onDelete('restrict');
            $table->string('ticket_number')->unique();
            $table->decimal('price', 8, 2);
            $table->string('status')->default('confirmed'); // confirmed, cancelled
            $table->string('qr_code')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->timestamps();
            
            $table->unique(['screening_id', 'seat_id']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
