<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProductPriceTier extends Model
{
    use HasFactory;

    protected $table = 'customer_product_price_tiers';

    protected $fillable = [
        'customer_id',
        'product_id',
        'min_quantity',
        'unit_price_without_vat',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'unit_price_without_vat' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitPriceWithVat(): float
    {
        return price_with_vat($this->unit_price_without_vat);
    }
}
