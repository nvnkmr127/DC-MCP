<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('client_onboardings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->enum('stage', [
                'prospect_won',
                'kickoff_scheduled',
                'kickoff_done',
                'access_shared',
                'sow_signed',
                'first_deliverable_sent',
                'active'
            ])->default('prospect_won');
            $table->jsonb('checklist')->nullable();  // array of {title, done, due_date}
            $table->text('notes')->nullable();
            $table->date('target_go_live')->nullable();
            $table->date('actual_go_live')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->integer('nps_score')->nullable();  // 0-10
            $table->text('nps_comment')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->unique(['organization_id', 'client_id']);
            $table->index(['organization_id', 'stage']);
        });
    }
    public function down(): void { Schema::dropIfExists('client_onboardings'); }
};
