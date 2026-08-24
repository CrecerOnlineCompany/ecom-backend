<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => $this->present($user)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);
        $data['password'] = $request->filled('password') ? $request->input('password') : Str::password(16);
        $data['superuser'] = $data['role'] === 'Superadmin';

        $user = User::query()->create($data);

        return response()->json([
            'data' => $this->present($user),
            'message' => 'Usuario guardado.',
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $this->validatedData($request, $user);
        $data['superuser'] = $data['role'] === 'Superadmin';

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
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:Superadmin,Admin,Editor'],
            'status' => ['required', 'integer', 'in:0,1'],
            'siteid' => ['nullable', 'string', 'max:64'],
        ]);
    }

    private function present(User $user): array
    {
        $role = $user->role ?: ($user->superuser ? 'Superadmin' : 'Admin');

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'status' => (int) $user->status === 0 ? 'Inactivo' : 'Activo',
            'siteid' => $user->siteid ?: 'default',
        ];
    }
}
