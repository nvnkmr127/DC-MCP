<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't enforce enums — just update existing rows
            DB::table('clients')->where('status', 'paused')->update(['status' => 'inactive']);
            return;
        }

        // PostgreSQL: rename the enum value
        // 1. Rename existing 'paused' rows first so no data is lost
        DB::table('clients')->where('status', 'paused')->update(['status' => 'inactive']);

        // 2. Rebuild the CHECK constraint with 'inactive' replacing 'paused'
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_status_check");
            DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_status_check CHECK (status::text = ANY (ARRAY['active'::text, 'inactive'::text, 'prospect'::text, 'churned'::text]))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::table('clients')->where('status', 'inactive')->update(['status' => 'paused']);
            return;
        }

        DB::table('clients')->where('status', 'inactive')->update(['status' => 'paused']);
        
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_status_check");
            DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_status_check CHECK (status::text = ANY (ARRAY['active'::text, 'paused'::text, 'prospect'::text, 'churned'::text]))");
        }
    }
};
