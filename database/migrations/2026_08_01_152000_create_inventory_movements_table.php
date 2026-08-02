<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }

    private function backfill(): void
    {
        $now = now();

        $lots = DB::table('inventory_lots')->orderBy('id')->get();
        foreach ($lots as $lot) {
            DB::table('inventory_movements')->insert([
                'product_id' => $lot->product_id,
                'inventory_lot_id' => $lot->id,
                'type' => 'entrada',
                'quantity' => (int) $lot->quantity_received,
                'unit_cost' => $lot->purchase_price,
                'reference_type' => 'inventory_lot',
                'reference_id' => $lot->id,
                'occurred_at' => $lot->received_at.' 12:00:00',
                'notes' => 'Backfill entrada lote '.$lot->lot_number,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $allocations = DB::table('sale_lot_allocations')
            ->join('sale_items', 'sale_items.id', '=', 'sale_lot_allocations.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->select([
                'sale_lot_allocations.*',
                'sale_items.product_id',
                'sales.id as sale_id',
                'sales.sold_at',
                'sales.voided_at',
                'sales.status',
            ])
            ->orderBy('sale_lot_allocations.id')
            ->get();

        foreach ($allocations as $row) {
            DB::table('inventory_movements')->insert([
                'product_id' => $row->product_id,
                'inventory_lot_id' => $row->inventory_lot_id,
                'type' => 'venta',
                'quantity' => -1 * (int) $row->quantity,
                'unit_cost' => $row->purchase_price,
                'reference_type' => 'sale',
                'reference_id' => $row->sale_id,
                'occurred_at' => $row->sold_at,
                'notes' => 'Backfill salida por venta',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($row->status === 'voided' && $row->voided_at) {
                DB::table('inventory_movements')->insert([
                    'product_id' => $row->product_id,
                    'inventory_lot_id' => $row->inventory_lot_id,
                    'type' => 'anulacion_venta',
                    'quantity' => (int) $row->quantity,
                    'unit_cost' => $row->purchase_price,
                    'reference_type' => 'sale',
                    'reference_id' => $row->sale_id,
                    'occurred_at' => $row->voided_at,
                    'notes' => 'Backfill restauración por anulación',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
