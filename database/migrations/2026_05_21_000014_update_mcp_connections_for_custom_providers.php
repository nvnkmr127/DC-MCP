<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: change enum → varchar using USING cast
        if (config('database.default') !== 'sqlite' && DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE mcp_connections ALTER COLUMN provider TYPE VARCHAR(60) USING provider::VARCHAR');
        }

        Schema::table('mcp_connections', function (Blueprint $table) {
            if (!Schema::hasColumn('mcp_connections', 'label')) {
                $table->string('label', 120)->nullable()->after('name');
            }
            if (!Schema::hasColumn('mcp_connections', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mcp_connections', function (Blueprint $table) {
            $table->dropColumn(['label', 'is_active']);
        });

        // Recreate the enum (only valid existing values will cast cleanly)
        if (config('database.default') !== 'sqlite' && DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE mcp_connections ALTER COLUMN provider TYPE VARCHAR(60)");
        }
    }
};
