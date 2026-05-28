<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'], // for API tokens
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return ApiResponse::error('Your account is deactivated. Please contact your administrator.', [], 403);
        }

        // Update last active timestamp
        $user->forceFill(['last_active_at' => now()])->save();

        $user->load(['organization', 'roles']);

        // Flatten permissions for easy API use
        $permissions = [];
        foreach ($user->roles as $role) {
            if ($role->permissions) {
                foreach ($role->permissions as $resource => $actions) {
                    if (!isset($permissions[$resource])) {
                        $permissions[$resource] = [];
                    }
                    $permissions[$resource] = array_values(array_unique(array_merge($permissions[$resource], $actions)));
                }
            }
        }

        $token = null;
        // If device_name is provided, we return a Sanctum token
        if ($request->filled('device_name')) {
            $token = $user->createToken($request->device_name)->plainTextToken;
        }

        return ApiResponse::success([
            'user' => $user,
            'permissions' => $permissions,
            'token' => $token,
        ], 'Logged in successfully.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = Auth::user();
        if ($user) {
            // Revoke current token if token authenticated
            if ($request->user() && $request->user()->currentAccessToken()) {
                $request->user()->currentAccessToken()->delete();
            }

            // Log out session
            Auth::guard('web')->logout();
        }

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['organization', 'roles']);

        $permissions = [];
        foreach ($user->roles as $role) {
            if ($role->permissions) {
                foreach ($role->permissions as $resource => $actions) {
                    if (!isset($permissions[$resource])) {
                        $permissions[$resource] = [];
                    }
                    $permissions[$resource] = array_values(array_unique(array_merge($permissions[$resource], $actions)));
                }
            }
        }

        return ApiResponse::success([
            'user' => $user,
            'permissions' => $permissions,
        ], 'User profile retrieved successfully.');
    }
}
