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
        Schema::create('roles', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->string('name');
            $blueprint->string('slug');
            $blueprint->text('description')->nullable();
            $blueprint->boolean('is_system')->default(false);
            $blueprint->jsonb('permissions')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->unique(['organization_id', 'slug']);
            $blueprint->index('organization_id');
        });

        Schema::create('role_user', function (Blueprint $blueprint) {
            $blueprint->uuid('user_id');
            $blueprint->uuid('role_id');
            $blueprint->uuid('organization_id');
            $blueprint->uuid('assigned_by')->nullable();
            $blueprint->timestamp('assigned_at')->useCurrent();

            $blueprint->primary(['user_id', 'role_id']);
            $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $blueprint->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');

            $blueprint->index('user_id');
            $blueprint->index('role_id');
            $blueprint->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
