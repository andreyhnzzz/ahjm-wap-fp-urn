<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Jwt\JwtTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stateless guard for routes/api.php: reads the Bearer token, resolves
 * the user for this request only (no session, no cookie), and rejects
 * with 401 on anything invalid — missing header, bad signature, expired
 * token, or a user id that no longer exists.
 *
 * ponytail: Auth::setUser() on the default guard rather than a dedicated
 * 'api' guard/driver — the only two protected resources are this file's
 * routes, so a second guard config would be ceremony with no behavior
 * difference. Add a real 'api' guard if session and token auth ever need
 * to coexist in the same request.
 */
final class AuthenticateWithJwt
{
    public function __construct(private readonly JwtTokenService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $userId = $token !== null ? $this->jwt->verify($token) : null;
        $user = $userId !== null ? User::query()->find($userId) : null;

        if (! $user) {
            abort(401, 'Invalid or expired token.');
        }

        Auth::setUser($user);

        return $next($request);
    }
}
