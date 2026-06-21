<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use ZipArchive;
use App\Modules\Notifications\Services\NotificationService;

class ExportOrganizationDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, \App\Shared\Traits\RateLimitsTenantJobs;

    public $timeout = 600; // Allow 10 minutes

    protected Organization $organization;
    protected User $requester;

    /**
     * Create a new job instance.
     */
    public function __construct(Organization $organization, User $requester)
    {
        $this->organization = $organization;
        $this->requester = $requester;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        // 1. Identify all tables with organization_id
        $driver = DB::connection()->getDriverName();
        $tables = [];

        if ($driver === 'pgsql') {
            $results = DB::select("
                SELECT table_name 
                FROM information_schema.columns 
                WHERE column_name = 'organization_id' 
                AND table_schema = 'public'
            ");
            $tables = array_map(fn($r) => $r->table_name, $results);
        } elseif ($driver === 'sqlite') {
            $results = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            foreach ($results as $r) {
                $columns = DB::select("PRAGMA table_info({$r->name})");
                foreach ($columns as $c) {
                    if ($c->name === 'organization_id') {
                        $tables[] = $r->name;
                        break;
                    }
                }
            }
        }

        // 2. Prepare export directory
        $exportDir = storage_path('app/exports/tmp_' . $this->organization->id);
        if (!File::exists($exportDir)) {
            File::makeDirectory($exportDir, 0755, true);
        }

        // 3. Dump data per table
        foreach ($tables as $table) {
            $filePath = $exportDir . '/' . $table . '.json';
            $fileHandle = fopen($filePath, 'w');
            fwrite($fileHandle, "[\n");
            
            $first = true;
            DB::table($table)->where('organization_id', $this->organization->id)->orderBy('created_at')->chunk(500, function ($records) use ($fileHandle, &$first) {
                foreach ($records as $record) {
                    if (!$first) {
                        fwrite($fileHandle, ",\n");
                    }
                    fwrite($fileHandle, json_encode($record));
                    $first = false;
                }
            });
            
            fwrite($fileHandle, "\n]");
            fclose($fileHandle);
        }

        // 4. Create ZIP archive
        $zipFileName = $this->organization->id . '_export.zip';
        $zipPath = storage_path('app/exports/' . $zipFileName);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = File::files($exportDir);
            foreach ($files as $file) {
                $zip->addFile($file->getPathname(), $file->getFilename());
            }
            $zip->close();
        }

        // 5. Cleanup temp directory
        File::deleteDirectory($exportDir);

        // 6. Notify requester
        $downloadUrl = url('/settings/organization/export/download/' . $zipFileName);

        $notificationService->sendNotification(
            $this->requester,
            'system_alert',
            'in_app',
            "Organization Export Ready",
            "Your organization data export has been completed successfully.",
            null,
            $downloadUrl
        );
    }
}
