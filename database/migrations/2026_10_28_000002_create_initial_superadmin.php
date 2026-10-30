<?php

use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Creates (or promotes) the first superadmin of the admin panel from the server's .env:
 *
 *   SUPERADMIN_EMAIL=...
 *   SUPERADMIN_PASSWORD=...   (only used when the user does not exist yet)
 *   SUPERADMIN_NAME=...       (optional)
 *
 * Credentials never live in the repository. If SUPERADMIN_EMAIL is missing the migration
 * does nothing; `php artisan ecom:superadmin {email}` can be used later instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        $email = $this->setting('SUPERADMIN_EMAIL');

        if (!$email) {
            Log::warning('Superadmin no creado: falta SUPERADMIN_EMAIL en el .env.');

            return;
        }

        $attributes = [
            'role' => User::ROLE_SUPERADMIN,
            'superuser' => true,
            'status' => 1,
            'site_code' => 'default',
        ];

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->update($attributes);

            return;
        }

        $password = $this->setting('SUPERADMIN_PASSWORD');

        if (!$password || strlen($password) < 8) {
            Log::warning('Superadmin no creado: SUPERADMIN_PASSWORD falta o tiene menos de 8 caracteres.');

            return;
        }

        User::query()->create($attributes + [
            'name' => $this->setting('SUPERADMIN_NAME') ?: 'Superadmin',
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function down(): void
    {
        // Intentionally empty: rolling back must not lock everyone out of the panel.
    }

    /**
     * env() returns null while a config cache exists (the deploy migrates before
     * rebuilding it), so fall back to reading the .env file itself.
     */
    private function setting(string $key): ?string
    {
        $value = env($key);

        if ($value === null && is_file(base_path('.env'))) {
            $value = Dotenv::parse(file_get_contents(base_path('.env')))[$key] ?? null;
        }

        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
};
