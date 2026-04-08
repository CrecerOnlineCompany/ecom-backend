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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->nullable()->unique()->comment('Código promocional opcional');
            $table->string('name');
            $table->string('type', 60)->comment('percentage, fixed_amount, bxgy');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_automatic')->default(false)->comment('Se aplica sin código');
            $table->boolean('is_stackable')->default(false)->comment('Permite combinar con otras promos');
            $table->integer('priority')->default(100)->comment('Menor número = mayor prioridad');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->json('settings')->nullable()->comment('Parámetros por tipo de promoción');
            $table->timestamps();

            $table->index(['is_active', 'is_automatic']);
            $table->index(['type', 'priority']);
            $table->index('starts_at');
            $table->index('ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
