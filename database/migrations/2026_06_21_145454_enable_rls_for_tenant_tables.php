<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tables = DB::select("
            SELECT table_name 
            FROM information_schema.columns 
            WHERE column_name = 'organization_id' 
            AND table_schema = 'public'
        ");

        foreach ($tables as $table) {
            $tableName = $table->table_name;
            DB::statement("ALTER TABLE \"{$tableName}\" ENABLE ROW LEVEL SECURITY;");
            DB::statement("ALTER TABLE \"{$tableName}\" FORCE ROW LEVEL SECURITY;");

            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON \"{$tableName}\";");

            DB::statement("
                CREATE POLICY tenant_isolation_policy ON \"{$tableName}\"
                FOR ALL
                USING (
                    current_setting('app.bypass_rls', true) = 'on'
                    OR organization_id::text = current_setting('app.current_tenant_id', true)::text
                );
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tables = DB::select("
            SELECT table_name 
            FROM information_schema.columns 
            WHERE column_name = 'organization_id' 
            AND table_schema = 'public'
        ");

        foreach ($tables as $table) {
            $tableName = $table->table_name;
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON \"{$tableName}\";");
            DB::statement("ALTER TABLE \"{$tableName}\" NO FORCE ROW LEVEL SECURITY;");
            DB::statement("ALTER TABLE \"{$tableName}\" DISABLE ROW LEVEL SECURITY;");
        }
    }
};
