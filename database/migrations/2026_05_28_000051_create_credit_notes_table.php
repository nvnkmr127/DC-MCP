<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('invoice_id')->nullable();
            $table->uuid('client_id');
            $table->uuid('created_by')->nullable();
            $table->string('credit_note_number')->unique();
            $table->date('issue_date');
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->enum('status', ['draft', 'issued', 'applied'])->default('draft');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
