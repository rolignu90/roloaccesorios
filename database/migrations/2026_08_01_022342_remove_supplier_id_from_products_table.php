<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $products = DB::table('products')
            ->whereNotNull('supplier_id')
            ->get(['id', 'supplier_id']);

        foreach ($products as $product) {
            $exists = DB::table('product_suppliers')
                ->where('product_id', $product->id)
                ->where('supplier_id', $product->supplier_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('product_suppliers')->insert([
                'product_id' => $product->id,
                'supplier_id' => $product->supplier_id,
                'purchase_price' => 0,
                'is_preferred' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('description')->constrained()->nullOnDelete();
        });

        $preferred = DB::table('product_suppliers')
            ->where('is_preferred', true)
            ->get(['product_id', 'supplier_id']);

        foreach ($preferred as $row) {
            DB::table('products')
                ->where('id', $row->product_id)
                ->update(['supplier_id' => $row->supplier_id]);
        }
    }
};
