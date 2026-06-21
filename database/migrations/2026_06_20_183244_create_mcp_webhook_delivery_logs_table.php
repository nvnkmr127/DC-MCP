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
        Schema::create('mcp_webhook_delivery_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id')->index();
            $table->string('event_type');
            $table->string('endpoint_url');
            $table->json('request_payload');
            $table->integer('response_status')->default(0);
            $table->text('response_body')->nullable();
            $table->integer('duration_ms')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_webhook_delivery_logs');
    }
};
