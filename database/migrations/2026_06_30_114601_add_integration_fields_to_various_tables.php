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
        Schema::table('audit_checklists', function (Blueprint $table) {
            $table->uuid('project_id')->nullable()->after('client_id');
            $table->uuid('asset_approval_id')->nullable()->after('project_id');
            // Adding basic foreign key constraints isn't strictly necessary for sqlite/UUID if we just index them, but it's good practice.
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('asset_approval_id')->references('id')->on('asset_approvals')->nullOnDelete();
        });

        Schema::table('one_on_one_notes', function (Blueprint $table) {
            $table->uuid('performance_review_id')->nullable()->after('member_id');
            $table->string('template_name')->nullable()->after('performance_review_id');
            
            $table->foreign('performance_review_id')->references('id')->on('performance_reviews')->nullOnDelete();
        });

        Schema::table('content_items', function (Blueprint $table) {
            $table->json('analytics_data')->nullable()->after('meta');
        });

        Schema::table('feature_flags', function (Blueprint $table) {
            $table->text('description')->nullable()->after('feature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_checklists', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['asset_approval_id']);
            $table->dropColumn(['project_id', 'asset_approval_id']);
        });

        Schema::table('one_on_one_notes', function (Blueprint $table) {
            $table->dropForeign(['performance_review_id']);
            $table->dropColumn(['performance_review_id', 'template_name']);
        });

        Schema::table('content_items', function (Blueprint $table) {
            $table->dropColumn('analytics_data');
        });

        Schema::table('feature_flags', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
