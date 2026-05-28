<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('upsell_flagged')->default(false)->after('health_status');
            $table->text('upsell_notes')->nullable()->after('upsell_flagged');
            $table->decimal('upsell_potential', 12, 2)->nullable()->after('upsell_notes');
            $table->timestamp('upsell_flagged_at')->nullable()->after('upsell_potential');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['upsell_flagged', 'upsell_notes', 'upsell_potential', 'upsell_flagged_at']);
        });
    }
};
