<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerSettlement extends Model
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
        'seller_id',
        'linked_customer_id',
        'seller_type',
        'period_type',
        'period_from',
        'period_to',
        'sales_count',
        'returns_count',
        'sales_total',
        'sales_cogs',
        'sales_real_margin_with_vat',
        'returns_cogs',
        'returns_carrier_cost',
        'returns_cost_total',
        'salary_amount',
        'commission_percent',
        'commission_base',
        'commission_amount',
        'amount_due',
        'applied_to_consignments',
        'cash_paid',
        'status',
        'paid_at',
        'payment_method',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'paid_at' => 'datetime',
            'sales_total' => 'decimal:2',
            'sales_cogs' => 'decimal:2',
            'sales_real_margin_with_vat' => 'decimal:2',
            'returns_cogs' => 'decimal:2',
            'returns_carrier_cost' => 'decimal:2',
            'returns_cost_total' => 'decimal:2',
            'salary_amount' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'commission_base' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'applied_to_consignments' => 'decimal:2',
            'cash_paid' => 'decimal:2',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function linkedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'linked_customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SellerSettlementItem::class);
    }

    public function consignmentPayments(): HasMany
    {
        return $this->hasMany(ConsignmentPayment::class);
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function periodLabel(): string
    {
        return self::PERIOD_LABELS[$this->period_type] ?? (string) $this->period_type;
    }

    public function sellerTypeLabel(): string
    {
        return Seller::TYPE_LABELS[$this->seller_type] ?? (string) $this->seller_type;
    }
}
