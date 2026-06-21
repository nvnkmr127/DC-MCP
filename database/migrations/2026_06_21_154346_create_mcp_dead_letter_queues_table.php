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
        Schema::create('mcp_dead_letter_queues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mcp_connection_id')->index();
            $table->string('provider');
            $table->text('error_message');
            $table->longText('exception_trace')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('failed_at')->useCurrent();
            $table->timestamps();
            
            $table->foreign('mcp_connection_id')
                  ->references('id')
                  ->on('mcp_connections')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_dead_letter_queues');
    }
};
