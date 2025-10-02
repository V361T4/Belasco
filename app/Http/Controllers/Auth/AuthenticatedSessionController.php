<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an API login request and return token.
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $wantsJson = $request->expectsJson() || $request->is('api/*');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            if ($wantsJson) {
                return response()->json([
                    'message' => trans('auth.failed'),
                ], 422);
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        if ($wantsJson) {
            $token = $request->user()->createToken('api-token')->plainTextToken;

            return response()->json([
                'user'  => $request->user(),
                'token' => $token,
            ]);
        }

        return response()->noContent();
    }

    /**
     * Logout (revoke the token).
     */
    public function destroy(Request $request)
    {
        $wantsJson = $request->expectsJson() || $request->is('api/*');

        $user = $request->user();

        if ($wantsJson && $user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
