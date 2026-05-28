<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('briefing_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->uuid('project_id')->nullable();
            $table->uuid('client_id')->nullable();
            $table->string('role_required')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('estimated_hours')->nullable();
            $table->string('suggested_by')->default('ai');
            $table->enum('status', ['pending', 'approved', 'rejected', 'modified'])->default('pending');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('task_id')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('briefing_id')->references('id')->on('daily_briefings')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'created_at']);
            $table->index('briefing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_suggestions');
    }
};
