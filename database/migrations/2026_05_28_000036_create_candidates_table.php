<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('job_opening_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('resume_url')->nullable();
            $table->enum('source', ['referral', 'job_portal', 'linkedin', 'direct', 'other'])->default('direct');
            $table->enum('stage', ['applied', 'screening', 'interview_1', 'interview_2', 'offer', 'hired', 'rejected'])->default('applied');
            $table->tinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->string('rejected_reason')->nullable();
            $table->date('hired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
