<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->nullable()->unique();
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->string('commission_type', 20)->default('fixed'); // fixed | percent
            $table->decimal('commission_value', 12, 4)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('shipping_carrier_id')
                ->nullable()
                ->after('has_shipping')
                ->constrained('shipping_carriers')
                ->nullOnDelete();
            $table->decimal('carrier_shipping_cost', 12, 2)->default(0)->after('shipping_carrier_id');
            $table->decimal('carrier_commission_amount', 12, 2)->default(0)->after('carrier_shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_carrier_id');
            $table->dropColumn(['carrier_shipping_cost', 'carrier_commission_amount']);
        });

        Schema::dropIfExists('shipping_carriers');
    }
};
