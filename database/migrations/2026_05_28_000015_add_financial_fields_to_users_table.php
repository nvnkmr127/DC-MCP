<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('monthly_salary', 12, 2)->nullable()->after('role');
            $table->decimal('billable_rate', 8, 2)->nullable()->after('monthly_salary'); // per hour
            $table->string('bank_account_last4', 4)->nullable()->after('billable_rate');
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['monthly_salary', 'billable_rate', 'bank_account_last4']);
        });
    }
};
