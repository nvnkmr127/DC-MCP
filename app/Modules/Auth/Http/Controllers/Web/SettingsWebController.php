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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SettingsWebController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();
        
        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'ip_address' => $s->ip_address,
                'user_agent' => $s->user_agent,
                'last_activity' => \Carbon\Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                'is_current' => $s->id === $request->session()->getId(),
            ]);

        $tokens = $user->tokens->map(fn($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'last_used_at' => $t->last_used_at?->diffForHumans(),
            'created_at' => $t->created_at->toIso8601String(),
        ]);

        return Inertia::render('Settings/Profile', [
            'user' => $user->only('id', 'name', 'display_name', 'email', 'timezone', 'avatar_url', 'phone', 'created_at', 'preferences'),
            'sessions' => $sessions,
            'tokens' => $tokens,
            'connectedAccounts' => $user->connectedAccounts,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:120',
            'display_name' => 'nullable|string|max:120',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'required|email|unique:users,email,' . $request->user()->id,
            'timezone'     => 'nullable|string|timezone',
            'date_format'  => 'nullable|string',
            'currency'     => 'nullable|string|max:3',
            'avatar'       => 'nullable|image|max:2048',
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            // Store the avatar in the public disk, under an "avatars" directory
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar_url'] = '/storage/' . $path;
        }
        
        // Remove 'avatar' from $data so we don't try to save the UploadedFile object
        unset($data['avatar']);

        $user->update($data);
        return back()->with('success', 'Profile updated.');
    }

    public function localization(Request $request)
    {
        return Inertia::render('Settings/Localization');
    }

    public function notifications(Request $request)
    {
        return Inertia::render('Settings/Notifications', [
            'user' => $request->user()->only('notification_preferences'),
        ]);
    }

    public function updateNotifications(Request $request)
    {
        $data = $request->validate([
            'preferences' => 'required|array',
            'preferences.email_tasks' => 'boolean',
            'preferences.push_tasks' => 'boolean',
            'preferences.email_projects' => 'boolean',
            'preferences.push_projects' => 'boolean',
        ]);

        $request->user()->update([
            'notification_preferences' => $data['preferences']
        ]);

        return back()->with('success', 'Notification preferences updated.');
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

    public function updateMemberRole(Request $request, User $user)
    {
        if ($user->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        $data = $request->validate([
            'role_id' => 'required|uuid|exists:roles,id',
        ]);

        $role = Role::where('id', $data['role_id'])
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        // Sync the role (since we enforce a single role dropdown in UI)
        $user->roles()->sync([$role->id]);

        return back()->with('success', 'User role updated successfully.');
    }

    public function destroySession(Request $request, string $id)
    {
        DB::table('sessions')->where('id', $id)->where('user_id', $request->user()->id)->delete();
        return back()->with('success', 'Session terminated.');
    }

    public function createToken(Request $request)
    {
        $request->validate(['token_name' => 'required|string|max:255']);
        $token = $request->user()->createToken($request->token_name);
        
        return back()->with('new_token', $token->plainTextToken);
    }

    public function revokeToken(Request $request, string $id)
    {
        $request->user()->tokens()->where('id', $id)->delete();
        return back()->with('success', 'API token revoked.');
    }

    public function exportData(Request $request)
    {
        $user = $request->user()->load('connectedAccounts');
        $data = [
            'profile' => $user->toArray(),
            'export_date' => now()->toIso8601String(),
        ];

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT);
        }, 'user-data-export.json');
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        $user->delete(); // Soft delete

        return redirect('/')->with('success', 'Your account has been deleted.');
    }
}
