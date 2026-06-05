<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_status_check");
            DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_status_check CHECK (status::text = ANY (ARRAY['draft'::text, 'active'::text, 'on_hold'::text, 'completed'::text, 'cancelled'::text, 'planning'::text]))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_status_check");
            DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_status_check CHECK (status::text = ANY (ARRAY['draft'::text, 'active'::text, 'on_hold'::text, 'completed'::text, 'cancelled'::text]))");
        }
    }
};
