<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('service_name');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->enum('unit', ['hour', 'post', 'campaign', 'month', 'project', 'word', 'video', 'other']);
            $table->decimal('rate', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_cards');
    }
};
