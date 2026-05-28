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
        Schema::create('attachments', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->string('attachable_type');
            $blueprint->uuid('attachable_id');
            $blueprint->uuid('organization_id');
            $blueprint->string('filename');
            $blueprint->string('original_name');
            $blueprint->string('mime_type')->nullable();
            $blueprint->bigInteger('size_bytes');
            $blueprint->string('storage_path');
            $blueprint->string('storage_disk')->default('s3');
            $blueprint->uuid('uploaded_by')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');

            $blueprint->index(['attachable_type', 'attachable_id']);
            $blueprint->index('organization_id');
            $blueprint->index('uploaded_by');
        });

        Schema::create('comments', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->string('commentable_type');
            $blueprint->uuid('commentable_id');
            $blueprint->uuid('user_id');
            $blueprint->uuid('parent_id')->nullable();
            $blueprint->text('body');
            $blueprint->jsonb('mentions')->nullable();
            $blueprint->boolean('is_internal')->default(false);
            $blueprint->timestamps();
            $blueprint->softDeletes();

            $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $blueprint->index(['commentable_type', 'commentable_id']);
            $blueprint->index('user_id');
            $blueprint->index('parent_id');
        });

        Schema::table('comments', function (Blueprint $blueprint) {
            $blueprint->foreign('parent_id')->references('id')->on('comments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('comments');
        Schema::dropIfExists('attachments');
    }
};
