<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_retainers', function (Blueprint $table) {
            if (!\Schema::hasColumn('client_retainers', 'renewal_date')) {
                $table->date('renewal_date')->nullable()->after('end_date');
            }
            if (!\Schema::hasColumn('client_retainers', 'renewal_alert_days')) {
                $table->tinyInteger('renewal_alert_days')->default(30);
            }
            if (!\Schema::hasColumn('client_retainers', 'renewal_status')) {
                $table->enum('renewal_status', ['active', 'renewed', 'churned', 'pending'])->default('active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_retainers', function (Blueprint $table) {
            $table->dropColumnIfExists('renewal_date');
            $table->dropColumnIfExists('renewal_alert_days');
            $table->dropColumnIfExists('renewal_status');
        });
    }
};
