<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'lot_number',
        'product_id',
        'supplier_id',
        'quantity_received',
        'quantity_remaining',
        'purchase_price',
        'received_at',
        'invoice_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'integer',
            'quantity_remaining' => 'integer',
            'purchase_price' => 'decimal:2',
            'received_at' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function saleLotAllocations(): HasMany
    {
        return $this->hasMany(SaleLotAllocation::class);
    }

    public function isDepleted(): bool
    {
        return $this->quantity_remaining <= 0;
    }
}
