<?php

use App\Models\ShippingCarrier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_carriers', function (Blueprint $table) {
            $table->boolean('sistrack_enabled')->default(false)->after('is_active');
            $table->string('sistrack_base_url')->nullable()->after('sistrack_enabled');
            $table->string('sistrack_email')->nullable()->after('sistrack_base_url');
            $table->text('sistrack_password')->nullable()->after('sistrack_email');
            $table->unsignedBigInteger('sistrack_sender_id')->nullable()->after('sistrack_password');
        });

        $carrier = ShippingCarrier::query()->find(1)
            ?? ShippingCarrier::query()
                ->where(function ($query) {
                    $query->where('name', 'like', '%Express%El%Salvador%')
                        ->orWhere('name', 'like', '%EXPRESS%EL%SALVADOR%');
                })
                ->first();

        if ($carrier) {
            $carrier->forceFill([
                'sistrack_enabled' => true,
                'sistrack_base_url' => env('SISTRACK_BASE_URL', 'https://expresselsalvador.sistrack.net'),
                'sistrack_email' => env('SISTRACK_EMAIL', 'Rolodocumentos@gmail.com'),
                'sistrack_password' => env('SISTRACK_PASSWORD', 'Envios2026'),
                'sistrack_sender_id' => (int) env('SISTRACK_SENDER_ID', 67306),
            ])->save();
        }
    }

    public function down(): void
    {
        Schema::table('shipping_carriers', function (Blueprint $table) {
            $table->dropColumn([
                'sistrack_enabled',
                'sistrack_base_url',
                'sistrack_email',
                'sistrack_password',
                'sistrack_sender_id',
            ]);
        });
    }
};
