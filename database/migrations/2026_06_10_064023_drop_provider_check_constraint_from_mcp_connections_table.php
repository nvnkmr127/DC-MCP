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
        if (config('database.default') !== 'sqlite' && DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE mcp_connections DROP CONSTRAINT IF EXISTS mcp_connections_provider_check');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-adding the check constraint would limit it back to the original ENUM values
        if (config('database.default') !== 'sqlite' && DB::getDriverName() !== 'sqlite') {
             DB::statement("ALTER TABLE mcp_connections ADD CONSTRAINT mcp_connections_provider_check CHECK (provider::text = ANY (ARRAY['google_calendar'::text, 'gmail'::text, 'google_drive'::text, 'notion'::text, 'zoho_cliq'::text, 'meta_ads'::text, 'make'::text, 'whatsapp'::text, 'slack'::text, 'hubspot'::text]))");
        }
    }
};
