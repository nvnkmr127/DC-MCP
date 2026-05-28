<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->enum('source', ['referral', 'inbound', 'outbound', 'cold', 'event', 'social'])->default('inbound');
            $table->enum('stage', ['lead', 'meeting_scheduled', 'proposal_sent', 'negotiation', 'won', 'lost'])->default('lead');
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->unsignedTinyInteger('probability')->default(20);
            $table->jsonb('services_interested')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->string('lost_reason')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('converted_client_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['organization_id', 'stage']);
        });

        Schema::create('prospect_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('prospect_id');
            $table->uuid('organization_id');
            $table->uuid('user_id')->nullable();
            $table->enum('type', ['call', 'email', 'meeting', 'proposal', 'follow_up', 'note'])->default('note');
            $table->text('note');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('prospect_id')->references('id')->on('prospects')->onDelete('cascade');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index('prospect_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_activities');
        Schema::dropIfExists('prospects');
    }
};
