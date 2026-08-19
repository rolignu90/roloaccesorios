<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignment_lot_allocations', function (Blueprint $table) {
            $table->dropForeign(['inventory_lot_id']);
        });

        DB::statement('ALTER TABLE consignment_lot_allocations MODIFY inventory_lot_id BIGINT UNSIGNED NULL');

        Schema::table('consignment_lot_allocations', function (Blueprint $table) {
            $table->foreign('inventory_lot_id')
                ->references('id')
                ->on('inventory_lots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consignment_lot_allocations', function (Blueprint $table) {
            $table->dropForeign(['inventory_lot_id']);
        });

        DB::statement('ALTER TABLE consignment_lot_allocations MODIFY inventory_lot_id BIGINT UNSIGNED NOT NULL');

        Schema::table('consignment_lot_allocations', function (Blueprint $table) {
            $table->foreign('inventory_lot_id')
                ->references('id')
                ->on('inventory_lots')
                ->restrictOnDelete();
        });
    }
};
