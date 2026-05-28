<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_communications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->uuid('user_id');
            $table->enum('type', ['call', 'email', 'whatsapp', 'meeting', 'linkedin', 'other']);
            $table->string('contact_person')->nullable();
            $table->string('subject');
            $table->text('notes');
            $table->string('outcome')->nullable();
            $table->string('next_action')->nullable();
            $table->date('next_action_date')->nullable();
            $table->timestamp('communicated_at');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['organization_id', 'client_id', 'communicated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_communications');
    }
};
