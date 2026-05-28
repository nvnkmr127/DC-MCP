<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_task_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('task');
            $table->string('role_required')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly'])->default('monthly');
            $table->unsignedTinyInteger('frequency_day')->nullable();
            $table->unsignedSmallInteger('sla_hours')->nullable();
            $table->decimal('estimated_hours', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_spawned_at')->nullable();
            $table->timestamp('next_spawn_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['organization_id', 'is_active', 'next_spawn_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_task_rules');
    }
};
