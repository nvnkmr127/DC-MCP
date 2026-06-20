<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dashboard_configs', function (Blueprint $table) {
            $table->string('share_token')->unique()->nullable()->after('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashboard_configs', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
