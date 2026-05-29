<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->uuid('reported_by')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->uuid('task_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['bug', 'enhancement', 'question', 'feedback'])->default('bug');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->enum('source', ['internal', 'client_portal', 'email', 'call'])->default('internal');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
