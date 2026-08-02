<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'consignment_return_id',
        'consignment_item_id',
        'quantity',
        'unit_price_with_vat',
        'line_total_with_vat',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_with_vat' => 'decimal:2',
            'line_total_with_vat' => 'decimal:2',
        ];
    }

    public function consignmentReturn(): BelongsTo
    {
        return $this->belongsTo(ConsignmentReturn::class);
    }

    public function consignmentItem(): BelongsTo
    {
        return $this->belongsTo(ConsignmentItem::class);
    }
}
