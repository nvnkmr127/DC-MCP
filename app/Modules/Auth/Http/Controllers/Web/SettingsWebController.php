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
use Illuminate\Support\Facades\Artisan;
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
            'organization' => $org->only(['id', 'name', 'slug', 'timezone', 'currency', 'settings']),            'is_maintenance' => app()->isDownForMaintenance(),
        ]);    }

    public function updateOrganization(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:200',
            'timezone' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'settings' => 'nullable|array',
            'settings.trash_retention_days' => 'nullable|integer|min:1|max:365',
        ]);

        $org = Organization::find($request->user()->organization_id);
        
        if (isset($data['settings'])) {
            $currentSettings = $org->settings ?? [];
            $data['settings'] = array_merge($currentSettings, $data['settings']);
        }

        $org->update($data);

        return back()->with('success', 'Organization updated.');
    }

    public function toggleMaintenance(Request $request)
    {
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
            return back()->with('success', 'Maintenance mode disabled.');
        } else {
            $secret = Str::random(16);
            Artisan::call('down', ['--secret' => $secret]);
            return Inertia::location('/' . $secret);
        }
    }

    public function team(Request $request)
    {
        $startOfWeek = now()->startOfWeek();

        $members = User::where('organization_id', $request->user()->organization_id)
            ->with('roles:id,name,slug')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'     => $u->id,
                'name'   => $u->name,
                'email'  => $u->email,
                'roles'  => $u->roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug]),
                'is_active' => $u->is_active,
                'hours_this_week' => (float) \App\Modules\ProjectManagement\Models\TimeEntry::where('user_id', $u->id)                    ->where('logged_date', '>=', $startOfWeek)
                    ->sum('hours'),
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

    public function bulkInvite(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            'role_id'  => 'required|uuid|exists:roles,id',
        ]);

        $file = $request->file('csv_file');
        $csvData = file_get_contents($file->getRealPath());
        $lines = explode(PHP_EOL, $csvData);
        $imported = 0;
        $skipped = 0;
        $orgId = $request->user()->organization_id;

        foreach ($lines as $line) {
            $row = str_getcsv($line);
            if (count($row) === 0 || empty(trim($line))) continue;

            $email = trim($row[1] ?? $row[0]);
            $name = count($row) > 1 ? trim($row[0]) : explode('@', $email)[0];

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $existing = User::where('email', $email)->first();
            if ($existing) {
                $skipped++;
                continue;
            }

            $tempPassword = Str::random(12);
            $user = User::create([
                'name'            => $name ?: 'Invited User',
                'email'           => $email,
                'password'        => Hash::make($tempPassword),
                'organization_id' => $orgId,
            ]);

            $user->roles()->attach($request->role_id);
            // TODO: queue bulk invite email with temp password
            $imported++;
        }

        return back()->with('success', "Bulk invite completed. Imported: {$imported}, Skipped: {$skipped}.");
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

        $oldRole = $user->roles->first();

        // Sync the role (since we enforce a single role dropdown in UI)
        $user->roles()->sync([
            $role->id => [
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
            ]
        ]);

        if (!$oldRole || $oldRole->id !== $role->id) {
            \App\Models\Activity::create([
                'user_id' => $request->user()->id,
                'subject_type' => get_class($user),
                'subject_id' => $user->id,
                'description' => "Role changed from " . ($oldRole ? $oldRole->name : 'None') . " to {$role->name}",
                'changes' => [
                    'old_role' => $oldRole ? $oldRole->name : null,
                    'new_role' => $role->name
                ],
            ]);
        }

        return back()->with('success', "Updated role for {$user->name}.");
    }

    public function memberActivity(Request $request, User $user)
    {
        if ($user->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        $activities = \App\Models\Activity::with('user:id,name')
            ->where('subject_type', get_class($user))
            ->where('subject_id', $user->id)
            ->latest()
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'actor' => $a->user->name ?? 'System',
                'description' => $a->description,
                'created_at' => $a->created_at->diffForHumans(),
            ]);

        return response()->json($activities);
    }

    public function forceLogoutUser(Request $request, User $user)
    {
        if ($user->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
        return back()->with('success', "Force logged out {$user->name}.");
    }

    public function impersonate(Request $request, User $user)
    {
        if ($user->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Cannot impersonate yourself.');
        }

        $request->session()->put('impersonated_by', $request->user()->id);
        Auth::login($user);

        return redirect('/dashboard')->with('success', "You are now impersonating {$user->name}.");
    }

    public function transferWork(Request $request, User $user)
    {
        $request->validate([
            'transfer_to_user_id' => 'required|uuid|exists:users,id',
        ]);

        if ($user->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        $targetUser = User::where('organization_id', $request->user()->organization_id)
            ->where('id', $request->transfer_to_user_id)
            ->where('is_active', true)
            ->firstOrFail();

        \App\Modules\ProjectManagement\Models\Project::where('project_manager_id', $user->id)
            ->update(['project_manager_id' => $targetUser->id]);

        \App\Modules\ProjectManagement\Models\Task::where('assigned_to', $user->id)
            ->update(['assigned_to' => $targetUser->id]);

        return back()->with('success', "Transferred all tasks and projects from {$user->name} to {$targetUser->name}.");
    }

    public function toggleActive(Request $request, User $user)
    {
        if ($user->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "User {$user->name} has been {$status}.");
    }

    public function stopImpersonating(Request $request)
    {
        if (!$request->session()->has('impersonated_by')) {
            return back();
        }

        $originalUserId = $request->session()->pull('impersonated_by');
        $originalUser = User::find($originalUserId);

        if ($originalUser) {
            Auth::login($originalUser);
            return redirect('/settings/team')->with('success', 'Stopped impersonating.');
        }

        Auth::logout();
        return redirect('/login');
    }

    public function terminateSession(Request $request, string $id)
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
