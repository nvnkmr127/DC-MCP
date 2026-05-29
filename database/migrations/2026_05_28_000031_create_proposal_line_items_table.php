<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_line_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('proposal_id');
            $table->string('service');
            $table->text('description')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('quantity', 6, 2)->default(1);
            $table->enum('frequency', ['one_time', 'monthly', 'quarterly', 'annual'])->default('one_time');
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_line_items');
    }
};
