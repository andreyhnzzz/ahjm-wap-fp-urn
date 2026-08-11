<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Jwt\JwtTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

final class AuthController extends Controller
{
    public function __construct(private readonly JwtTokenService $jwt) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ])->validate();

        // Auth::once() checks the password against the 'web' guard's
        // provider without starting a session — this endpoint hands out
        // a token, never a cookie.
        if (! Auth::once($credentials)) {
            abort(401, 'Invalid credentials.');
        }

        /** @var User $user */
        $user = Auth::user();

        // ponytail: accounts with 2FA confirmed must still sign in
        // through the web UI, which enforces the challenge. Building a
        // JSON equivalent of the 2FA challenge is out of scope for what
        // this endpoint exists to demonstrate (JWT as a transversal
        // requirement) — add one if the API ever needs to be a primary
        // login surface for 2FA accounts.
        if ($user->two_factor_confirmed_at !== null) {
            abort(423, 'Accounts with two-factor authentication must sign in through the web app.');
        }

        return response()->json([
            'token' => $this->jwt->issue($user),
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
