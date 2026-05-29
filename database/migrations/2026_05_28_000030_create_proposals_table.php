<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->uuid('created_by')->nullable();
            $table->string('title');
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired'])->default('draft');
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_value', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('public_token', 80)->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
