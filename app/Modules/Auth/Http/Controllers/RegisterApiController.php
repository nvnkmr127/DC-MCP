<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\RoleService;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterApiController extends Controller
{
    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Register a new organization and its first owner (CEO).
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
            'organization_name' => ['required', 'string', 'max:255'],
        ]);

        try {
            return DB::transaction(function () use ($request) {
                // 1. Create Organization
                $organization = Organization::create([
                    'name' => $request->organization_name,
                    'slug' => Str::slug($request->organization_name) . '-' . Str::random(4),
                    'plan' => 'free',
                    'is_active' => true,
                    'settings' => [
                        'currency' => 'INR',
                        'timezone' => 'Asia/Kolkata',
                    ],
                ]);

                // 2. Seed default roles for this Organization
                $roles = $this->roleService->seedOrganizationRoles($organization);

                // 3. Create CEO User
                $user = User::create([
                    'organization_id' => $organization->id,
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'timezone' => 'Asia/Kolkata',
                    'is_active' => true,
                    'preferences' => [
                        'dashboard_layout' => 'default',
                        'notifications' => [
                            'email' => true,
                            'zoho_cliq' => false,
                        ],
                    ],
                ]);

                // 4. Attach CEO Role
                $ceoRole = $roles['ceo'];
                $user->roles()->attach($ceoRole->id, [
                    'organization_id' => $organization->id,
                    'assigned_at' => now(),
                ]);

                // Load relationships
                $user->load(['organization', 'roles']);

                // Authenticate the user
                auth()->login($user);

                // Flatten permissions for response
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
                ], 'Organization registered and user logged in successfully.', [], 201);
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Registration failed: ' . $e->getMessage(), [], 500);
        }
    }
}
