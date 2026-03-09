<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['vendor', 'client'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
        ]);

        [$plainToken, $token] = $this->createToken($user, 'auth-register');

        return response()->json([
            'message' => 'Inscription reussie.',
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toISOString(),
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email ou mot de passe invalide.',
            ], 401);
        }

        [$plainToken, $token] = $this->createToken($user, 'auth-login');

        return response()->json([
            'message' => 'Connexion reussie.',
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toISOString(),
            'user' => $user,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profil mis a jour avec succes.',
            'user' => $user->fresh(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var ApiToken|null $token */
        $token = $request->attributes->get('auth_token');

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Deconnexion reussie.',
        ]);
    }

    /**
     * @return array{0: string, 1: ApiToken}
     */
    private function createToken(User $user, string $name): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $ttlDays = (int) env('AUTH_TOKEN_TTL_DAYS', 7);
        $expiresAt = $ttlDays > 0 ? now()->addDays($ttlDays) : null;

        $token = ApiToken::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        return [$plainToken, $token];
    }
}
