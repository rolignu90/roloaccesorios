<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'number',
        'store_id',
        'cashier_id',
        'opened_by_user_id',
        'closed_by_user_id',
        'status',
        'opened_at',
        'opened_by',
        'opening_amount',
        'opening_notes',
        'closed_at',
        'closed_by',
        'sales_count',
        'sales_total',
        'cash_sales_total',
        'card_sales_total',
        'transfer_sales_total',
        'other_sales_total',
        'cash_in_total',
        'cash_out_total',
        'expected_cash',
        'counted_cash',
        'difference',
        'closing_notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'sales_total' => 'decimal:2',
            'cash_sales_total' => 'decimal:2',
            'card_sales_total' => 'decimal:2',
            'transfer_sales_total' => 'decimal:2',
            'other_sales_total' => 'decimal:2',
            'cash_in_total' => 'decimal:2',
            'cash_out_total' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'cashier_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public static function currentFor(Store $store): ?self
    {
        return static::query()->open()->where('store_id', $store->id)->latest('opened_at')->first();
    }
}
