<?php

namespace App\Services\Shop;

/**
 * Store (Aimeos site code) the admin panel is working on for the current request.
 * Resolved by the EnsureManageAccess middleware.
 */
class ManagedSite
{
    private string $code = 'default';

    public function set(string $code): void
    {
        $this->code = $code;
    }

    public function code(): string
    {
        return $this->code;
    }
}
