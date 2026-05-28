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
        Schema::create('task_assignments', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('task_id');
            $blueprint->uuid('user_id');
            $blueprint->uuid('assigned_by')->nullable();
            $blueprint->timestamp('assigned_at')->useCurrent();
            $blueprint->timestamp('unassigned_at')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $blueprint->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');

            $blueprint->index('task_id');
            $blueprint->index('user_id');
        });

        Schema::create('task_logs', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('task_id');
            $blueprint->uuid('user_id')->nullable();
            $blueprint->enum('action', [
                'created', 'status_changed', 'assigned', 'commented',
                'time_logged', 'attachment_added', 'sla_warning', 'sla_breached'
            ]);
            $blueprint->jsonb('old_value')->nullable();
            $blueprint->jsonb('new_value')->nullable();
            $blueprint->text('comment')->nullable();
            $blueprint->timestamp('logged_at')->useCurrent();

            $blueprint->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $blueprint->index('task_id');
            $blueprint->index('user_id');
            $blueprint->index('logged_at');
        });

        Schema::create('time_entries', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->uuid('task_id');
            $blueprint->uuid('user_id');
            $blueprint->uuid('project_id');
            $blueprint->string('description')->nullable();
            $blueprint->decimal('hours', 5, 2);
            $blueprint->date('logged_date');
            $blueprint->boolean('is_billable')->default(true);
            $blueprint->timestamps();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $blueprint->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $blueprint->index('organization_id');
            $blueprint->index('task_id');
            $blueprint->index('user_id');
            $blueprint->index('project_id');
            $blueprint->index('logged_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('task_logs');
        Schema::dropIfExists('task_assignments');
    }
};
