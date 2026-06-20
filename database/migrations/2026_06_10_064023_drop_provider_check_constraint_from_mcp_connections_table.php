<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support DROP CONSTRAINT; recreate the table without the provider CHECK
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement('ALTER TABLE mcp_connections RENAME TO mcp_connections_old');

            DB::statement('CREATE TABLE "mcp_connections" (
                "id" varchar not null,
                "organization_id" varchar not null,
                "user_id" varchar,
                "provider" varchar not null,
                "name" varchar not null,
                "status" varchar check ("status" in (\'active\', \'disconnected\', \'error\', \'pending\')) not null default \'pending\',
                "credentials" text,
                "scopes" text,
                "last_synced_at" datetime,
                "sync_error" text,
                "settings" text,
                "created_at" datetime,
                "updated_at" datetime,
                "deleted_at" datetime,
                "label" varchar,
                "is_active" tinyint(1) not null default \'1\',
                foreign key("organization_id") references "organizations"("id") on delete cascade,
                foreign key("user_id") references "users"("id") on delete set null,
                primary key ("id")
            )');

            DB::statement('INSERT INTO mcp_connections SELECT * FROM mcp_connections_old');
            DB::statement('DROP TABLE mcp_connections_old');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('ALTER TABLE mcp_connections DROP CONSTRAINT IF EXISTS mcp_connections_provider_check');
        }
    }

    public function down(): void
    {
        // Reversing this is not practical; intentionally left as no-op
    }
};
