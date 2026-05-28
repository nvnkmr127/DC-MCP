<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedTinyInteger('health_score')->nullable()->after('status');
            $table->enum('health_status', ['green', 'yellow', 'red'])->nullable()->after('health_score');
            $table->jsonb('health_breakdown')->nullable()->after('health_status');
            $table->timestamp('health_computed_at')->nullable()->after('health_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['health_score', 'health_status', 'health_breakdown', 'health_computed_at']);
        });
    }
};
