<?php

namespace App\Http\Resources\Api\Ecommerce;

use App\Models\Combo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Combo */
class ComboResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $catalog = $this->resource instanceof Combo
            ? $this->resource->toSaleCatalogEntry()
            : (array) $this->resource;

        return [
            'id' => $catalog['id'] ?? null,
            'code' => $catalog['code'] ?? null,
            'name' => $catalog['name'] ?? null,
            'price_with_vat' => isset($catalog['price']) ? (float) $catalog['price'] : null,
            'stock' => isset($catalog['stock']) ? (int) $catalog['stock'] : null,
            'on_demand' => (bool) ($catalog['on_demand'] ?? false),
            'free_shipping' => (bool) ($catalog['free_shipping'] ?? false),
            'items' => collect($catalog['items'] ?? [])->map(fn (array $item) => [
                'product_id' => $item['product_id'] ?? null,
                'product_code' => $item['code'] ?? null,
                'product_name' => $item['name'] ?? null,
                'quantity' => (int) ($item['quantity'] ?? 0),
                'ref_price_with_vat' => isset($item['ref_price']) ? (float) $item['ref_price'] : null,
                'stock' => isset($item['stock']) ? (int) $item['stock'] : null,
                'on_demand' => (bool) ($item['on_demand'] ?? false),
            ])->values()->all(),
        ];
    }
}
