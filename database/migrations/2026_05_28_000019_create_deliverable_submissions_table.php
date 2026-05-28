<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverable_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('sow_deliverable_id');
            $table->uuid('submitted_by');
            $table->uuid('reviewer_id')->nullable();
            $table->string('file_url')->nullable();
            $table->string('external_link')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['submitted', 'approved', 'revision_requested'])->default('submitted');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->unsignedTinyInteger('revision_number')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('sow_deliverable_id')->references('id')->on('sow_deliverables')->onDelete('cascade');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['organization_id', 'sow_deliverable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverable_submissions');
    }
};
