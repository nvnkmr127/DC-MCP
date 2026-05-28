<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('one_on_one_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('manager_id');
            $table->uuid('member_id');
            $table->date('meeting_date');
            $table->text('wins')->nullable();
            $table->text('challenges')->nullable();
            $table->jsonb('action_items')->default('[]');
            $table->enum('mood', ['great', 'good', 'neutral', 'concerned', 'struggling'])->nullable();
            $table->date('next_meeting_date')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['organization_id', 'member_id', 'meeting_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_on_one_notes');
    }
};
