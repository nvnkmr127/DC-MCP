<?php

namespace App\Modules\Auth\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Modules\ProjectManagement\Services\ProjectService;

class SetupController extends Controller
{
    public function index(Request $request)
    {
        $org = $request->user()->organization;
        
        // If already onboarded, send to dashboard
        if ($org->is_onboarded) {
            return redirect()->route('dashboard');
        }

        $roles = Role::where('organization_id', $org->id)->get(['id', 'name', 'slug']);

        return Inertia::render('Setup/Index', [
            'organization' => $org->only('name', 'timezone', 'currency'),
            'roles' => $roles,
        ]);
    }

    public function complete(Request $request)
    {
        $org = $request->user()->organization;
        if ($org->is_onboarded) {
            return redirect()->route('dashboard');
        }

        $data = $request->validate([
            'organization.name'     => 'required|string|max:120',
            'organization.timezone' => 'required|string',
            'organization.currency' => 'required|string|max:3',
            'team'                  => 'array',
            'team.*.name'           => 'required|string|max:120',
            'team.*.email'          => 'required|email',
            'team.*.role_id'        => 'required|uuid|exists:roles,id',
            'project'               => 'nullable|array',
            'project.name'          => 'required_with:project|string|max:120',
            'project.budget'        => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($data, $org, $request) {
            // 1. Update Organization
            $org->update([
                'name' => $data['organization']['name'],
                'timezone' => $data['organization']['timezone'],
                'currency' => $data['organization']['currency'],
                'is_onboarded' => true,
            ]);

            // 2. Invite Team
            if (!empty($data['team'])) {
                foreach ($data['team'] as $member) {
                    $existing = User::where('email', $member['email'])->first();
                    if (!$existing) {
                        $tempPassword = Str::random(12);
                        $user = User::create([
                            'name'            => $member['name'],
                            'email'           => $member['email'],
                            'password'        => Hash::make($tempPassword),
                            'organization_id' => $org->id,
                        ]);
                        $user->roles()->attach($member['role_id']);
                        // TODO: Send invite email here
                    }
                }
            }

            // 3. Create First Project
            if (!empty($data['project']['name'])) {
                $projectService = app(ProjectService::class);
                $projectService->createProject([
                    'name' => $data['project']['name'],
                    'status' => 'planning',
                    'budget' => $data['project']['budget'] ?? null,
                    'organization_id' => $org->id,
                ]);
            }
        });

        return redirect()->route('dashboard')->with('success', 'Setup completed successfully!');
    }
}
