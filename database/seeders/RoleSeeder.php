<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Services\RoleService;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();
        foreach ($organizations as $organization) {
            $this->roleService->seedOrganizationRoles($organization);
        }
    }
}
