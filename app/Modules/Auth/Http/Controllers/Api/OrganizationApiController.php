<?php

namespace App\Modules\Auth\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\Role;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $org = $request->user()->organization;
        $org->load('roles');

        return ApiResponse::success($org);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'domain'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'logo_url'     => ['sometimes', 'nullable', 'url'],
            'timezone'     => ['sometimes', 'string'],
            'currency'     => ['sometimes', 'string', 'size:3'],
            'date_format'  => ['sometimes', 'string'],
            'fiscal_start' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'settings'     => ['sometimes', 'array'],
        ]);

        $org = $request->user()->organization;
        $org->update($data);

        return ApiResponse::success($org->fresh(), 'Organization updated.');
    }

    public function roles(Request $request): JsonResponse
    {
        $roles = Role::where('organization_id', $request->user()->organization_id)->get();

        return ApiResponse::success($roles);
    }

    public function createRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'slug'        => ['required', 'string', 'max:100', 'alpha_dash'],
            'permissions' => ['required', 'array'],
        ]);

        $role = Role::create([
            'organization_id' => $request->user()->organization_id,
            'name'            => $data['name'],
            'slug'            => $data['slug'],
            'permissions'     => $data['permissions'],
        ]);

        return ApiResponse::success($role, 'Role created.', [], 201);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        if ($role->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'permissions' => ['sometimes', 'array'],
        ]);

        $role->update($data);

        return ApiResponse::success($role->fresh(), 'Role updated.');
    }
}
