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
        Schema::create('tasks', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->uuid('project_id');
            $blueprint->uuid('sprint_id')->nullable();
            $blueprint->uuid('milestone_id')->nullable();
            $blueprint->uuid('parent_task_id')->nullable();
            $blueprint->string('title');
            $blueprint->text('description')->nullable();
            $blueprint->enum('type', [
                'feature', 'bug', 'content', 'design', 'research', 'review',
                'meeting', 'report', 'campaign_setup', 'ad_creative', 'seo_audit',
                'email_sequence', 'other'
            ])->default('other');
            $blueprint->enum('status', [
                'backlog', 'todo', 'in_progress', 'in_review', 'blocked', 'done', 'cancelled'
            ])->default('backlog');
            $blueprint->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $blueprint->uuid('assigned_to')->nullable();
            $blueprint->uuid('created_by')->nullable();
            $blueprint->enum('role_required', [
                'ceo', 'project_manager', 'analyst', 'marketer', 'developer', 'designer', 'copywriter'
            ])->nullable();
            $blueprint->date('due_date')->nullable();
            $blueprint->timestamp('completed_at')->nullable();
            $blueprint->decimal('estimated_hours', 5, 2)->default(0.00);
            $blueprint->decimal('actual_hours', 5, 2)->default(0.00);
            $blueprint->integer('sla_hours')->nullable();
            $blueprint->timestamp('sla_breached_at')->nullable();
            $blueprint->jsonb('tags')->nullable();
            $blueprint->jsonb('meta')->nullable();
            $blueprint->integer('sort_order')->default(0);
            $blueprint->timestamps();
            $blueprint->softDeletes();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $blueprint->foreign('sprint_id')->references('id')->on('sprints')->onDelete('set null');
            $blueprint->foreign('milestone_id')->references('id')->on('milestones')->onDelete('set null');
            $blueprint->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $blueprint->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $blueprint->index('organization_id');
            $blueprint->index('project_id');
            $blueprint->index('sprint_id');
            $blueprint->index('milestone_id');
            $blueprint->index('parent_task_id');
            $blueprint->index('assigned_to');
            $blueprint->index('status');
            $blueprint->index('priority');
            $blueprint->index('due_date');
            $blueprint->index('sla_breached_at');
        });

        Schema::table('tasks', function (Blueprint $blueprint) {
            $blueprint->foreign('parent_task_id')->references('id')->on('tasks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
