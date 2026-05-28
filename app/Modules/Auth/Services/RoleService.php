<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\Role;

class RoleService
{
    /**
     * Seed default system roles for a given organization.
     *
     * @param Organization $organization
     * @return array<string, Role>
     */
    public function seedOrganizationRoles(Organization $organization): array
    {
        $defaultRoles = [
            [
                'name' => 'CEO',
                'slug' => 'ceo',
                'description' => 'Full access to everything in their organization',
                'is_system' => true,
                'permissions' => [
                    '*' => ['*']
                ],
            ],
            [
                'name' => 'Project Manager',
                'slug' => 'project_manager',
                'description' => 'Manage all projects, assign tasks, view all reports',
                'is_system' => true,
                'permissions' => [
                    'project' => ['view', 'create', 'update', 'delete', 'manage_settings'],
                    'task' => ['view', 'create', 'update', 'delete', 'assign'],
                    'report' => ['view', 'create', 'update', 'delete', 'export'],
                    'client' => ['view', 'create', 'update', 'delete'],
                    'metric' => ['view', 'create', 'update'],
                    'user' => ['view', 'create', 'update'],
                    'mcp_connection' => ['view', 'create', 'update', 'delete'],
                    'briefing' => ['view', 'create'],
                ],
            ],
            [
                'name' => 'Analyst',
                'slug' => 'analyst',
                'description' => 'View all data, create reports, manage metrics',
                'is_system' => true,
                'permissions' => [
                    'project' => ['view'],
                    'task' => ['view', 'update'],
                    'report' => ['view', 'create', 'update', 'export'],
                    'client' => ['view'],
                    'metric' => ['view', 'create', 'update', 'delete'],
                    'user' => ['view'],
                    'mcp_connection' => ['view'],
                    'briefing' => ['view', 'create'],
                ],
            ],
            [
                'name' => 'Marketer',
                'slug' => 'marketer',
                'description' => 'Manage campaigns, tasks assigned to them, content calendar',
                'is_system' => true,
                'permissions' => [
                    'project' => ['view'],
                    'task' => ['view', 'update', 'assign'],
                    'report' => ['view'],
                    'client' => ['view'],
                    'metric' => ['view'],
                    'user' => ['view'],
                    'mcp_connection' => ['view'],
                    'briefing' => ['view'],
                ],
            ],
            [
                'name' => 'Developer',
                'slug' => 'developer',
                'description' => 'View dev tasks only, sprint boards, time logging',
                'is_system' => true,
                'permissions' => [
                    'project' => ['view'],
                    'task' => ['view', 'update'],
                    'report' => ['view'],
                    'client' => [],
                    'metric' => [],
                    'user' => ['view'],
                    'mcp_connection' => [],
                    'briefing' => ['view'],
                ],
            ],
            [
                'name' => 'Designer',
                'slug' => 'designer',
                'description' => 'View design tasks, upload assets',
                'is_system' => true,
                'permissions' => [
                    'project' => ['view'],
                    'task' => ['view', 'update'],
                    'report' => ['view'],
                    'client' => [],
                    'metric' => [],
                    'user' => ['view'],
                    'mcp_connection' => [],
                    'briefing' => ['view'],
                ],
            ],
            [
                'name' => 'Client',
                'slug' => 'client',
                'description' => 'View-only access to their project reports',
                'is_system' => true,
                'permissions' => [
                    'project' => ['view'],
                    'task' => ['view'],
                    'report' => ['view'],
                    'client' => ['view'],
                    'metric' => ['view'],
                    'user' => [],
                    'mcp_connection' => [],
                    'briefing' => [],
                ],
            ],
        ];

        $roles = [];
        foreach ($defaultRoles as $roleData) {
            $roles[$roleData['slug']] = Role::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'slug' => $roleData['slug'],
                ],
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'is_system' => $roleData['is_system'],
                    'permissions' => $roleData['permissions'],
                ]
            );
        }

        return $roles;
    }
}
