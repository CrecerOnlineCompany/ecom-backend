<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_content_templates')) {
            return;
        }

        Schema::create('cms_content_templates', function (Blueprint $table) {
            $table->id();
            $table->string('site', 64)->nullable()->index();
            $table->string('type', 60)->default('product_landing')->index();
            $table->string('name', 160);
            $table->string('handle', 160);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('template_json');
            $table->string('preview_image_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['site', 'type', 'handle'], 'cms_templates_site_type_handle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_content_templates');
    }
};
