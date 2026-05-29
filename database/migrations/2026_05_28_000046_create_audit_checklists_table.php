<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->string('title');
            $table->enum('type', ['seo', 'social', 'ads', 'content', 'website', 'general'])->default('general');
            $table->jsonb('items')->default('[]');
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_checklists');
    }
};
