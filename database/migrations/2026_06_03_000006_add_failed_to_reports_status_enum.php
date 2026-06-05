<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DB enum is: draft | generating | ready | sent | archived
        // GenerateReportJob::failed() sets status = 'failed' which the DB rejects.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_status_check");
            DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check CHECK (status::text = ANY (ARRAY['draft'::text, 'generating'::text, 'ready'::text, 'sent'::text, 'archived'::text, 'failed'::text]))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_status_check");
            DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check CHECK (status::text = ANY (ARRAY['draft'::text, 'generating'::text, 'ready'::text, 'sent'::text, 'archived'::text]))");
        }
    }
};
