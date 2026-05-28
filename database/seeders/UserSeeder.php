<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::where('slug', 'digicloudify')->first();
        if (!$organization) {
            return;
        }

        $roles = Role::where('organization_id', $organization->id)->get();

        $usersData = [
            [
                'name' => 'CEO User',
                'email' => 'ceo@digicloudify.com',
                'role' => 'ceo',
            ],
            [
                'name' => 'PM User',
                'email' => 'pm@digicloudify.com',
                'role' => 'project_manager',
            ],
            [
                'name' => 'Analyst User',
                'email' => 'analyst@digicloudify.com',
                'role' => 'analyst',
            ],
            [
                'name' => 'Marketer User',
                'email' => 'marketer@digicloudify.com',
                'role' => 'marketer',
            ],
            [
                'name' => 'Developer User',
                'email' => 'developer@digicloudify.com',
                'role' => 'developer',
            ],
            [
                'name' => 'Designer User',
                'email' => 'designer@digicloudify.com',
                'role' => 'designer',
            ],
            [
                'name' => 'Client User',
                'email' => 'client@digicloudify.com',
                'role' => 'client',
            ],
        ];

        foreach ($usersData as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'organization_id' => $organization->id,
                    'name' => $data['name'],
                    'password' => Hash::make('Demo@1234'),
                    'timezone' => 'Asia/Kolkata',
                    'is_active' => true,
                    'preferences' => [
                        'dashboard_layout' => 'default',
                        'notifications' => [
                            'email' => true,
                        ],
                    ],
                ]
            );

            $role = $roles->firstWhere('slug', $data['role']);
            if ($role && !$user->roles()->where('role_id', $role->id)->exists()) {
                $user->roles()->attach($role->id, [
                    'organization_id' => $organization->id,
                    'assigned_at' => now(),
                ]);
            }
        }
    }
}
