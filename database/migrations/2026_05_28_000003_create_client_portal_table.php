<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Client contacts who can log into the portal
        Schema::create('client_portal_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
            $table->string('magic_token', 64)->nullable()->unique();
            $table->timestamp('magic_token_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->jsonb('permissions')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->unique(['organization_id', 'client_id', 'email']);
            $table->index(['organization_id', 'client_id']);
        });

        // Items CEO has explicitly shared with a client
        Schema::create('client_portal_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->string('shareable_type');
            $table->uuid('shareable_id');
            $table->uuid('shared_by');
            $table->timestamp('shared_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->index(['organization_id', 'client_id', 'is_active']);
            $table->index(['shareable_type', 'shareable_id']);
        });

        // Requests submitted by clients through their portal
        Schema::create('client_portal_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->uuid('portal_user_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['new_request', 'feedback', 'bug', 'question'])->default('new_request');
            $table->enum('status', ['open', 'in_review', 'actioned', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->uuid('task_id')->nullable();
            $table->uuid('actioned_by')->nullable();
            $table->timestamp('actioned_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('portal_user_id')->references('id')->on('client_portal_users')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_requests');
        Schema::dropIfExists('client_portal_shares');
        Schema::dropIfExists('client_portal_users');
    }
};
