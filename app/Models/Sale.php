<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_VOIDED = 'voided';

    public const SISTRACK_PENDING = 'pending';

    public const SISTRACK_SENT = 'sent';

    public const SISTRACK_FAILED = 'failed';

    public const CHANNEL_CRM = 'crm';

    public const CHANNEL_ECOMMERCE = 'ecommerce';

    public const CHANNEL_STORE = 'store';

    public const CHANNEL_LABELS = [
        self::CHANNEL_CRM => 'En línea',
        self::CHANNEL_STORE => 'Tienda',
        self::CHANNEL_ECOMMERCE => 'Web',
    ];

    /** Estados que cuentan para ingresos / márgenes. */
    public const REVENUE_STATUSES = [
        self::STATUS_CONFIRMED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_DELIVERED,
    ];

    /** Estados visibles en on-demand (excluye anuladas). */
    public const ON_DEMAND_STATUSES = [
        self::STATUS_CONFIRMED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_DELIVERED,
        self::STATUS_RETURNED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_CONFIRMED => 'Confirmada',
        self::STATUS_IN_TRANSIT => 'En ruta',
        self::STATUS_DELIVERED => 'Entregada',
        self::STATUS_RETURNED => 'Devolución',
        self::STATUS_VOIDED => 'Anulada',
    ];

    /**
     * Rank for non-degrading sync: higher wins.
     *
     * @var array<string, int>
     */
    public const STATUS_RANK = [
        self::STATUS_CONFIRMED => 1,
        self::STATUS_IN_TRANSIT => 2,
        self::STATUS_DELIVERED => 3,
        self::STATUS_RETURNED => 3,
        self::STATUS_VOIDED => 99,
    ];

    protected $fillable = [
        'number',
        'customer_id',
        'seller_id',
        'sold_at',
        'status',
        'status_changed_at',
        'subtotal_without_vat',
        'discount_percent',
        'discount_amount',
        'taxable_base',
        'vat_rate',
        'vat_amount',
        'shipping_amount',
        'has_shipping',
        'shipping_carrier_id',
        'carrier_shipping_cost',
        'carrier_commission_amount',
        'total',
        'cogs_total',
        'gross_margin',
        'payment_method',
        'notes',
        'channel',
        'cash_session_id',
        'store_id',
        'created_by_user_id',
        'voided_by_user_id',
        'amount_received',
        'change_given',
        'external_order_id',
        'sistrack_status',
        'sistrack_external_id',
        'sistrack_order_id',
        'sistrack_recipient_id',
        'sistrack_last_attempt_at',
        'sistrack_last_error',
        'sistrack_shipping_status',
        'sistrack_status_synced_at',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'sistrack_last_attempt_at' => 'datetime',
            'sistrack_status_synced_at' => 'datetime',
            'subtotal_without_vat' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_base' => 'decimal:2',
            'vat_rate' => 'decimal:4',
            'vat_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'has_shipping' => 'boolean',
            'carrier_shipping_cost' => 'decimal:2',
            'carrier_commission_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'cogs_total' => 'decimal:2',
            'gross_margin' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'change_given' => 'decimal:2',
        ];
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    /**
     * Without sales.view_all a user only sees sales of their linked seller or that they created.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->hasPermission('sales.view_all')) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user) {
            $inner->where('created_by_user_id', $user?->id ?? 0);
            if ($user?->seller_id) {
                $inner->orWhere('seller_id', $user->seller_id);
            }
        });
    }

    public function isVisibleTo(?User $user): bool
    {
        return static::query()->whereKey($this->id)->visibleTo($user)->exists();
    }

    public function isStoreSale(): bool
    {
        return $this->channel === self::CHANNEL_STORE;
    }

    public function channelLabel(): string
    {
        if ($this->isStoreSale() && $this->store) {
            return $this->store->name;
        }

        return self::CHANNEL_LABELS[$this->channel] ?? (string) $this->channel;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function shippingCarrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SellerSettlementItem::class);
    }

    public function scopeRevenue(Builder $query): Builder
    {
        return $query->whereIn('status', self::REVENUE_STATUSES);
    }

    public function scopeOnDemandVisible(Builder $query): Builder
    {
        return $query->whereIn('status', self::ON_DEMAND_STATUSES);
    }

    public function scopeStuckInTransit(Builder $query, int $days = 7): Builder
    {
        $cutoff = now()->subDays($days);

        return $query
            ->where('status', self::STATUS_IN_TRANSIT)
            ->where(function (Builder $inner) use ($cutoff) {
                $inner->where(function (Builder $q) use ($cutoff) {
                    $q->whereNotNull('status_changed_at')
                        ->where('status_changed_at', '<=', $cutoff);
                })->orWhere(function (Builder $q) use ($cutoff) {
                    $q->whereNull('status_changed_at')
                        ->whereNotNull('sistrack_status_synced_at')
                        ->where('sistrack_status_synced_at', '<=', $cutoff);
                })->orWhere(function (Builder $q) use ($cutoff) {
                    $q->whereNull('status_changed_at')
                        ->whereNull('sistrack_status_synced_at')
                        ->where('sold_at', '<=', $cutoff);
                });
            });
    }

    /**
     * Enviadas a Sistrack y aún sin estado final (pendientes de sync).
     */
    public function scopePendingSistrackStatusSync(Builder $query): Builder
    {
        return $query
            ->where('sistrack_status', self::SISTRACK_SENT)
            ->whereNotNull('sistrack_external_id')
            ->where('status', '!=', self::STATUS_VOIDED)
            ->whereNotIn('status', [
                self::STATUS_DELIVERED,
                self::STATUS_RETURNED,
            ]);
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isInTransit(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isReturned(): bool
    {
        return $this->status === self::STATUS_RETURNED;
    }

    public function canMarkReturned(): bool
    {
        return ! $this->isVoided()
            && ! $this->isReturned()
            && $this->canProgressToStatus(self::STATUS_RETURNED);
    }

    public function canMarkDelivered(): bool
    {
        return ! $this->isVoided()
            && ! $this->isDelivered()
            && $this->canProgressToStatus(self::STATUS_DELIVERED);
    }

    public function isOpenForRevenue(): bool
    {
        return in_array($this->status, self::REVENUE_STATUSES, true);
    }

    public function isFinalShippingStatus(): bool
    {
        return in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_RETURNED,
            self::STATUS_VOIDED,
        ], true);
    }

    public function canSyncSistrackStatus(): bool
    {
        return $this->isSistrackSent()
            && filled($this->sistrack_external_id)
            && ! $this->isVoided();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_CONFIRMED, self::STATUS_DELIVERED => 'badge-ok',
            self::STATUS_IN_TRANSIT => 'badge-warn',
            self::STATUS_RETURNED, self::STATUS_VOIDED => 'badge-off',
            default => 'badge-warn',
        };
    }

    public function inTransitSince(): ?CarbonInterface
    {
        if (! $this->isInTransit()) {
            return null;
        }

        return $this->status_changed_at
            ?? $this->sistrack_status_synced_at
            ?? $this->sold_at;
    }

    public function isStuckInTransit(int $days = 7): bool
    {
        if (! $this->isInTransit()) {
            return false;
        }

        $since = $this->inTransitSince();

        return $since !== null && $since->lte(now()->subDays($days));
    }

    /**
     * Whether applying $newStatus would be a non-degrading progression.
     */
    public function canProgressToStatus(string $newStatus): bool
    {
        if ($this->isVoided()) {
            return false;
        }

        $currentRank = self::STATUS_RANK[$this->status] ?? 0;
        $newRank = self::STATUS_RANK[$newStatus] ?? 0;

        if ($newRank <= 0) {
            return false;
        }

        // Same terminal family (delivered <-> returned) may switch either way.
        if ($currentRank === 3 && $newRank === 3 && $this->status !== $newStatus) {
            return true;
        }

        return $newRank > $currentRank;
    }

    public function isSistrackSent(): bool
    {
        return $this->sistrack_status === self::SISTRACK_SENT;
    }

    public function canSendToSistrack(): bool
    {
        return $this->isConfirmed()
            && ! $this->isSistrackSent()
            && $this->isEligibleForSistrackPush();
    }

    /**
     * Ya se envió y se puede crear otra etiqueta (p. ej. si la borraron en Sistrack).
     */
    public function canResendToSistrack(): bool
    {
        return $this->isSistrackSent()
            && $this->isEligibleForSistrackPush();
    }

    public function hasSistrackLabel(): bool
    {
        return $this->isSistrackSent()
            && filled($this->sistrack_external_id)
            && ! $this->isVoided();
    }

    public function isEligibleForSistrackPush(): bool
    {
        $this->loadMissing('shippingCarrier');

        return ! $this->isVoided()
            && $this->has_shipping
            && (bool) $this->shippingCarrier?->supportsSistrack();
    }

    /**
     * Transferencias ya se cobraron; Sistrack no debe pedir contra entrega.
     */
    public function isPaidBeforeShipping(): bool
    {
        return $this->payment_method === 'transfer';
    }

    /**
     * Monto que Sistrack debe mostrar / cobrar al entregar.
     */
    public function sistrackCollectAmount(): float
    {
        if ($this->isPaidBeforeShipping()) {
            return 0.0;
        }

        return round((float) $this->total, 2);
    }

    public function sistrackStatusLabel(): string
    {
        if (! $this->has_shipping) {
            return '—';
        }

        $this->loadMissing('shippingCarrier');
        if (! $this->shippingCarrier?->sistrack_enabled && ! $this->isSistrackSent()) {
            return 'Sin Sistrack';
        }

        return match ($this->sistrack_status) {
            self::SISTRACK_SENT => 'Enviado a Sistrack',
            self::SISTRACK_FAILED => 'Falló envío Sistrack',
            self::SISTRACK_PENDING => 'Pendiente Sistrack',
            default => 'Sin enviar a Sistrack',
        };
    }

    /** Productos c/IVA (sin incluir envío cobrado al cliente). */
    public function productsTotalWithVat(): float
    {
        return round((float) $this->total - (float) $this->shipping_amount, 2);
    }

    /** Margen bruto s/IVA = base gravada − COGS (solo producto). */
    public function grossMarginWithoutVat(): float
    {
        return round((float) $this->gross_margin, 2);
    }

    /** Margen bruto c/IVA = productos c/IVA − COGS (solo producto, sin envío). */
    public function grossMarginWithVat(): float
    {
        return round($this->productsTotalWithVat() - (float) $this->cogs_total, 2);
    }

    /** Costo total de la empresa de envío (su tarifa + comisión COD). */
    public function carrierCostTotal(): float
    {
        return round((float) $this->carrier_shipping_cost + (float) $this->carrier_commission_amount, 2);
    }

    /**
     * Diferencia entre lo cobrado al cliente por envío y lo que cobra la empresa por el envío
     * (sin incluir comisión COD).
     */
    public function shippingSpread(): float
    {
        return round((float) $this->shipping_amount - (float) $this->carrier_shipping_cost, 2);
    }

    /**
     * Margen real s/IVA:
     * margen producto s/IVA + envío cobrado al cliente − costo carrier (envío + COD).
     */
    public function realMarginWithoutVat(): float
    {
        return round(
            $this->grossMarginWithoutVat()
            + (float) $this->shipping_amount
            - $this->carrierCostTotal(),
            2
        );
    }

    /**
     * Margen real c/IVA:
     * total cobrado (productos + envío cliente) − COGS − costo carrier (envío + COD).
     */
    public function realMarginWithVat(): float
    {
        return round(
            (float) $this->total - (float) $this->cogs_total - $this->carrierCostTotal(),
            2
        );
    }
}
