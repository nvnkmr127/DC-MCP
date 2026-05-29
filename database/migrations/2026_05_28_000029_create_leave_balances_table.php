<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('user_id');
            $table->smallInteger('year');
            $table->decimal('earned_total', 4, 1)->default(15);
            $table->decimal('earned_used', 4, 1)->default(0);
            $table->decimal('sick_total', 4, 1)->default(12);
            $table->decimal('sick_used', 4, 1)->default(0);
            $table->decimal('casual_total', 4, 1)->default(6);
            $table->decimal('casual_used', 4, 1)->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
