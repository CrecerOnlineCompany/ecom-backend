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
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->text('description')->nullable();
            $table->string('genre');
            $table->integer('duration')->comment('Duración en minutos');
            $table->string('rating')->nullable(); // G, PG, PG-13, R, NC-17
            $table->string('director')->nullable();
            $table->string('cast')->nullable();
            $table->string('language')->default('es');
            $table->text('poster_url')->nullable();
            $table->text('trailer_url')->nullable();
            $table->date('release_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
