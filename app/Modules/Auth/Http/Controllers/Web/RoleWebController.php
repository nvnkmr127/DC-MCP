<?php

namespace App\Modules\Auth\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Modules\Auth\Models\User;
use App\Shared\Enums\RoleType;

use App\Modules\Auth\Models\Role;
use Illuminate\Validation\Rule;

class RoleWebController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::where('organization_id', $request->user()->organization_id)
            ->withCount('users')
            ->get();

        return Inertia::render('Settings/Roles', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
        ]);

        $data['slug'] = strtolower(str_replace(' ', '_', $data['name']));
        $data['organization_id'] = $request->user()->organization_id;
        $data['is_system'] = false;
        $data['permissions'] = $data['permissions'] ?? [];

        Role::create($data);        return back()->with('success', 'Role created successfully.');
    }

    public function update(Request $request, Role $role)
    {
        if ($role->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
        ]);

        if ($role->is_system && $data['name'] !== $role->name) {
            unset($data['name']); // Prevent renaming system roles
        }

        $role->update($data);

        return back()->with('success', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role)
    {
        if ($role->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        if ($role->is_system) {
            return back()->with('error', 'Cannot delete a system role.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Cannot delete role with assigned users.');
        }

        $role->delete();

        return back()->with('success', 'Role deleted successfully.');
    }
}
