<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->unique()->after('role_id')->constrained('sellers')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('seller_id');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->string('pos_pin')->nullable()->after('must_change_password');
            $table->timestamp('last_login_at')->nullable()->after('pos_pin');
        });

        Schema::create('store_user', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['store_id', 'user_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('store_id')->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->foreignId('opened_by_user_id')->nullable()->after('cashier_id')->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->after('opened_by_user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('cash_session_id')->constrained('users')->nullOnDelete();
        });

        foreach (Permissions::defaultRoles() as $role) {
            DB::table('roles')->insert([
                'name' => $role['name'],
                'description' => $role['description'],
                'permissions' => json_encode($role['permissions']),
                'is_admin' => $role['is_admin'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('cash_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by_user_id');
            $table->dropConstrainedForeignId('opened_by_user_id');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
        });
        Schema::dropIfExists('store_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'is_active', 'must_change_password', 'pos_pin', 'last_login_at']);
        });
        Schema::dropIfExists('roles');
    }
};
