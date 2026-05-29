<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_triggers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('trigger_event', ['task_completed', 'invoice_sent', 'project_created', 'client_added', 'retainer_renewed', 'proposal_accepted'])->default('task_completed');
            $table->jsonb('conditions')->default('{}');
            $table->enum('action_type', ['send_notification', 'create_task', 'send_email', 'update_status'])->default('send_notification');
            $table->jsonb('action_config')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->integer('run_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_triggers');
    }
};
