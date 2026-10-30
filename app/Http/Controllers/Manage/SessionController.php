<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureManageAccess;
use App\Services\Shop\AimeosSiteManager;
use App\Services\Shop\ManagedSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function __construct(
        private readonly AimeosSiteManager $sites,
        private readonly ManagedSite $site,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $this->sites->find($this->site->code());

        return response()->json([
            'data' => [
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'isSuperadmin' => $user->isSuperadmin(),
                'site' => [
                    'code' => $this->site->code(),
                    'name' => $current['label'] ?? $this->site->code(),
                    'storefront' => '/t/' . $this->site->code(),
                ],
                'sites' => $user->isSuperadmin()
                    ? array_map(fn (array $site) => ['code' => $site['code'], 'name' => $site['label']], $this->sites->list())
                    : [],
            ],
        ]);
    }

    public function switchSite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        if (!$this->sites->exists($data['code'])) {
            throw ValidationException::withMessages(['code' => 'La tienda no existe.']);
        }

        $request->session()->put(EnsureManageAccess::SESSION_KEY, $data['code']);

        return response()->json([
            'data' => ['code' => $data['code']],
            'message' => 'Tienda seleccionada.',
        ]);
    }
}
