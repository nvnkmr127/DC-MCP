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

        // 2. Rebuild the enum type with 'inactive' replacing 'paused'
        DB::statement("ALTER TYPE clients_status_enum RENAME TO clients_status_enum_old");
        DB::statement("CREATE TYPE clients_status_enum AS ENUM ('active', 'inactive', 'prospect', 'churned')");
        DB::statement("ALTER TABLE clients ALTER COLUMN status TYPE clients_status_enum USING status::text::clients_status_enum");
        DB::statement("DROP TYPE clients_status_enum_old");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::table('clients')->where('status', 'inactive')->update(['status' => 'paused']);
            return;
        }

        DB::table('clients')->where('status', 'inactive')->update(['status' => 'paused']);
        DB::statement("ALTER TYPE clients_status_enum RENAME TO clients_status_enum_old");
        DB::statement("CREATE TYPE clients_status_enum AS ENUM ('active', 'paused', 'prospect', 'churned')");
        DB::statement("ALTER TABLE clients ALTER COLUMN status TYPE clients_status_enum USING status::text::clients_status_enum");
        DB::statement("DROP TYPE clients_status_enum_old");
    }
};
