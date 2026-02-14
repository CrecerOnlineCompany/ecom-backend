<?php

return [
    /**
     * Feature Flags
     * Control experimental or gradual rollout of features
     */

    /**
     * FEATURE_SEAT_INVENTORY
     * 
     * Controls whether to use the new seat inventory system (screening_seats table)
     * for reservation management instead of the legacy ticket-based checking.
     * 
     * When enabled:
     *  - processTerminalPayment reserves seats before creating tickets
     *  - Uses atomic transactions with SELECT FOR UPDATE to prevent race conditions
     *  - Creates Order entities to group seat reservations
     *  - Sets TTLs on reservations (6 minutes for terminal)
     * 
     * When disabled:
     *  - Falls back to legacy ticket-based availability checking
     *  - No changes to existing behavior
     * 
     * Type: boolean
     * Default: false (legacy behavior)
     * 
     * To enable:
     *  - Set env variable: FEATURE_SEAT_INVENTORY=true
     *  - Or set this to true directly below
     */
    'seat_inventory' => env('FEATURE_SEAT_INVENTORY', false),

    /**
     * FEATURE_AUTO_RECLAIM_EXPIRED_SEATS
     * 
     * Whether to automatically reclaim expired seat reservations.
     * Requires cron job or background task running screening-seats:reclaim-expired
     */
    'auto_reclaim_expired_seats' => env('FEATURE_AUTO_RECLAIM_EXPIRED_SEATS', false),
];
