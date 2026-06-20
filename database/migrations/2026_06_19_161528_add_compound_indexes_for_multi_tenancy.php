<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tasks Table
        Schema::table('tasks', function (Blueprint $table) {
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->dropIndex(['organization_id']);
                $table->dropIndex(['status']);
            }
            // Add compound index for filtering
            $table->index(['organization_id', 'deleted_at', 'status'], 'tasks_org_del_status_idx');
        });

        // 2. Projects Table
        Schema::table('projects', function (Blueprint $table) {
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->dropIndex(['organization_id']);
            }
            $table->index(['organization_id', 'deleted_at', 'status'], 'projects_org_del_status_idx');
        });

        // 3. Clients Table
        Schema::table('clients', function (Blueprint $table) {
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->dropIndex(['organization_id']);
            }
            $table->index(['organization_id', 'deleted_at', 'status'], 'clients_org_del_status_idx');
        });

        // 4. Issues Table
        Schema::table('issues', function (Blueprint $table) {
            $table->index(['organization_id', 'deleted_at', 'status'], 'issues_org_del_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_org_del_status_idx');
            $table->index('organization_id');
            $table->index('status');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_org_del_status_idx');
            $table->index('organization_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_org_del_status_idx');
            $table->index('organization_id');
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex('issues_org_del_status_idx');
            $table->index('organization_id');
        });
    }
};
