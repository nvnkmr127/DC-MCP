<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('title');
            $table->enum('category', ['tools', 'freelancer', 'office', 'ads', 'travel', 'hardware', 'other'])->default('other');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->date('expense_date');
            $table->string('vendor')->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_url')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurrence', ['monthly', 'annual', 'one_time'])->default('one_time');
            $table->uuid('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['organization_id', 'expense_date']);
            $table->index(['organization_id', 'category']);
        });
    }
    public function down(): void { Schema::dropIfExists('expenses'); }
};
