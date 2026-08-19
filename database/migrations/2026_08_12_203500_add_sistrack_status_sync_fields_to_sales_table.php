<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('sistrack_shipping_status')->nullable()->after('sistrack_last_error');
            $table->timestamp('sistrack_status_synced_at')->nullable()->after('sistrack_shipping_status');
            $table->timestamp('status_changed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'sistrack_shipping_status',
                'sistrack_status_synced_at',
                'status_changed_at',
            ]);
        });
    }
};
