<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsSettlementItem extends Model
{
    use HasFactory;

    public const TYPE_SHIPMENT = 'shipment';

    public const TYPE_RETURN = 'return';

    protected $fillable = [
        'logistics_settlement_id',
        'logistics_shipment_id',
        'item_type',
        'collect_amount',
        'carrier_shipping_cost',
        'carrier_commission_amount',
        'service_commission',
        'payable_to_client',
    ];

    protected function casts(): array
    {
        return [
            'collect_amount' => 'decimal:2',
            'carrier_shipping_cost' => 'decimal:2',
            'carrier_commission_amount' => 'decimal:2',
            'service_commission' => 'decimal:2',
            'payable_to_client' => 'decimal:2',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(LogisticsSettlement::class, 'logistics_settlement_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(LogisticsShipment::class, 'logistics_shipment_id');
    }
}
