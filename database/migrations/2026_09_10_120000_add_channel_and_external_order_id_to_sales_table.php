<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'channel')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('channel', 32)->default('crm')->after('notes');
                $table->index('channel');
            });
        }

        if (! Schema::hasColumn('sales', 'external_order_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('external_order_id')->nullable()->after('channel');
                $table->unique('external_order_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'external_order_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropUnique(['external_order_id']);
                $table->dropColumn('external_order_id');
            });
        }

        if (Schema::hasColumn('sales', 'channel')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropIndex(['channel']);
                $table->dropColumn('channel');
            });
        }
    }
};
