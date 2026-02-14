<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add ticket_sequence and payment_data fields for order finalization
     * 
     * ticket_sequence: Deterministic ticket number sequence within order (1, 2, 3...)
     * payment_data: JSON field to store transaction_id, provider_id, approval details
     */
    public function up(): void
    {
        // 1. Add ticket_sequence to tickets table
        Schema::table('tickets', function (Blueprint $table) {
            // Nullable initially to backfill existing tickets
            $table->unsignedInteger('ticket_sequence')->nullable()->after('ticket_number');
            // Unique constraint on (order_id, ticket_sequence) to prevent duplicate sequences
            $table->unique(['order_id', 'ticket_sequence']);
            // Index for quick lookup
            $table->index('ticket_sequence');
        });

        // 2. Add payment_data and completed_at to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->json('payment_data')->nullable()->after('ip_address');
            $table->datetime('completed_at')->nullable()->after('paid_at');
            // Add 'completed' status constant if not already there
            // Note: migrations can't create constants, just ensure schema
        });

        // 3. Add uniqueness constraint to screening_seats (screening_id, seat_id)
        // This should already exist from migration 2026_02_14_000003
        // Just ensuring no duplicate seat reservations per screening
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'ticket_sequence']);
            $table->dropIndex(['ticket_sequence']);
            $table->dropColumn('ticket_sequence');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_data', 'completed_at']);
        });
    }
};
