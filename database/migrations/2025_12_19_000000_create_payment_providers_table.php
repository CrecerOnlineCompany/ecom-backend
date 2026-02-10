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
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // 'mercado_pago', 'paypal', 'cash'
            $table->string('display_name'); // 'Mercado Pago', 'PayPal', 'Pago en Efectivo'
            $table->text('description')->nullable();
            $table->string('icon_url')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Configuración del provider
            $table->json('config')->nullable(); // API keys, secrets, etc.
            
            // URLs de redirección y webhook
            $table->string('redirect_url')->nullable(); // URL post-pago (si aplica)
            $table->string('webhook_path')->nullable(); // /webhooks/payment/{hash}
            $table->string('webhook_secret')->nullable(); // Hash único para validación
            
            // Flags de funcionalidad
            $table->boolean('requires_redirect')->default(false); // ¿Requiere redirección?
            $table->boolean('supports_webhook')->default(true); // ¿Soporta webhooks?
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_providers');
    }
};
