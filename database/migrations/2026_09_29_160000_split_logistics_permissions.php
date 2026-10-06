<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LOGISTICS_ALL = [
        'logistics.view', 'logistics.create', 'logistics.void', 'logistics.clients', 'logistics.settlements',
    ];

    public function up(): void
    {
        Schema::table('logistics_shipments', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by_user_id')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('logistics_settlements', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('roles')->get(['id', 'name', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            $original = $permissions;

            if (in_array('logistics.manage', $permissions, true)) {
                $permissions = array_merge(array_diff($permissions, ['logistics.manage']), self::LOGISTICS_ALL);
            }
            if ($role->name === 'Contador') {
                $permissions = array_merge($permissions, ['logistics.view', 'logistics.settlements']);
            }

            $permissions = array_values(array_unique($permissions));
            if ($permissions !== $original) {
                DB::table('roles')->where('id', $role->id)->update([
                    'permissions' => json_encode($permissions),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! DB::table('roles')->where('name', 'Logística / despacho')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Logística / despacho',
                'description' => 'Crea envíos a terceros, los manda a Sistrack e imprime etiquetas. Sin anular ni liquidar.',
                'permissions' => json_encode(['logistics.view', 'logistics.create']),
                'is_admin' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('roles')->get(['id', 'permissions']) as $role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            if (array_intersect($permissions, self::LOGISTICS_ALL) === []) {
                continue;
            }
            $permissions = array_values(array_unique(array_merge(array_diff($permissions, self::LOGISTICS_ALL), ['logistics.manage'])));
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        }

        Schema::table('logistics_settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
        });
        Schema::table('logistics_shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
