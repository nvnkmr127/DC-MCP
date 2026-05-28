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
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('client_id')->nullable();
            $table->string('title');
            $table->enum('type', ['weekly', 'monthly', 'campaign', 'sprint', 'custom', 'client']);
            $table->enum('template', ['seo_report', 'ads_report', 'social_report', 'sprint_report', 'full_service']);
            $table->enum('frequency', ['weekly', 'monthly']);
            $table->integer('send_day'); // 1-7 for day of week (Monday = 1), 1-31 for day of month
            $table->jsonb('config')->nullable();
            $table->jsonb('recipients'); // array of emails
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index('organization_id');
            $table->index('project_id');
            $table->index('client_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
