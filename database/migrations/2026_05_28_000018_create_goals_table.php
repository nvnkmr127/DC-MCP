<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('owner_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('period', ['q1', 'q2', 'q3', 'q4', 'annual']);
            $table->unsignedSmallInteger('year');
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('active');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->jsonb('key_results')->default('[]');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['organization_id', 'period', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
