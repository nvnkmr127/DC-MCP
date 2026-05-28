<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->uuid('task_id')->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->enum('type', ['social_post', 'blog', 'ad_campaign'])->default('social_post');
            $table->enum('platform', [
                'instagram', 'facebook', 'twitter', 'linkedin',
                'youtube', 'website', 'google_ads', 'meta_ads', 'email',
            ])->nullable();
            $table->enum('status', [
                'idea', 'in_progress', 'in_review', 'approved',
                'scheduled', 'published', 'cancelled',
            ])->default('idea');
            $table->date('due_date')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->jsonb('tags')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
