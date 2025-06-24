<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class AuthTokenController extends Controller
{
    /**
     * Issue a Sanctum personal access token for the currently authenticated web-session user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Revoke previous "web" tokens to avoid token bloat (optional)
        $user->tokens()->where('name', 'web')->delete();

        // Issue token with full abilities (adjust scopes if needed)
        $token = $user->createToken('web', ['*'])->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }
}
