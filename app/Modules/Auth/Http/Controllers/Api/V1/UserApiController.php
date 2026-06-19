<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\Role;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles')
            ->where('organization_id', $request->user()->organization_id)
            ->get();

        return ApiResponse::success($users);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->sameOrg($request, $user);
        $user->load('roles');
        return ApiResponse::success($user);
    }

    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'role_slug'=> ['required', 'string', 'exists:roles,slug'],
        ]);

        $role = Role::where('slug', $data['role_slug'])
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $temporaryPassword = \Illuminate\Support\Str::random(16);

        $user = User::create([
            'organization_id' => $request->user()->organization_id,
            'name'            => $data['name'],
            'email'           => $data['email'],
            'password'        => Hash::make($temporaryPassword),
            'is_active'       => true,
        ]);

        $user->roles()->attach($role->id, [
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        return ApiResponse::success([
            'user' => $user,
            'temporary_password' => $temporaryPassword,
        ], 'User invited. Share the temporary password securely.', [], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->sameOrg($request, $user);

        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'avatar_url' => ['sometimes', 'nullable', 'url'],
            'timezone'   => ['sometimes', 'string'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        return ApiResponse::success($user->fresh(), 'User updated.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return ApiResponse::error('Current password is incorrect.', [], 422);
        }

        $user->forceFill(['password' => Hash::make($request->password)])->save();

        return ApiResponse::success(null, 'Password updated successfully.');
    }

    public function assignRole(Request $request, User $user): JsonResponse
    {
        $this->sameOrg($request, $user);

        $data = $request->validate([
            'role_slug' => ['required', 'string', 'exists:roles,slug'],
        ]);

        $role = Role::where('slug', $data['role_slug'])
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        // Detach all existing roles and assign new one
        $user->roles()->sync([
            $role->id => ['assigned_by' => $request->user()->id, 'assigned_at' => now()],
        ]);

        return ApiResponse::success($user->fresh(['roles']), 'Role assigned.');
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        $this->sameOrg($request, $user);

        if ($user->id === $request->user()->id) {
            return ApiResponse::error('You cannot deactivate your own account.', [], 422);
        }

        $user->update(['is_active' => false]);

        return ApiResponse::success(null, 'User deactivated.');
    }

    private function sameOrg(Request $request, User $user): void
    {
        if ($user->organization_id !== $request->user()->organization_id) {
            abort(403, 'Forbidden.');
        }
    }
}
