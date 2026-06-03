<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: extend the enum to include 'planning' alongside 'draft'.
        // SQLite ignores enum constraints so no action needed there.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TYPE projects_status_enum ADD VALUE IF NOT EXISTS 'planning'");
        }
    }

    public function down(): void
    {
        // PostgreSQL does not support removing enum values without recreating the type.
        // Intentionally left as no-op — removing 'planning' would break existing rows.
    }
};
