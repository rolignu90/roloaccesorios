<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->dateTime('sold_at');
            $table->string('status', 20)->default('confirmed');
            $table->decimal('subtotal_without_vat', 14, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('taxable_base', 14, 2)->default(0);
            $table->decimal('vat_rate', 8, 4)->default(0.13);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('cogs_total', 14, 2)->default(0);
            $table->decimal('gross_margin', 14, 2)->default(0);
            $table->string('payment_method', 40)->default('cash');
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['sold_at', 'status']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
