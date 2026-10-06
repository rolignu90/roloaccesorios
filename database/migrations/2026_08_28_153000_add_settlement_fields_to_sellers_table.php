<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('type', 20)->default('external')->after('is_active');
            $table->decimal('salary_amount', 12, 2)->nullable()->after('type');
            $table->decimal('commission_percent', 8, 2)->nullable()->after('salary_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['type', 'salary_amount', 'commission_percent']);
        });
    }
};
