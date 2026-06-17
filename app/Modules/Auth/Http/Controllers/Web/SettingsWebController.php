<?php

namespace App\Modules\Auth\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\Role;

class SettingsWebController extends Controller
{
    public function profile(Request $request)
    {
        return Inertia::render('Settings/Profile', [
            'user' => $request->user()->only('id', 'name', 'email', 'timezone', 'avatar_url', 'preferences'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|unique:users,email,' . $request->user()->id,
            'timezone' => 'nullable|string|timezone',
        ]);

        $request->user()->update($data);
        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password updated.');
    }

    public function organization(Request $request)
    {
        $org = Organization::find($request->user()->organization_id);

        return Inertia::render('Settings/Organization', [
            'organization' => $org->only('id', 'name', 'slug', 'timezone', 'currency', 'settings'),
        ]);
    }

    public function updateOrganization(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:200',
            'timezone' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
        ]);

        $org = Organization::find($request->user()->organization_id);
        $org->update($data);

        return back()->with('success', 'Organization updated.');
    }

    public function team(Request $request)
    {
        $members = User::where('organization_id', $request->user()->organization_id)
            ->with('roles:id,name,slug')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'     => $u->id,
                'name'   => $u->name,
                'email'  => $u->email,
                'roles'  => $u->roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug]),
                'is_active' => !$u->deleted_at,
            ]);

        $roles = Role::where('organization_id', $request->user()->organization_id)
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Settings/Team', [
            'members' => $members,
            'roles'   => $roles,
        ]);
    }

    public function invite(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:120',
            'email'   => 'required|email|unique:users,email',
            'role_id' => 'required|uuid|exists:roles,id',
        ]);

        $tempPassword = Str::random(12);

        $user = User::create([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'password'        => Hash::make($tempPassword),
            'organization_id' => $request->user()->organization_id,
        ]);

        $user->roles()->attach($data['role_id']);

        // TODO: send invite email with temp password
        return back()->with('success', "Invite sent to {$data['email']}. Temp password: {$tempPassword}");
    }
}
