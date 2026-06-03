<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DB enum is: draft | generating | ready | sent | archived
        // GenerateReportJob::failed() sets status = 'failed' which the DB rejects.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TYPE reports_status_enum ADD VALUE IF NOT EXISTS 'failed'");
        }
    }

    public function down(): void
    {
        // PostgreSQL does not support removing enum values without recreating the type.
    }
};
