<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Shop\AimeosSiteManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Clients of the platform. Each client is an Aimeos site with its own catalog,
 * orders, settings, payment methods and admin users.
 */
class SiteController extends Controller
{
    public function __construct(private readonly AimeosSiteManager $sites)
    {
    }

    public function index(): JsonResponse
    {
        $admins = User::query()
            ->whereIn('role', User::MANAGE_ROLES)
            ->select('site_code', DB::raw('count(*) as total'))
            ->groupBy('site_code')
            ->pluck('total', 'site_code');

        return response()->json([
            'data' => array_map(fn (array $site) => $this->present($site, (int) ($admins[$site['code']] ?? 0)), $this->sites->list()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // "demo" is the storefront alias of the default store.
            'code' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(['demo'])],
            'currency' => ['required', 'string', 'size:3'],
            'language' => ['required', 'string', 'min:2', 'max:5'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:8'],
        ], [
            'code.regex' => 'El codigo solo puede tener minusculas, numeros y guiones.',
            'code.not_in' => 'Ese codigo esta reservado.',
            'admin_email.unique' => 'Ya existe un usuario con ese email.',
        ]);

        if ($this->sites->exists($data['code'])) {
            return response()->json([
                'message' => 'Ya existe un cliente con ese codigo.',
                'errors' => ['code' => ['Ya existe un cliente con ese codigo.']],
            ], 422);
        }

        $currency = strtoupper($data['currency']);
        $language = strtolower($data['language']);

        try {
            $site = $this->sites->create($data['code'], $data['name'], $language, $currency);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo crear la tienda: ' . $exception->getMessage(),
            ], 500);
        }

        DB::transaction(function () use ($data, $currency, $language) {
            $settings = [
                'commercial_name' => $data['name'],
                'contact_email' => $data['admin_email'],
                'primary_currency' => $currency,
                'primary_language' => $language,
            ];

            foreach ($settings as $key => $value) {
                ShopSetting::query()->updateOrCreate(
                    ['site_code' => $data['code'], 'key' => $key],
                    ['value' => $value]
                );
            }

            User::query()->create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'role' => User::ROLE_ADMIN,
                'status' => 1,
                'superuser' => false,
                'site_code' => $data['code'],
            ]);
        });

        return response()->json([
            'data' => $this->present($site, 1),
            'message' => 'Cliente creado.',
        ], 201);
    }

    public function update(Request $request, string $site): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:Activo,Inactivo'],
        ]);

        abort_if($site === 'default' && $data['status'] !== 'Activo', 422, 'La tienda principal no se puede desactivar.');

        $updated = $this->sites->update($site, $data['name'], $data['status'] === 'Activo');
        $admins = User::query()->whereIn('role', User::MANAGE_ROLES)->where('site_code', $site)->count();

        return response()->json([
            'data' => $this->present($updated, $admins),
            'message' => 'Cliente guardado.',
        ]);
    }

    private function present(array $site, int $admins): array
    {
        return [
            'id' => $site['code'],
            'name' => $site['label'],
            'meta' => $site['code'],
            'admins' => $admins,
            'status' => $site['status'],
            'storefront' => '/t/' . $site['code'],
            'createdAt' => $site['createdAt'],
        ];
    }
}
