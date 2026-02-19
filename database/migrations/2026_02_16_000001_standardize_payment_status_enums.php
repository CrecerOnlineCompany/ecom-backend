<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Standarize ALL payment status enums to use unified PaymentStatus enum
 * 
 * This migration unifies the status enum across:
 * - orders.status
 * - tickets.status  
 * - payment_provider_tickets.status
 * 
 * MAPPING:
 * 
 * Orders:
 *   draft → pending
 *   reserved → pending
 *   payment_processing → processing
 *   paid → completed
 *   payment_failed → failed
 *   (cancelled, expired stay same)
 * 
 * Tickets:
 *   pending_payment → pending
 *   processing → processing
 *   confirmed → completed
 *   payment_failed → failed
 *   (cancelled, expired stay same)
 * 
 * PaymentProviderTickets:
 *   pending → pending
 *   queued → processing
 *   processing → processing
 *   approved → completed
 *   declined → failed
 *   failed → failed
 *   finalization_failed → failed
 *   (cancelled, expired, refunded stay same)
 */

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection()->getDriverName();

        if ($connection === 'mysql') {
            
            // ==================================================================================
            // 1. MIGRATE ORDERS.STATUS DATA
            // ==================================================================================
            
            DB::statement("
                UPDATE orders 
                SET status = 'pending'
                WHERE status IN ('draft', 'reserved')
            ");
            
            DB::statement("
                UPDATE orders
                SET status = 'processing'
                WHERE status = 'payment_processing'
            ");
            
            DB::statement("
                UPDATE orders
                SET status = 'completed'
                WHERE status = 'paid'
            ");
            
            DB::statement("
                UPDATE orders
                SET status = 'failed'
                WHERE status = 'payment_failed'
            ");
            
            // Change ENUM for orders.status
            DB::statement(
                "ALTER TABLE `orders` 
                MODIFY COLUMN `status` ENUM(
                    'pending','processing','completed','failed','cancelled','expired','refunded'
                ) DEFAULT 'pending'"
            );
            
            
            // ==================================================================================
            // 2. MIGRATE TICKETS.STATUS DATA
            // ==================================================================================
            
            DB::statement("
                UPDATE tickets
                SET status = 'pending'
                WHERE status IN ('pending_payment', 'processing')
            ");
            
            DB::statement("
                UPDATE tickets
                SET status = 'completed'
                WHERE status = 'confirmed'
            ");
            
            DB::statement("
                UPDATE tickets
                SET status = 'failed'
                WHERE status = 'payment_failed'
            ");
            
            // Change ENUM for tickets.status
            DB::statement(
                "ALTER TABLE `tickets` 
                MODIFY COLUMN `status` ENUM(
                    'pending','processing','completed','failed','cancelled','expired','refunded'
                ) DEFAULT 'pending'"
            );
            
            
            // ==================================================================================
            // 3. MIGRATE PAYMENT_PROVIDER_TICKETS.STATUS DATA
            // ==================================================================================
            
            // queued y processing → processing
            DB::statement("
                UPDATE payment_provider_tickets
                SET status = 'processing'
                WHERE status IN ('queued', 'processing')
            ");
            
            // approved → completed
            DB::statement("
                UPDATE payment_provider_tickets
                SET status = 'completed'
                WHERE status = 'approved'
            ");
            
            // declined, failed, finalization_failed → failed
            DB::statement("
                UPDATE payment_provider_tickets
                SET status = 'failed'
                WHERE status IN ('declined', 'failed', 'finalization_failed')
            ");
            
            // Change ENUM for payment_provider_tickets.status
            DB::statement(
                "ALTER TABLE `payment_provider_tickets` 
                MODIFY COLUMN `status` ENUM(
                    'pending','processing','completed','failed','cancelled','expired','refunded'
                ) DEFAULT 'pending'"
            );
            
        } elseif ($connection === 'sqlite') {
            // SQLite doesn't support ENUM, use TEXT instead
            // Just update data (schema won't change for SQLite)
            
            // Orders
            DB::statement("UPDATE orders SET status = 'pending' WHERE status IN ('draft', 'reserved')");
            DB::statement("UPDATE orders SET status = 'processing' WHERE status = 'payment_processing'");
            DB::statement("UPDATE orders SET status = 'completed' WHERE status = 'paid'");
            DB::statement("UPDATE orders SET status = 'failed' WHERE status = 'payment_failed'");
            
            // Tickets
            DB::statement("UPDATE tickets SET status = 'pending' WHERE status IN ('pending_payment', 'processing')");
            DB::statement("UPDATE tickets SET status = 'completed' WHERE status = 'confirmed'");
            DB::statement("UPDATE tickets SET status = 'failed' WHERE status = 'payment_failed'");
            
            // PaymentProviderTickets
            DB::statement("UPDATE payment_provider_tickets SET status = 'processing' WHERE status IN ('queued', 'processing')");
            DB::statement("UPDATE payment_provider_tickets SET status = 'completed' WHERE status = 'approved'");
            DB::statement("UPDATE payment_provider_tickets SET status = 'failed' WHERE status IN ('declined', 'failed', 'finalization_failed')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection()->getDriverName();

        if ($connection === 'mysql') {
            
            // Revertir orders.status
            DB::statement("UPDATE orders SET status = 'draft' WHERE status = 'pending'");
            DB::statement("UPDATE orders SET status = 'payment_processing' WHERE status = 'processing'");
            DB::statement("UPDATE orders SET status = 'paid' WHERE status = 'completed'");
            DB::statement("UPDATE orders SET status = 'payment_failed' WHERE status = 'failed'");
            
            DB::statement(
                "ALTER TABLE `orders` 
                MODIFY COLUMN `status` ENUM(
                    'draft','reserved','payment_processing','paid','cancelled','expired','payment_failed'
                ) DEFAULT 'draft'"
            );
            
            // Revertir tickets.status
            DB::statement("UPDATE tickets SET status = 'pending_payment' WHERE status = 'pending'");
            DB::statement("UPDATE tickets SET status = 'confirmed' WHERE status = 'completed'");
            DB::statement("UPDATE tickets SET status = 'payment_failed' WHERE status = 'failed'");
            
            DB::statement(
                "ALTER TABLE `tickets` 
                MODIFY COLUMN `status` ENUM(
                    'pending_payment','processing','confirmed','payment_failed','cancelled','expired'
                ) DEFAULT 'pending_payment'"
            );
            
            // Revertir payment_provider_tickets.status
            DB::statement("UPDATE payment_provider_tickets SET status = 'queued' WHERE status = 'processing'");
            DB::statement("UPDATE payment_provider_tickets SET status = 'approved' WHERE status = 'completed'");
            DB::statement("UPDATE payment_provider_tickets SET status = 'declined' WHERE status = 'failed'");
            
            DB::statement(
                "ALTER TABLE `payment_provider_tickets` 
                MODIFY COLUMN `status` ENUM(
                    'pending','queued','processing','approved','declined','refunded','expired','cancelled','failed'
                ) DEFAULT 'pending'"
            );
        }
    }
};
