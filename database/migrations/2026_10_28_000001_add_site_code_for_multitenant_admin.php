<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // users.siteid belongs to Aimeos (internal site id such as "1."), so the
        // store an admin manages is kept in its own column.
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'site_code')) {
                $table->string('site_code', 64)->default('default')->index();
            }
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->string('site_code', 64)->default('default');
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['site_code', 'key']);
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('site_code', 64)->default('default')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex(['site_code']);
            $table->dropColumn('site_code');
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropUnique(['site_code', 'key']);
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('site_code');
            $table->unique(['key']);
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'site_code')) {
                $table->dropIndex(['site_code']);
                $table->dropColumn('site_code');
            }
        });
    }
};
