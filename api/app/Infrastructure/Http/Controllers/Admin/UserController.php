<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->role, fn ($query, $role) => $query->where('role', $role))
            ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->when($request->search, fn ($query, $search) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $user = User::create([
            ...$data,
            'email' => mb_strtolower($data['email']),
            'password' => Hash::make($data['password'] ?? Str::random(40)),
            'active' => $data['active'] ?? true,
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate($this->rules($user));
        if (isset($data['email'])) $data['email'] = mb_strtolower($data['email']);
        if (!empty($data['password'])) $data['password'] = Hash::make($data['password']);
        else unset($data['password']);

        if ($user->is($request->user()) && (($data['active'] ?? true) === false || ($data['role'] ?? 'admin') !== 'admin')) {
            throw ValidationException::withMessages(['user' => 'No puedes quitarte tu propio acceso administrativo.']);
        }

        $wasActive = $user->active;
        $user->update($data);
        if ($wasActive && ! $user->fresh()->active) {
            $user->tokens()->delete();
        }
        return response()->json($user->fresh());
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'No puedes desactivar tu propia cuenta.']);
        }
        $user->update(['active' => false]);
        $user->tokens()->delete();
        return response()->json(['message' => 'Usuario desactivado correctamente.']);
    }

    private function rules(?User $user = null): array
    {
        $sometimes = $user ? 'sometimes' : 'required';
        return [
            'name' => [$sometimes, 'string', 'max:255'],
            'email' => [$sometimes, 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => [$sometimes, Rule::in($user?->role === 'coordinator' ? ['teacher', 'coordinator', 'admin'] : ['teacher', 'admin'])],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
