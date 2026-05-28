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
        Schema::create('reports', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->uuid('project_id')->nullable();
            $blueprint->uuid('client_id')->nullable();
            $blueprint->string('title');
            $blueprint->enum('type', ['weekly', 'monthly', 'campaign', 'sprint', 'custom', 'client']);
            $blueprint->enum('status', ['draft', 'generating', 'ready', 'sent', 'archived'])->default('draft');
            $blueprint->enum('template', ['seo_report', 'ads_report', 'social_report', 'sprint_report', 'full_service']);
            $blueprint->date('date_from');
            $blueprint->date('date_to');
            $blueprint->jsonb('config')->nullable();
            $blueprint->string('generated_file_path')->nullable();
            $blueprint->timestamp('generated_at')->nullable();
            $blueprint->timestamp('sent_at')->nullable();
            $blueprint->uuid('generated_by')->nullable();
            $blueprint->jsonb('recipients')->nullable(); // array of email strings
            $blueprint->timestamps();
            $blueprint->softDeletes();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $blueprint->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $blueprint->foreign('generated_by')->references('id')->on('users')->onDelete('set null');

            $blueprint->index('organization_id');
            $blueprint->index('project_id');
            $blueprint->index('client_id');
            $blueprint->index('status');
        });

        Schema::create('daily_briefings', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->uuid('user_id');
            $blueprint->date('date');
            $blueprint->enum('status', ['pending', 'generating', 'ready', 'delivered', 'failed'])->default('pending');
            $blueprint->jsonb('digest_raw')->nullable();
            $blueprint->text('digest_html')->nullable();
            $blueprint->text('digest_text')->nullable();
            $blueprint->string('ai_model')->nullable();
            $blueprint->integer('ai_tokens_used')->nullable();
            $blueprint->jsonb('delivered_via')->nullable(); // array e.g. ["email", "zoho_cliq"]
            $blueprint->timestamp('delivered_at')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $blueprint->index('organization_id');
            $blueprint->index('user_id');
            $blueprint->index('date');
            $blueprint->index('status');
        });

        Schema::create('dashboard_configs', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->uuid('user_id');
            $blueprint->enum('role', ['ceo', 'project_manager', 'analyst', 'marketer', 'developer']);
            $blueprint->string('name');
            $blueprint->boolean('is_default')->default(false);
            $blueprint->jsonb('layout')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $blueprint->index('organization_id');
            $blueprint->index('user_id');
            $blueprint->index('role');
        });

        Schema::create('notifications_log', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->uuid('user_id');
            $blueprint->enum('type', [
                'task_assigned', 'sla_warning', 'sla_breached', 'report_ready',
                'briefing_ready', 'campaign_alert', 'mention', 'system'
            ]);
            $blueprint->enum('channel', ['email', 'zoho_cliq', 'whatsapp', 'in_app', 'push']);
            $blueprint->string('title');
            $blueprint->text('body');
            $blueprint->jsonb('data')->nullable(); // contextual data
            $blueprint->enum('status', ['pending', 'sent', 'delivered', 'failed', 'read'])->default('pending');
            $blueprint->timestamp('read_at')->nullable();
            $blueprint->timestamp('sent_at')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $blueprint->index('organization_id');
            $blueprint->index('user_id');
            $blueprint->index('status');
            $blueprint->index('type');
            $blueprint->index('channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
        Schema::dropIfExists('dashboard_configs');
        Schema::dropIfExists('daily_briefings');
        Schema::dropIfExists('reports');
    }
};
