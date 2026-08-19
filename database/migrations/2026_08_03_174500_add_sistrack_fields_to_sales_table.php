<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('sistrack_status', 20)->nullable()->after('notes');
            $table->string('sistrack_external_id')->nullable()->after('sistrack_status');
            $table->string('sistrack_order_id')->nullable()->after('sistrack_external_id');
            $table->string('sistrack_recipient_id')->nullable()->after('sistrack_order_id');
            $table->timestamp('sistrack_last_attempt_at')->nullable()->after('sistrack_recipient_id');
            $table->text('sistrack_last_error')->nullable()->after('sistrack_last_attempt_at');

            $table->index('sistrack_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['sistrack_status']);
            $table->dropColumn([
                'sistrack_status',
                'sistrack_external_id',
                'sistrack_order_id',
                'sistrack_recipient_id',
                'sistrack_last_attempt_at',
                'sistrack_last_error',
            ]);
        });
    }
};
