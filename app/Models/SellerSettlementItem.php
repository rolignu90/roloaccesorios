<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerSettlementItem extends Model
{
    use HasFactory;

    public const TYPE_SALE = 'sale';

    public const TYPE_RETURN = 'return';

    protected $fillable = [
        'seller_settlement_id',
        'sale_id',
        'item_type',
        'sale_total',
        'cogs_total',
        'real_margin_with_vat',
        'carrier_cost_total',
    ];

    protected function casts(): array
    {
        return [
            'sale_total' => 'decimal:2',
            'cogs_total' => 'decimal:2',
            'real_margin_with_vat' => 'decimal:2',
            'carrier_cost_total' => 'decimal:2',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SellerSettlement::class, 'seller_settlement_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
