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
        Schema::create('users', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->string('name');
            $blueprint->string('email')->unique();
            $blueprint->string('password');
            $blueprint->string('avatar_url')->nullable();
            $blueprint->string('phone')->nullable();
            $blueprint->string('timezone')->default('Asia/Kolkata');
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamp('email_verified_at')->nullable();
            $blueprint->timestamp('last_active_at')->nullable();
            $blueprint->jsonb('preferences')->nullable();
            $blueprint->rememberToken();
            $blueprint->timestamps();
            $blueprint->softDeletes();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->index('organization_id');
            $blueprint->index('is_active');
            $blueprint->index('last_active_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $blueprint) {
            $blueprint->string('email')->primary();
            $blueprint->string('token');
            $blueprint->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $blueprint) {
            $blueprint->string('id')->primary();
            $blueprint->foreignId('user_id')->nullable()->index();
            $blueprint->string('ip_address', 45)->nullable();
            $blueprint->text('user_agent')->nullable();
            $blueprint->longText('payload');
            $blueprint->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
