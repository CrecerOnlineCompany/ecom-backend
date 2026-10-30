<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ecom:superadmin {email}', function (string $email) {
    $user = User::query()->where('email', $email)->first();

    if (!$user) {
        $this->error("No existe un usuario con el email {$email}.");

        return 1;
    }

    $user->update(['role' => User::ROLE_SUPERADMIN, 'superuser' => true, 'status' => 1]);
    $this->info("{$email} ahora es superadmin y puede crear clientes desde /admin/sites.");

    return 0;
})->purpose('Promote an existing user to superadmin of the admin panel');
