<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_clients', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->string('commission_type', 20)->default('fixed'); // fixed | percent
            $table->decimal('commission_value', 12, 4)->default(0);
            $table->foreignId('default_shipping_carrier_id')
                ->nullable()
                ->constrained('shipping_carriers')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('logistics_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('logistics_client_id')->constrained('logistics_clients')->cascadeOnDelete();
            $table->foreignId('shipping_carrier_id')->constrained('shipping_carriers')->restrictOnDelete();
            $table->timestamp('shipped_at');
            $table->string('status', 20)->default('confirmed');
            $table->timestamp('status_changed_at')->nullable();

            $table->string('recipient_name');
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_address');
            $table->string('department')->nullable();
            $table->string('municipality')->nullable();
            $table->string('country')->default('El Salvador');

            $table->string('description');
            $table->decimal('collect_amount', 12, 2)->default(0);

            $table->decimal('carrier_shipping_cost', 12, 2)->default(0);
            $table->decimal('carrier_commission_amount', 12, 2)->default(0);
            $table->decimal('service_commission', 12, 2)->default(0);
            $table->decimal('payable_to_client', 12, 2)->default(0);

            $table->text('notes')->nullable();

            $table->string('sistrack_status', 20)->default('pending');
            $table->string('sistrack_external_id')->nullable();
            $table->string('sistrack_order_id')->nullable();
            $table->string('sistrack_recipient_id')->nullable();
            $table->timestamp('sistrack_last_attempt_at')->nullable();
            $table->text('sistrack_last_error')->nullable();
            $table->string('sistrack_shipping_status')->nullable();
            $table->timestamp('sistrack_status_synced_at')->nullable();

            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['logistics_client_id', 'status', 'shipped_at'], 'logistics_shipments_client_status_idx');
            $table->index(['status', 'status_changed_at'], 'logistics_shipments_status_changed_idx');
            $table->index(['sistrack_status', 'status'], 'logistics_shipments_sistrack_idx');
        });

        Schema::create('logistics_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('logistics_client_id')->constrained('logistics_clients')->cascadeOnDelete();
            $table->string('period_type', 20);
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('shipments_count')->default(0);
            $table->unsignedInteger('returns_count')->default(0);
            $table->decimal('collect_total', 12, 2)->default(0);
            $table->decimal('carrier_shipping_total', 12, 2)->default(0);
            $table->decimal('carrier_commission_total', 12, 2)->default(0);
            $table->decimal('service_commission_total', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->string('status', 20)->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['logistics_client_id', 'period_from', 'period_to'], 'logistics_settlements_period_idx');
            $table->index(['status', 'paid_at'], 'logistics_settlements_status_idx');
        });

        Schema::create('logistics_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_settlement_id')->constrained('logistics_settlements')->cascadeOnDelete();
            $table->foreignId('logistics_shipment_id')->constrained('logistics_shipments')->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->decimal('collect_amount', 12, 2)->default(0);
            $table->decimal('carrier_shipping_cost', 12, 2)->default(0);
            $table->decimal('carrier_commission_amount', 12, 2)->default(0);
            $table->decimal('service_commission', 12, 2)->default(0);
            $table->decimal('payable_to_client', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['logistics_settlement_id', 'logistics_shipment_id'], 'logistics_settlement_shipment_unique');
            $table->index(['logistics_shipment_id', 'item_type'], 'logistics_settlement_items_shipment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_settlement_items');
        Schema::dropIfExists('logistics_settlements');
        Schema::dropIfExists('logistics_shipments');
        Schema::dropIfExists('logistics_clients');
    }
};
