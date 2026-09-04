<?php

namespace App\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas. Verifica tu correo y contraseña.',
            ], 401);
        }

        if (!$user->active) {
            return response()->json([
                'message' => 'Tu cuenta está desactivada. Contacta al administrador.',
            ], 403);
        }

        $user->update(['last_login' => now()]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'avatar' => $user->google_avatar,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
            'avatar' => $user->google_avatar,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        // No usar exists:users,email aquí: revelaría si un correo está
        // registrado o no. El mensaje debe ser el mismo exista o no.
        $request->validate([
            'email' => 'required|email',
        ]);

        return response()->json([
            'message' => 'Si el correo existe, recibirás un enlace para restablecer tu contraseña.',
        ]);
    }
}
