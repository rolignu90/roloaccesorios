<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at');
            $table->string('opened_by', 100)->nullable();
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->text('opening_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_by', 100)->nullable();
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('sales_total', 12, 2)->default(0);
            $table->decimal('cash_sales_total', 12, 2)->default(0);
            $table->decimal('card_sales_total', 12, 2)->default(0);
            $table->decimal('transfer_sales_total', 12, 2)->default(0);
            $table->decimal('other_sales_total', 12, 2)->default(0);
            $table->decimal('cash_in_total', 12, 2)->default(0);
            $table->decimal('cash_out_total', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'opened_at'], 'cash_sessions_status_idx');
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();
            $table->string('type', 10); // in | out
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_session_id')
                ->nullable()
                ->after('channel')
                ->constrained('cash_sessions')
                ->nullOnDelete();
            $table->decimal('amount_received', 12, 2)->nullable()->after('cash_session_id');
            $table->decimal('change_given', 12, 2)->nullable()->after('amount_received');
            $table->index(['channel', 'sold_at'], 'sales_channel_sold_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_channel_sold_idx');
            $table->dropConstrainedForeignId('cash_session_id');
            $table->dropColumn(['amount_received', 'change_given']);
        });
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
    }
};
