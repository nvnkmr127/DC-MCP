<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // The original migration declared client_id NOT NULL with a CASCADE foreign key.
            // Internal projects (briefings, ops work) have no client — allow null.
            $table->dropForeign(['client_id']);
            $table->uuid('client_id')->nullable()->change();
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->uuid('client_id')->nullable(false)->change();
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }
};
