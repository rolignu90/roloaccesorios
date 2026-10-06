<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('commission_percent')
                ->constrained('customers')
                ->nullOnDelete();
            $table->unique('customer_id');
        });

        Schema::table('seller_settlements', function (Blueprint $table) {
            $table->foreignId('linked_customer_id')
                ->nullable()
                ->after('seller_id')
                ->constrained('customers')
                ->nullOnDelete();
            $table->decimal('applied_to_consignments', 12, 2)->default(0)->after('amount_due');
            $table->decimal('cash_paid', 12, 2)->default(0)->after('applied_to_consignments');
        });

        Schema::table('consignment_payments', function (Blueprint $table) {
            $table->foreignId('seller_settlement_id')
                ->nullable()
                ->after('consignment_id')
                ->constrained('seller_settlements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consignment_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_settlement_id');
        });

        Schema::table('seller_settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_customer_id');
            $table->dropColumn(['applied_to_consignments', 'cash_paid']);
        });

        Schema::table('sellers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
