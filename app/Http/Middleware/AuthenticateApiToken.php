<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return $this->unauthorized('Missing bearer token.');
        }

        $tokenHash = hash('sha256', $bearerToken);

        $token = ApiToken::with('user')
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $token || ! $token->user) {
            return $this->unauthorized('Invalid token.');
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            $token->delete();

            return $this->unauthorized('Token expired.');
        }

        $token->update(['last_used_at' => now()]);

        $request->attributes->set('auth_token', $token);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }
}
