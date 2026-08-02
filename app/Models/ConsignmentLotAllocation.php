<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentLotAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'consignment_item_id',
        'inventory_lot_id',
        'quantity',
        'quantity_returned',
        'purchase_price',
        'cogs_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_returned' => 'integer',
            'purchase_price' => 'decimal:2',
            'cogs_amount' => 'decimal:2',
        ];
    }

    public function consignmentItem(): BelongsTo
    {
        return $this->belongsTo(ConsignmentItem::class);
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    public function quantityOutstanding(): int
    {
        return max(0, (int) $this->quantity - (int) $this->quantity_returned);
    }
}
