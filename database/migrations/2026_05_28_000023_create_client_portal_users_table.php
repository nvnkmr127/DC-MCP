<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portal_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->string('name');
            $table->string('email');
            $table->boolean('is_active')->default(true);
            $table->string('invite_token', 80)->nullable()->unique();
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('invite_sent_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'client_id']);
            $table->index('invite_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_users');
    }
};
