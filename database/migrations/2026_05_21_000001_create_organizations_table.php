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
        Schema::create('organizations', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->string('name');
            $blueprint->string('slug')->unique();
            $blueprint->string('logo_url')->nullable();
            $blueprint->string('website')->nullable();
            $blueprint->enum('plan', ['free', 'starter', 'pro', 'enterprise'])->default('free');
            $blueprint->jsonb('settings')->nullable();
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();
            $blueprint->softDeletes();

            $blueprint->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
