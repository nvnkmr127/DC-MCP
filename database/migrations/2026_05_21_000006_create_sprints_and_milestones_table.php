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
        Schema::create('sprints', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('project_id');
            $blueprint->string('name');
            $blueprint->text('goal')->nullable();
            $blueprint->enum('status', ['planning', 'active', 'completed', 'cancelled'])->default('planning');
            $blueprint->date('start_date')->nullable();
            $blueprint->date('end_date')->nullable();
            $blueprint->integer('velocity_planned')->nullable();
            $blueprint->integer('velocity_actual')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $blueprint->index('project_id');
            $blueprint->index('status');
        });

        Schema::create('milestones', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('project_id');
            $blueprint->uuid('sprint_id')->nullable();
            $blueprint->string('name');
            $blueprint->text('description')->nullable();
            $blueprint->date('due_date');
            $blueprint->timestamp('completed_at')->nullable();
            $blueprint->enum('status', ['pending', 'in_progress', 'completed', 'missed'])->default('pending');
            $blueprint->timestamps();

            $blueprint->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $blueprint->foreign('sprint_id')->references('id')->on('sprints')->onDelete('set null');

            $blueprint->index('project_id');
            $blueprint->index('sprint_id');
            $blueprint->index('status');
            $blueprint->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('sprints');
    }
};
