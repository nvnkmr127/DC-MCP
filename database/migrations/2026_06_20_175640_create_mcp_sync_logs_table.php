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
        Schema::create('mcp_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mcp_connection_id')->constrained('mcp_connections')->onDelete('cascade');
            $table->string('status')->index(); // 'success' or 'failed'
            $table->integer('duration_ms')->nullable();
            $table->integer('records_processed')->default(0);
            $table->integer('bytes_transferred')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            // Helpful indices for diagnostics
            $table->index(['mcp_connection_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_sync_logs');
    }
};
