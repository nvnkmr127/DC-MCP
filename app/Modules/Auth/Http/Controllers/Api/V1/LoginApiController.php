<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginApiController extends Controller
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
            'scopes' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            Log::warning('Failed login attempt', [
                'email' => $request->email,
                'ip'    => $request->ip(),
            ]);
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            Log::warning('Login rejected — deactivated account', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'ip'      => $request->ip(),
            ]);
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
        if ($request->filled('device_name')) {
            $scopes = $request->input('scopes', ['*']);
            $expiresAt = $request->input('expires_at') 
                ? \Carbon\Carbon::parse($request->input('expires_at')) 
                : now()->addDays(30); // Default to 30 days

            $token = $user->createToken($request->device_name, $scopes, $expiresAt)->plainTextToken;
            Log::info('API token issued', [
                'user_id'     => $user->id,
                'device_name' => $request->device_name,
                'ip'          => $request->ip(),
            ]);
        }

        Log::info('User login', [
            'user_id'         => $user->id,
            'organization_id' => $user->organization_id,
            'ip'              => $request->ip(),
            'api_token'       => $token !== null,
        ]);

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
            Log::info('User logout', [
                'user_id'         => $user->id,
                'organization_id' => $user->organization_id,
                'ip'              => $request->ip(),
            ]);

            if ($request->user() && $request->user()->currentAccessToken()) {
                $request->user()->currentAccessToken()->delete();
            }

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
