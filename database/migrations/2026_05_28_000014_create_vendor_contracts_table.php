<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vendor_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->enum('type', ['freelancer', 'tool', 'saas', 'service', 'infrastructure', 'other'])->default('other');
            $table->decimal('monthly_cost', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->enum('billing_cycle', ['monthly', 'annual', 'one_time'])->default('monthly');
            $table->unsignedTinyInteger('billing_day')->nullable(); // day of month
            $table->string('website')->nullable();
            $table->string('contact_email')->nullable();
            $table->enum('status', ['active', 'paused', 'cancelled'])->default('active');
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['organization_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('vendor_contracts'); }
};
