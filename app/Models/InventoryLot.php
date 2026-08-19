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
        'amount_paid',
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
            'amount_paid' => 'decimal:2',
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

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function isDepleted(): bool
    {
        return $this->quantity_remaining <= 0;
    }

    /** Costo total de la compra del lote. */
    public function purchaseCost(): float
    {
        return round((int) $this->quantity_received * (float) $this->purchase_price, 2);
    }

    public function balanceDue(): float
    {
        return round(max(0, $this->purchaseCost() - (float) $this->amount_paid), 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->balanceDue() <= 0.009;
    }
}
