<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeOrganizationDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, \App\Shared\Traits\RateLimitsTenantJobs;

    public $timeout = 600; // Allow 10 minutes

    protected $organizationId;

    /**
     * Create a new job instance.
     * We pass the ID rather than the model instance because we will delete it.
     */
    public function __construct(string $organizationId)
    {
        $this->organizationId = $organizationId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Delete physical files from Attachments
        if (class_exists(Attachment::class)) {
            foreach (Attachment::withoutGlobalScopes()->where('organization_id', $this->organizationId)->cursor() as $attachment) {
                if ($attachment->storage_path && $attachment->storage_disk) {
                    Storage::disk($attachment->storage_disk)->delete($attachment->storage_path);
                }
            }
        }

        // 2. Delete user avatars
        foreach (User::withoutGlobalScopes()->where('organization_id', $this->organizationId)->whereNotNull('avatar_url')->cursor() as $user) {
            // Path is usually like /storage/avatars/...
            if (str_starts_with($user->avatar_url, '/storage/')) {
                $path = str_replace('/storage/', '', $user->avatar_url);
                Storage::disk('public')->delete($path);
            }
        }

        // 3. Delete cached GDPR export files
        $exportFileName = $this->organizationId . '_export.zip';
        $exportPath = storage_path('app/exports/' . $exportFileName);
        if (file_exists($exportPath)) {
            unlink($exportPath);
        }

        // 4. Force Delete the Organization.
        // Due to `ON DELETE CASCADE` foreign keys on all tenant-aware tables, 
        // this operation will instantly hard-delete all rows belonging to the organization.
        $organization = Organization::withoutGlobalScopes()->find($this->organizationId);
        
        if ($organization) {
            $organization->forceDelete();
        } else {
            // Fallback raw query if soft deleted or not found through model
            DB::table('organizations')->where('id', $this->organizationId)->delete();
        }
    }
}
