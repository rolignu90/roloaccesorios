<?php

namespace App\Http\Resources\Api\Ecommerce;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sale */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['customer', 'items.product', 'items.combo', 'shippingCarrier']);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'external_order_id' => $this->external_order_id,
            'channel' => $this->channel,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'payment_method' => $this->payment_method,
            'sold_at' => $this->sold_at?->toIso8601String(),
            'subtotal_without_vat' => (float) $this->subtotal_without_vat,
            'taxable_base' => (float) $this->taxable_base,
            'vat_amount' => (float) $this->vat_amount,
            'shipping_amount' => (float) $this->shipping_amount,
            'has_shipping' => (bool) $this->has_shipping,
            'shipping_carrier_id' => $this->shipping_carrier_id,
            'shipping_carrier' => $this->shippingCarrier?->name,
            'total' => (float) $this->total,
            'notes' => $this->notes,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
                'address' => $this->customer->address,
                'department' => $this->customer->department,
                'municipality' => $this->customer->municipality,
            ] : null,
            'items' => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_code' => $item->product?->code,
                'product_name' => $item->product?->name,
                'combo_id' => $item->combo_id,
                'combo_code' => $item->combo?->code,
                'quantity' => (int) $item->quantity,
                'unit_price_without_vat' => (float) $item->unit_price_without_vat,
                'line_total' => (float) $item->line_total,
            ])->values()->all(),
        ];
    }
}
