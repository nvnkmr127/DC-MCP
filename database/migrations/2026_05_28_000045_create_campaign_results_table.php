<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('campaign_budget_id');
            $table->uuid('client_id');
            $table->date('report_date');
            $table->decimal('impressions', 15, 0)->default(0);
            $table->decimal('clicks', 12, 0)->default(0);
            $table->decimal('conversions', 10, 0)->default(0);
            $table->decimal('spend', 12, 2)->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('cpc', 10, 2)->default(0);
            $table->decimal('roas', 8, 4)->default(0);
            $table->string('platform')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_results');
    }
};
