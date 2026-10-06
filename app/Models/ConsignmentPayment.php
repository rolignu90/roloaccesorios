<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'consignment_id',
        'seller_settlement_id',
        'paid_at',
        'amount',
        'method',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function sellerSettlement(): BelongsTo
    {
        return $this->belongsTo(SellerSettlement::class);
    }
}
