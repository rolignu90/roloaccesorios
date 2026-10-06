<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('ticket_footer')->nullable();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('number')->constrained('stores')->nullOnDelete();
            $table->index(['store_id', 'status'], 'cash_sessions_store_status_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('cash_session_id')->constrained('stores')->nullOnDelete();
        });

        $sellerId = DB::table('sellers')->whereIn('sale_prefix', ['T-', 'T'])->orderBy('id')->value('id');

        $storeId = DB::table('stores')->insertGetId([
            'name' => 'Tienda principal',
            'ticket_footer' => '¡Gracias por su compra!',
            'seller_id' => $sellerId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cash_sessions')->whereNull('store_id')->update(['store_id' => $storeId]);
        DB::table('sales')->where('channel', 'store')->whereNull('store_id')->update(['store_id' => $storeId]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropIndex('cash_sessions_store_status_idx');
            $table->dropConstrainedForeignId('store_id');
        });
        Schema::dropIfExists('stores');
    }
};
