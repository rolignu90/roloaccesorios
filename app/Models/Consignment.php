<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consignment extends Model
{
    use HasFactory;

    public const PARTY_SELLER = 'seller';

    public const PARTY_CUSTOMER = 'customer';

    public const STATUS_OPEN = 'open';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_VOIDED = 'voided';

    public const STATUSES = [
        self::STATUS_OPEN => 'Abierta',
        self::STATUS_PARTIAL => 'Parcial',
        self::STATUS_SETTLED => 'Liquidada',
        self::STATUS_VOIDED => 'Anulada',
    ];

    protected $fillable = [
        'number',
        'party_type',
        'seller_id',
        'customer_id',
        'delivered_at',
        'status',
        'total_with_vat',
        'returned_with_vat',
        'paid_with_vat',
        'balance_with_vat',
        'cogs_total',
        'notes',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'voided_at' => 'datetime',
            'total_with_vat' => 'decimal:2',
            'returned_with_vat' => 'decimal:2',
            'paid_with_vat' => 'decimal:2',
            'balance_with_vat' => 'decimal:2',
            'cogs_total' => 'decimal:2',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ConsignmentPayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ConsignmentReturn::class);
    }

    public function partyName(): string
    {
        if ($this->party_type === self::PARTY_SELLER) {
            return $this->seller?->name ?? '—';
        }

        return $this->customer?->name ?? '—';
    }

    public function partyLabel(): string
    {
        $type = $this->party_type === self::PARTY_SELLER ? 'Vendedor' : 'Cliente';

        return $type.': '.$this->partyName();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function isOpenBalance(): bool
    {
        return ! $this->isVoided() && (float) $this->balance_with_vat > 0.009;
    }

    public function canReceivePayment(): bool
    {
        return $this->isOpenBalance();
    }

    public function canReturn(): bool
    {
        if ($this->isVoided()) {
            return false;
        }

        return $this->items->contains(fn (ConsignmentItem $item) => $item->quantityOutstanding() > 0);
    }

    public function canVoid(): bool
    {
        return ! $this->isVoided() && (float) $this->paid_with_vat <= 0.009;
    }
}
