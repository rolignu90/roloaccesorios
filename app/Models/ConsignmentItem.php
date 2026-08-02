<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsignmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'consignment_id',
        'product_id',
        'quantity',
        'quantity_returned',
        'unit_price_with_vat',
        'unit_price_without_vat',
        'line_total_with_vat',
        'cogs_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_returned' => 'integer',
            'unit_price_with_vat' => 'decimal:2',
            'unit_price_without_vat' => 'decimal:2',
            'line_total_with_vat' => 'decimal:2',
            'cogs_total' => 'decimal:2',
        ];
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lotAllocations(): HasMany
    {
        return $this->hasMany(ConsignmentLotAllocation::class);
    }

    public function quantityOutstanding(): int
    {
        return max(0, (int) $this->quantity - (int) $this->quantity_returned);
    }

    public function outstandingTotalWithVat(): float
    {
        return round($this->quantityOutstanding() * (float) $this->unit_price_with_vat, 2);
    }
}
