<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->uuid('sent_by')->nullable();
            $table->string('public_token', 80)->unique();
            $table->tinyInteger('nps_score')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('responded_at')->nullable();
            $table->enum('status', ['sent', 'responded', 'expired'])->default('sent');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_surveys');
    }
};
