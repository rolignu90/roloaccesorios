<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('promo_type', 20)->nullable()->after('sale_price_without_vat');
            $table->decimal('promo_value', 12, 2)->nullable()->after('promo_type');
            $table->boolean('promo_active')->default(false)->after('promo_value');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['promo_type', 'promo_value', 'promo_active']);
        });
    }
};
