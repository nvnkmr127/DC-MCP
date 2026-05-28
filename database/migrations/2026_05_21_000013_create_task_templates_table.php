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
        Schema::create('task_templates', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->enum('project_type', [
                'seo', 'social_media', 'performance_ads', 'web_dev', 'app_dev',
                'content', 'brand', 'whatsapp', 'email_marketing', 'ecommerce'
            ]);
            $blueprint->string('title');
            $blueprint->text('description')->nullable();
            $blueprint->enum('type', [
                'feature', 'bug', 'content', 'design', 'research', 'review',
                'meeting', 'report', 'campaign_setup', 'ad_creative', 'seo_audit',
                'email_sequence', 'other'
            ])->default('other');
            $blueprint->enum('role_required', [
                'ceo', 'project_manager', 'analyst', 'marketer', 'developer', 'designer', 'copywriter'
            ]);
            $blueprint->decimal('estimated_hours', 5, 2)->default(0.00);
            $blueprint->integer('sla_hours')->nullable();
            $blueprint->integer('sort_order')->default(0);
            $blueprint->jsonb('depends_on')->nullable(); // array of sort_order integers
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();

            $blueprint->index('project_type');
            $blueprint->index('role_required');
            $blueprint->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};
