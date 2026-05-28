<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mcp_connections', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('organization_id');
            $blueprint->uuid('user_id')->nullable();
            $blueprint->enum('provider', [
                'google_calendar', 'gmail', 'google_drive', 'notion', 'zoho_cliq',
                'meta_ads', 'make', 'whatsapp', 'slack', 'hubspot'
            ]);
            $blueprint->string('name');
            $blueprint->enum('status', ['active', 'disconnected', 'error', 'pending'])->default('pending');
            $blueprint->jsonb('credentials')->nullable(); // stored encrypted
            $blueprint->jsonb('scopes')->nullable();
            $blueprint->timestamp('last_synced_at')->nullable();
            $blueprint->text('sync_error')->nullable();
            $blueprint->jsonb('settings')->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();

            $blueprint->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $blueprint->index('organization_id');
            $blueprint->index('provider');
            $blueprint->index('status');
        });

        Schema::create('mcp_sync_logs', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('mcp_connection_id');
            $blueprint->enum('direction', ['inbound', 'outbound']);
            $blueprint->string('entity_type');
            $blueprint->string('entity_id')->nullable();
            $blueprint->enum('status', ['success', 'failed', 'partial', 'skipped']);
            $blueprint->integer('records_processed')->default(0);
            $blueprint->integer('records_failed')->default(0);
            $blueprint->jsonb('payload')->nullable();
            $blueprint->text('error_message')->nullable();
            $blueprint->integer('duration_ms')->default(0);
            $blueprint->timestamp('synced_at')->useCurrent();

            $blueprint->foreign('mcp_connection_id')->references('id')->on('mcp_connections')->onDelete('cascade');

            $blueprint->index('mcp_connection_id');
            $blueprint->index('status');
            $blueprint->index('synced_at');
        });

        Schema::create('mcp_webhook_events', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('mcp_connection_id')->nullable();
            $blueprint->string('provider');
            $blueprint->string('event_type');
            $blueprint->jsonb('payload')->nullable();
            $blueprint->string('signature')->nullable();
            $blueprint->enum('status', ['received', 'processing', 'processed', 'failed'])->default('received');
            $blueprint->timestamp('processed_at')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('mcp_connection_id')->references('id')->on('mcp_connections')->onDelete('cascade');

            $blueprint->index('mcp_connection_id');
            $blueprint->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_webhook_events');
        Schema::dropIfExists('mcp_sync_logs');
        Schema::dropIfExists('mcp_connections');
    }
};
