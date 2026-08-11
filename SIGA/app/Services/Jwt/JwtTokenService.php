<?php

declare(strict_types=1);

namespace App\Services\Jwt;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Issues and verifies the HS256 bearer tokens routes/api.php runs on.
 * Deliberately independent of session auth (see config/jwt.php) — the
 * only two things a controller needs from a JWT.
 */
final class JwtTokenService
{
    public function issue(User $user): string
    {
        $now = time();

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + config('jwt.ttl'),
        ];

        return JWT::encode($payload, (string) config('jwt.secret'), (string) config('jwt.algo'));
    }

    /**
     * Returns the authenticated user id, or null for a missing, expired,
     * malformed or mis-signed token — the middleware treats all of those
     * identically as "not authenticated".
     */
    public function verify(string $token): ?int
    {
        try {
            $decoded = JWT::decode($token, new Key((string) config('jwt.secret'), (string) config('jwt.algo')));
        } catch (Throwable) {
            return null;
        }

        return isset($decoded->sub) ? (int) $decoded->sub : null;
    }
}
