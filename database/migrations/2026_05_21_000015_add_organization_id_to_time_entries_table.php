<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $newlyAdded = false;
        
        if (!Schema::hasColumn('time_entries', 'organization_id')) {
            Schema::table('time_entries', function (Blueprint $table) {
                $table->uuid('organization_id')->nullable()->after('id');
            });
            $newlyAdded = true;
        }

        if ($newlyAdded) {
            // Populate organization_id based on task's organization_id
            if (config('database.default') === 'sqlite' || DB::getDriverName() === 'sqlite') {
                // SQLite update syntax
                DB::statement("
                    UPDATE time_entries
                    SET organization_id = (
                        SELECT organization_id FROM tasks WHERE tasks.id = time_entries.task_id
                    )
                    WHERE organization_id IS NULL
                ");
            } else {
                // PostgreSQL update syntax
                DB::statement("
                    UPDATE time_entries
                    SET organization_id = tasks.organization_id
                    FROM tasks
                    WHERE time_entries.task_id = tasks.id
                    AND time_entries.organization_id IS NULL
                ");
            }

            // Backfill a default organization if there are still any orphan rows, just to prevent constraint failure
            $defaultOrg = DB::table('organizations')->first();
            if ($defaultOrg) {
                DB::table('time_entries')
                    ->whereNull('organization_id')
                    ->update(['organization_id' => $defaultOrg->id]);
            }

            Schema::table('time_entries', function (Blueprint $table) {
                // Make the column not-nullable and add foreign key/index
                $table->uuid('organization_id')->nullable(false)->change();
                
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
                $table->index('organization_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('time_entries', 'organization_id')) {
            Schema::table('time_entries', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }
    }
};
