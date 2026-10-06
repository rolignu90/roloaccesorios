<?php

namespace App\Services;

use App\Models\CustomerProductPriceTier;
use App\Models\Product;

class CustomerPricingService
{
    /**
     * Resolve unit price with VAT for a customer + product + quantity.
     * Priority: customer tier (best matching min_quantity) → wholesale → effective sale.
     */
    public function resolveUnitPriceWithVat(?int $customerId, Product $product, int $quantity): float
    {
        $quantity = max(1, $quantity);

        if ($customerId) {
            $tier = CustomerProductPriceTier::query()
                ->where('customer_id', $customerId)
                ->where('product_id', $product->id)
                ->where('min_quantity', '<=', $quantity)
                ->orderByDesc('min_quantity')
                ->first();

            if ($tier) {
                return $tier->unitPriceWithVat();
            }
        }

        $wholesale = $product->wholesalePriceWithVat();
        if ($wholesale !== null) {
            return $wholesale;
        }

        return $product->effectiveSalePriceWithVat();
    }

    /**
     * Keys are strings so JS lookups by select value always match.
     *
     * @return array<string, array<string, list<array{min:int, price:float}>>>
     */
    public function catalogForJs(): array
    {
        $grouped = [];

        CustomerProductPriceTier::query()
            ->orderBy('customer_id')
            ->orderBy('product_id')
            ->orderBy('min_quantity')
            ->get()
            ->each(function (CustomerProductPriceTier $tier) use (&$grouped) {
                $customerKey = (string) $tier->customer_id;
                $productKey = (string) $tier->product_id;
                $grouped[$customerKey][$productKey][] = [
                    'min' => (int) $tier->min_quantity,
                    'price' => round($tier->unitPriceWithVat(), 2),
                ];
            });

        return $grouped;
    }
}
