<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('seller_type', 20);
            $table->string('period_type', 20);
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedInteger('returns_count')->default(0);
            $table->decimal('sales_total', 12, 2)->default(0);
            $table->decimal('sales_cogs', 12, 2)->default(0);
            $table->decimal('sales_real_margin_with_vat', 12, 2)->default(0);
            $table->decimal('returns_cogs', 12, 2)->default(0);
            $table->decimal('returns_carrier_cost', 12, 2)->default(0);
            $table->decimal('returns_cost_total', 12, 2)->default(0);
            $table->decimal('salary_amount', 12, 2)->default(0);
            $table->decimal('commission_percent', 8, 2)->nullable();
            $table->decimal('commission_base', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->string('status', 20)->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'period_from', 'period_to']);
            $table->index(['status', 'paid_at']);
        });

        Schema::create('seller_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_settlement_id')->constrained('seller_settlements')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->decimal('sale_total', 12, 2)->default(0);
            $table->decimal('cogs_total', 12, 2)->default(0);
            $table->decimal('real_margin_with_vat', 12, 2)->default(0);
            $table->decimal('carrier_cost_total', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['seller_settlement_id', 'sale_id']);
            $table->index(['sale_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_settlement_items');
        Schema::dropIfExists('seller_settlements');
    }
};
