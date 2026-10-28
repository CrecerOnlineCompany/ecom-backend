<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_admin(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_unknown_paths_redirect_to_admin(): void
    {
        $this->get('/cualquier-cosa')->assertRedirect('/admin');
    }

    public function test_guest_is_sent_to_login_from_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_login_page_is_available(): void
    {
        $this->withoutVite()->get('/login')->assertOk();
    }
}
