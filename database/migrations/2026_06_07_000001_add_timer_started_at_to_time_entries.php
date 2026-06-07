<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('time_entries', 'timer_started_at')) {
                $table->timestamp('timer_started_at')->nullable()->after('logged_date');
                $table->index('timer_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            if (Schema::hasColumn('time_entries', 'timer_started_at')) {
                $table->dropIndex(['timer_started_at']);
                $table->dropColumn('timer_started_at');
            }
        });
    }
};

