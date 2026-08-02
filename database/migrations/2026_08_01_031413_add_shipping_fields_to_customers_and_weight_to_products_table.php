<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('municipality')->nullable()->after('address');
            $table->string('department')->nullable()->after('municipality');
            $table->string('country')->default('El Salvador')->after('department');
            $table->string('postal_code', 20)->nullable()->after('country');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('weight', 10, 3)->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['municipality', 'department', 'country', 'postal_code']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
