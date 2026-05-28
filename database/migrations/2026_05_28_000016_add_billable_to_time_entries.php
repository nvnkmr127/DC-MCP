<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->boolean('billable')->default(true)->after('description');
            $table->enum('billing_status', ['unbilled', 'billed', 'written_off'])->default('unbilled')->after('billable');
            $table->timestamp('timer_started_at')->nullable()->after('billing_status');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn(['billable', 'billing_status', 'timer_started_at']);
        });
    }
};
