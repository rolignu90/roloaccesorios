<?php

namespace App\Http\Controllers\Api\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\ShippingCarrier;
use Illuminate\Http\JsonResponse;

class ShippingCarrierController extends Controller
{
    public function index(): JsonResponse
    {
        $carriers = ShippingCarrier::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'code', 'shipping_cost', 'commission_type', 'commission_value']);

        return response()->json([
            'data' => $carriers->map(fn (ShippingCarrier $carrier) => [
                'id' => $carrier->id,
                'name' => $carrier->name,
                'code' => $carrier->code,
                'shipping_cost' => (float) $carrier->shipping_cost,
                'commission_type' => $carrier->commission_type,
                'commission_value' => (float) $carrier->commission_value,
            ])->values()->all(),
        ]);
    }
}
