<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('Admin')->after('superuser');
            }

            if (!Schema::hasColumn('users', 'status')) {
                $table->unsignedTinyInteger('status')->default(1)->after('role');
            }

            if (!Schema::hasColumn('users', 'siteid')) {
                $table->string('siteid')->default('default')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'siteid')) {
                $table->dropColumn('siteid');
            }

            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
