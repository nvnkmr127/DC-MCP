<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('campaign_budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->uuid('project_id')->nullable();
            $table->enum('channel', ['meta_ads', 'google_ads', 'seo', 'email', 'linkedin', 'twitter', 'youtube', 'other'])->default('other');
            $table->string('month_year', 7); // YYYY-MM
            $table->decimal('allocated_budget', 12, 2);
            $table->decimal('spent_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->unique(['organization_id', 'client_id', 'channel', 'month_year']);
            $table->index(['organization_id', 'client_id', 'month_year']);
        });
    }
    public function down(): void { Schema::dropIfExists('campaign_budgets'); }
};
