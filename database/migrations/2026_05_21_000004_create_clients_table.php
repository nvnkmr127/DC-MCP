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
        Schema::create('clients', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->string('name');
            $blueprint->string('email');
            $blueprint->string('phone')->nullable();
            $blueprint->string('company');
            $blueprint->string('website')->nullable();
            $blueprint->string('industry')->nullable();
            $blueprint->enum('tier', ['basic', 'standard', 'premium', 'enterprise'])->default('basic');
            $blueprint->enum('status', ['active', 'paused', 'churned', 'prospect'])->default('prospect');
            $blueprint->text('notes')->nullable();
            $blueprint->jsonb('metadata')->nullable();
            $blueprint->uuid('assigned_to')->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');

            $blueprint->index('organization_id');
            $blueprint->index('status');
            $blueprint->index('tier');
            $blueprint->index('assigned_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
