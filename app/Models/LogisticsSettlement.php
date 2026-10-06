<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsSettlement extends Model
{
    use HasFactory;

    public const PERIOD_WEEK = 'week';

    public const PERIOD_MONTH = 'month';

    public const PERIOD_CUSTOM = 'custom';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOIDED = 'voided';

    public const PERIOD_LABELS = [
        self::PERIOD_WEEK => 'Semanal',
        self::PERIOD_MONTH => 'Mensual',
        self::PERIOD_CUSTOM => 'Personalizado',
    ];

    protected $fillable = [
        'number',
        'logistics_client_id',
        'period_type',
        'period_from',
        'period_to',
        'shipments_count',
        'returns_count',
        'collect_total',
        'carrier_shipping_total',
        'carrier_commission_total',
        'service_commission_total',
        'amount_due',
        'status',
        'paid_at',
        'payment_method',
        'notes',
        'created_by_user_id',
        'voided_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'paid_at' => 'datetime',
            'collect_total' => 'decimal:2',
            'carrier_shipping_total' => 'decimal:2',
            'carrier_commission_total' => 'decimal:2',
            'service_commission_total' => 'decimal:2',
            'amount_due' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(LogisticsClient::class, 'logistics_client_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LogisticsSettlementItem::class);
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function periodLabel(): string
    {
        return self::PERIOD_LABELS[$this->period_type] ?? (string) $this->period_type;
    }
}
