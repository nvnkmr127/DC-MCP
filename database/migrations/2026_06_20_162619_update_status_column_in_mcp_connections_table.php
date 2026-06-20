<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the constraint that restricts the 'status' column.
        // We will manage this at the application layer via Enums moving forward.
        DB::statement('ALTER TABLE mcp_connections DROP CONSTRAINT IF EXISTS mcp_connections_status_check');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // To reverse, we add the original check constraint back.
        DB::statement("ALTER TABLE mcp_connections ADD CONSTRAINT mcp_connections_status_check CHECK (status::text = ANY (ARRAY['active'::character varying, 'disconnected'::character varying, 'error'::character varying, 'pending'::character varying]::text[]))");
    }
};
