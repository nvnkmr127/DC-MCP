<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->uuid('author_id')->nullable();
            $table->string('month_year', 7);
            $table->enum('status', ['draft', 'sent'])->default('draft');
            $table->text('highlights')->nullable();
            $table->text('challenges')->nullable();
            $table->jsonb('metrics')->default('{}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_reports');
    }
};
