<?php

namespace App\Http\Resources\Api\Ecommerce;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stock = (int) ($this->stock_on_hand ?? $this->stockOnHand());

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'unit' => $this->unit,
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'price_with_vat' => $this->effectiveSalePriceWithVat(),
            'regular_price_with_vat' => $this->salePriceWithVat(),
            'promo_active' => $this->hasActivePromo(),
            'promo_label' => $this->promoBadgeLabel(),
            'stock' => $stock,
            'on_demand' => (bool) $this->on_demand,
            'free_shipping' => (bool) $this->free_shipping,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
