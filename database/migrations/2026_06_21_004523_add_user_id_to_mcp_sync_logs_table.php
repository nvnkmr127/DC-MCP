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
        Schema::table('mcp_sync_logs', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->after('mcp_connection_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mcp_sync_logs', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
