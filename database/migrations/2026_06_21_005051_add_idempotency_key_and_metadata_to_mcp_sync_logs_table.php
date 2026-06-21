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
            $table->string('idempotency_key')->nullable()->index()->after('user_id');
            // Ensure metadata column exists (it should based on schema dump, but just in case we need JSON structure updates, we rely on Eloquent casts)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mcp_sync_logs', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};
