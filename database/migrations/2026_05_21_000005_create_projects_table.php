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
        Schema::create('projects', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->uuid('client_id');
            $blueprint->string('name');
            $blueprint->string('slug');
            $blueprint->text('description')->nullable();
            $blueprint->enum('type', [
                'seo', 'social_media', 'performance_ads', 'web_dev', 'app_dev',
                'content', 'brand', 'whatsapp', 'email_marketing', 'ecommerce'
            ]);
            $blueprint->enum('status', ['draft', 'active', 'on_hold', 'completed', 'cancelled'])->default('draft');
            $blueprint->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $blueprint->date('start_date')->nullable();
            $blueprint->date('end_date')->nullable();
            $blueprint->date('actual_end_date')->nullable();
            $blueprint->decimal('budget', 12, 2)->default(0.00);
            $blueprint->decimal('budget_used', 12, 2)->default(0.00);
            $blueprint->uuid('project_manager_id')->nullable();
            $blueprint->jsonb('settings')->nullable();
            $blueprint->jsonb('tags')->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $blueprint->foreign('project_manager_id')->references('id')->on('users')->onDelete('set null');

            $blueprint->unique(['organization_id', 'slug']);
            $blueprint->index('organization_id');
            $blueprint->index('client_id');
            $blueprint->index('project_manager_id');
            $blueprint->index('status');
            $blueprint->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
