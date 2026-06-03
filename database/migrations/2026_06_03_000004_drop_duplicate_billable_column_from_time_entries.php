<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migration 000008 created is_billable (correct name, matches model).
        // Migration 000016 added a duplicate 'billable' column.
        // This migration consolidates: copy any differing 'billable' values into
        // 'is_billable', then drop 'billable' and 'billing_status' (unused) and
        // 'timer_started_at' (use timesheets timer flow instead).

        if (!Schema::hasColumn('time_entries', 'billable')) {
            return; // already clean
        }

        // Sync: if is_billable differs from billable, trust billable (the newer column)
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('UPDATE time_entries SET is_billable = billable WHERE is_billable != billable');
        } else {
            DB::statement('UPDATE time_entries SET is_billable = billable WHERE is_billable IS DISTINCT FROM billable');
        }

        Schema::table('time_entries', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('time_entries', 'billable')) {
                $cols[] = 'billable';
            }
            if (Schema::hasColumn('time_entries', 'billing_status')) {
                $cols[] = 'billing_status';
            }
            if (Schema::hasColumn('time_entries', 'timer_started_at')) {
                $cols[] = 'timer_started_at';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->boolean('billable')->default(true)->after('description');
            $table->enum('billing_status', ['unbilled', 'billed', 'written_off'])->default('unbilled')->after('billable');
            $table->timestamp('timer_started_at')->nullable()->after('billing_status');
        });
    }
};
