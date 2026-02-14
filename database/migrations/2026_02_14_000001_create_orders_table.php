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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->comment('UUID único del orden internamente');
            $table->string('order_number')->unique()->comment('Número de orden legible: ORD-YYYYMMDD-XXXX');
            
            // Información del cliente
            $table->string('customer_name')->comment('Nombre del cliente');
            $table->string('customer_email')->comment('Email del cliente');
            $table->string('customer_phone')->nullable()->comment('Teléfono del cliente');
            
            // Referencias
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario asociado (nullable para compras anónimas)');
            $table->foreignId('screening_id')->constrained('screenings')->onDelete('cascade')->comment('Función/película comprada');
            
            // Información de la compra
            $table->decimal('total_amount', 10, 2)->comment('Monto total de la orden');
            $table->string('currency')->default('ARS')->comment('Moneda (ARS, USD, etc)');
            $table->string('status')->default('draft')->comment('Estado: draft, reserved, payment_processing, paid, cancelled, expired, payment_failed');
            
            // Metadatos
            $table->string('purchase_device')->nullable()->comment('Dispositivo (web, mobile)');
            $table->string('ip_address')->nullable()->comment('IP de la compra');
            $table->dateTime('reserved_until')->nullable()->comment('Fecha de expiración de la reserva');
            $table->dateTime('paid_at')->nullable()->comment('Fecha de pago confirmado');
            $table->dateTime('cancelled_at')->nullable()->comment('Fecha de cancelación');
            
            // Auditoría
            $table->timestamps();
            $table->softDeletes(); // Para mantener historial sin eliminar
            
            // Índices
            $table->index(['user_id', 'status']);
            $table->index(['screening_id', 'status']);
            $table->index('status');
            $table->index('order_number'); // Para búsquedas rápidas por número
            $table->index('created_at');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
