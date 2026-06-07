<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Abort with 403 if the given resource does not belong to the
     * authenticated user's organization.
     *
     * Replaces the 30+ duplicated one-liners:
     *   abort_if($resource->organization_id !== $request->user()->organization_id, 403);
     */
    protected function authorizeOrg(mixed $resource): void
    {
        abort_if(
            $resource->organization_id !== auth()->user()?->organization_id,
            403
        );
    }

    protected function authorizeProjectId(string $projectId): void
    {
        $orgId = \App\Modules\ProjectManagement\Models\Project::withoutGlobalScope(\App\Modules\Auth\Scopes\OrganizationScope::class)
            ->where('id', $projectId)
            ->value('organization_id');

        abort_unless($orgId, 404);
        abort_if($orgId !== auth()->user()?->organization_id, 403);
    }
}
