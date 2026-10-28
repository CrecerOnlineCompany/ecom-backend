<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ManageAccessTest extends TestCase
{
    public function test_storefront_customer_cannot_open_admin(): void
    {
        $this->actingAs($this->user(User::ROLE_CUSTOMER))
            ->get('/admin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_storefront_customer_cannot_use_admin_api(): void
    {
        $this->actingAs($this->user(User::ROLE_CUSTOMER))
            ->getJson('/admin/api/products')
            ->assertForbidden();
    }

    public function test_inactive_admin_cannot_use_admin_api(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN, 0))
            ->getJson('/admin/api/products')
            ->assertForbidden();
    }

    public function test_editor_cannot_manage_users(): void
    {
        $this->actingAs($this->user(User::ROLE_EDITOR))
            ->getJson('/admin/api/users')
            ->assertForbidden();
    }

    public function test_store_admin_cannot_manage_clients(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->getJson('/admin/api/sites')
            ->assertForbidden();

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->putJson('/admin/api/session/site', ['code' => 'default'])
            ->assertForbidden();
    }

    private function user(string $role, int $status = 1): User
    {
        $user = new User([
            'name' => 'Test',
            'email' => strtolower($role) . '@example.com',
            'role' => $role,
            'status' => $status,
            'site_code' => 'tienda-test',
        ]);
        $user->id = 999;
        // Logging a denied user out touches remember_token; keep it from inserting a row.
        $user->exists = true;

        return $user;
    }
}
