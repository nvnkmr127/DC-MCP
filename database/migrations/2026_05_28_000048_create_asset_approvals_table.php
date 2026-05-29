<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->uuid('submitted_by');
            $table->uuid('reviewed_by')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['social_post', 'ad_creative', 'blog', 'video', 'email', 'other'])->default('other');
            $table->string('asset_url')->nullable();
            $table->text('feedback')->nullable();
            $table->integer('version')->default(1);
            $table->enum('status', ['pending', 'approved', 'revision_requested', 'rejected'])->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_approvals');
    }
};
