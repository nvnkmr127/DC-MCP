<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('reviewer_id');
            $table->uuid('reviewee_id');
            $table->enum('period', ['q1', 'q2', 'q3', 'q4', 'annual']);
            $table->smallInteger('year');
            $table->tinyInteger('overall_rating')->nullable();
            $table->tinyInteger('technical_rating')->nullable();
            $table->tinyInteger('communication_rating')->nullable();
            $table->tinyInteger('teamwork_rating')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('goals_next')->nullable();
            $table->enum('status', ['draft', 'submitted', 'acknowledged'])->default('draft');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
