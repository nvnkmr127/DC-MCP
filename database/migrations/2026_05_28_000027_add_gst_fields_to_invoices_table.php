<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('client_gstin', 20)->nullable()->after('client_id');
            $table->string('agency_gstin', 20)->nullable()->after('client_gstin');
            $table->decimal('gst_rate', 5, 2)->default(18.00)->after('agency_gstin');
            $table->decimal('gst_amount', 12, 2)->default(0)->after('gst_rate');
            $table->string('hsn_code', 20)->nullable()->after('gst_amount');
            $table->enum('supply_type', ['intra', 'inter'])->default('intra')->after('hsn_code');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['client_gstin', 'agency_gstin', 'gst_rate', 'gst_amount', 'hsn_code', 'supply_type']);
        });
    }
};
