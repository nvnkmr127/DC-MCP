<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_sows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->uuid('retainer_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['draft', 'active', 'expired'])->default('draft');
            $table->uuid('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->index(['organization_id', 'client_id']);
        });

        Schema::create('sow_deliverables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sow_id');
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->string('title');
            $table->enum('service_type', ['seo', 'ads', 'social', 'content', 'design', 'dev', 'email', 'other'])->default('other');
            $table->enum('frequency', ['one_time', 'weekly', 'monthly', 'quarterly'])->default('monthly');
            $table->unsignedSmallInteger('quantity_per_period')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('sow_id')->references('id')->on('client_sows')->onDelete('cascade');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sow_deliverables');
        Schema::dropIfExists('client_sows');
    }
};
