<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Shop\ManagedSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private readonly ManagedSite $site)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::query()
                ->whereIn('role', User::MANAGE_ROLES)
                ->where('site_code', $this->site->code())
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => $this->present($user)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);
        $data['password'] = $request->filled('password') ? $request->input('password') : Str::password(16);
        $data['superuser'] = $data['role'] === User::ROLE_SUPERADMIN;
        $data['site_code'] = $this->site->code();

        $user = User::query()->create($data);

        return response()->json([
            'data' => $this->present($user),
            'message' => 'Usuario guardado.',
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless(
            $user->site_code === $this->site->code() && in_array($user->role, User::MANAGE_ROLES, true),
            404
        );
        abort_if($user->isSuperadmin() && !$request->user()->isSuperadmin(), 403, 'No podes editar a un superadmin.');

        $data = $this->validatedData($request, $user);
        $data['superuser'] = $data['role'] === User::ROLE_SUPERADMIN;

        if (!$request->filled('password')) {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'data' => $this->present($user),
            'message' => 'Usuario guardado.',
        ]);
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $roles = $request->user()->isSuperadmin()
            ? User::MANAGE_ROLES
            : [User::ROLE_ADMIN, User::ROLE_EDITOR];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in($roles)],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);
    }

    private function present(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => (int) $user->status === 0 ? 'Inactivo' : 'Activo',
            'site_code' => $user->site_code,
        ];
    }
}
