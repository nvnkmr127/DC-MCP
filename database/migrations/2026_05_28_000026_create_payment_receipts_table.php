<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('invoice_id');
            $table->uuid('client_id');
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->enum('payment_method', ['upi', 'neft', 'rtgs', 'cheque', 'cash', 'card', 'other'])->default('upi');
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
