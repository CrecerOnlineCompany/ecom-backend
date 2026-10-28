<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Shop\AimeosSiteManager;
use App\Services\Shop\ManagedSite;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only admin roles into the panel and resolves the store they work on:
 * regular admins are pinned to their own store, superadmins pick one (kept in the session).
 *
 * Usage: "manage" for any admin role, "manage:admin" to exclude editors,
 * "manage:superadmin" for superadmin-only endpoints.
 */
class EnsureManageAccess
{
    public const SESSION_KEY = 'manage_site';

    public function __construct(
        private readonly AimeosSiteManager $sites,
        private readonly ManagedSite $managedSite,
    ) {
    }

    public function handle(Request $request, Closure $next, ?string $level = null): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user || !$user->canManage()) {
            return $this->deny($request);
        }

        if ($level === 'superadmin' && !$user->isSuperadmin()) {
            abort(403, 'Solo un superadmin puede hacer esto.');
        }

        if ($level === 'admin' && $user->role === User::ROLE_EDITOR) {
            abort(403, 'Tu rol no tiene acceso a esta seccion.');
        }

        $this->managedSite->set($this->resolveSite($request, $user));

        return $next($request);
    }

    private function resolveSite(Request $request, User $user): string
    {
        if (!$user->isSuperadmin()) {
            return $user->site_code ?: 'default';
        }

        $code = (string) $request->session()->get(self::SESSION_KEY, $user->site_code ?: 'default');

        return $this->sites->exists($code) ? $code : 'default';
    }

    private function deny(Request $request): Response
    {
        if ($request->expectsJson()) {
            abort(403, 'Tu usuario no tiene acceso al panel de administracion.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'Tu usuario no tiene acceso al panel de administracion.',
        ]);
    }
}
