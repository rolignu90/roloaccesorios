<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('party_type', 20);
            $table->foreignId('seller_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('delivered_at');
            $table->string('status', 20)->default('open');
            $table->decimal('total_with_vat', 14, 2)->default(0);
            $table->decimal('returned_with_vat', 14, 2)->default(0);
            $table->decimal('paid_with_vat', 14, 2)->default(0);
            $table->decimal('balance_with_vat', 14, 2)->default(0);
            $table->decimal('cogs_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'delivered_at']);
            $table->index('party_type');
        });

        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_returned')->default(0);
            $table->decimal('unit_price_with_vat', 12, 2);
            $table->decimal('unit_price_without_vat', 12, 2);
            $table->decimal('line_total_with_vat', 14, 2);
            $table->decimal('cogs_total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('consignment_lot_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_returned')->default(0);
            $table->decimal('purchase_price', 12, 2);
            $table->decimal('cogs_amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('consignment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->dateTime('paid_at');
            $table->decimal('amount', 14, 2);
            $table->string('method', 40)->default('cash');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('consignment_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->dateTime('returned_at');
            $table->decimal('total_with_vat', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('consignment_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consignment_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_with_vat', 12, 2);
            $table->decimal('line_total_with_vat', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_return_items');
        Schema::dropIfExists('consignment_returns');
        Schema::dropIfExists('consignment_payments');
        Schema::dropIfExists('consignment_lot_allocations');
        Schema::dropIfExists('consignment_items');
        Schema::dropIfExists('consignments');
    }
};
