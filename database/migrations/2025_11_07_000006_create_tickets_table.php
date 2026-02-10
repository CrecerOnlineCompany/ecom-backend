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
            $table->foreignId('seat_id')->nullable()->constrained('seats')->onDelete('set null')->comment('Legacy: ahora usamos ticket_details');
            $table->string('ticket_number')->unique();
            $table->decimal('price', 8, 2);
            $table->string('status')->default('confirmed'); // confirmed, pending_payment, cancelled
            $table->string('qr_code')->nullable();
            $table->dateTime('used_at')->nullable();
            
            // Información desnormalizada del cliente
            $table->string('customer_name')->nullable()->comment('Nombre del cliente');
            $table->string('customer_email')->nullable()->comment('Email del cliente');
            $table->string('customer_phone')->nullable()->comment('Teléfono del cliente');

            // Información desnormalizada de la función
            $table->string('movie_title')->nullable()->comment('Título de la película');
            $table->string('room_name')->nullable()->comment('Nombre de la sala');
            $table->string('cinema_name')->nullable()->comment('Nombre del cine');
            $table->dateTime('screening_start_time')->nullable()->comment('Hora de inicio de la función');
            $table->string('screening_format')->nullable()->comment('Formato de la función (2D, 3D, etc)');

            // Metadatos para estadísticas
            $table->string('payment_method')->nullable()->comment('Método de pago utilizado');
            $table->decimal('original_price', 8, 2)->nullable()->comment('Precio original sin descuentos');
            $table->decimal('discount_amount', 8, 2)->default(0)->comment('Monto de descuento aplicado');
            $table->string('discount_code')->nullable()->comment('Código de descuento usado');
            $table->dateTime('purchased_at')->nullable()->comment('Fecha y hora de compra');
            $table->string('purchase_device')->nullable()->comment('Dispositivo desde donde se compró (web, mobile, etc)');
            $table->string('ip_address')->nullable()->comment('IP desde donde se realizó la compra');
            
            $table->timestamps();
            
            // Índices para mejor rendimiento en estadísticas
            $table->index(['user_id', 'status']);
            $table->index('movie_title');
            $table->index('cinema_name');
            $table->index('screening_start_time');
            $table->index('purchased_at');
            $table->index('payment_method');
            $table->index('status');
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
