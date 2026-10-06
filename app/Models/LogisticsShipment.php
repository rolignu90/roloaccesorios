<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsShipment extends Model
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

    public const STATUS_LABELS = [
        self::STATUS_CONFIRMED => 'Confirmado',
        self::STATUS_IN_TRANSIT => 'En ruta',
        self::STATUS_DELIVERED => 'Entregado',
        self::STATUS_RETURNED => 'Devolución',
        self::STATUS_VOIDED => 'Anulado',
    ];

    /**
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
        'logistics_client_id',
        'shipping_carrier_id',
        'shipped_at',
        'status',
        'status_changed_at',
        'recipient_name',
        'recipient_phone',
        'recipient_email',
        'recipient_address',
        'department',
        'municipality',
        'country',
        'description',
        'collect_amount',
        'carrier_shipping_cost',
        'carrier_commission_amount',
        'service_commission',
        'payable_to_client',
        'notes',
        'sistrack_status',
        'sistrack_external_id',
        'sistrack_order_id',
        'sistrack_recipient_id',
        'sistrack_last_attempt_at',
        'sistrack_last_error',
        'sistrack_shipping_status',
        'sistrack_status_synced_at',
        'voided_at',
        'created_by_user_id',
        'voided_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'voided_at' => 'datetime',
            'sistrack_last_attempt_at' => 'datetime',
            'sistrack_status_synced_at' => 'datetime',
            'collect_amount' => 'decimal:2',
            'carrier_shipping_cost' => 'decimal:2',
            'carrier_commission_amount' => 'decimal:2',
            'service_commission' => 'decimal:2',
            'payable_to_client' => 'decimal:2',
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

    public function shippingCarrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class);
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(LogisticsSettlementItem::class);
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

    public function isSistrackSent(): bool
    {
        return $this->sistrack_status === self::SISTRACK_SENT;
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

    public function carrierCostTotal(): float
    {
        return round((float) $this->carrier_shipping_cost + (float) $this->carrier_commission_amount, 2);
    }

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

        if ($currentRank === 3 && $newRank === 3 && $this->status !== $newStatus) {
            return true;
        }

        return $newRank > $currentRank;
    }

    public function canMarkDelivered(): bool
    {
        return ! $this->isVoided()
            && ! $this->isDelivered()
            && $this->canProgressToStatus(self::STATUS_DELIVERED);
    }

    public function scopeSettled(Builder $query, bool $settled = true): Builder
    {
        $constraint = fn ($q) => $q->whereHas('settlement', fn ($s) => $s->where('status', '!=', LogisticsSettlement::STATUS_VOIDED));

        return $settled
            ? $query->whereHas('settlementItems', $constraint)
            : $query->whereDoesntHave('settlementItems', $constraint);
    }

    public function activeSettlement(): ?LogisticsSettlement
    {
        if (! $this->relationLoaded('settlementItems')) {
            return null;
        }

        return $this->settlementItems
            ->map(fn ($item) => $item->settlement)
            ->first(fn ($settlement) => $settlement && $settlement->status !== LogisticsSettlement::STATUS_VOIDED);
    }

    public function isSettled(): bool
    {
        if ($this->relationLoaded('settlementItems')) {
            return $this->activeSettlement() !== null;
        }

        return $this->settlementItems()
            ->whereHas('settlement', fn ($q) => $q->where('status', '!=', LogisticsSettlement::STATUS_VOIDED))
            ->exists();
    }

    /**
     * Delivered shipments carry COD owed to the client, so only settlement voiding can undo them.
     */
    public function canVoid(): bool
    {
        return ! $this->isVoided()
            && ! $this->isDelivered()
            && ! $this->isSettled();
    }

    public function canEdit(): bool
    {
        return ! $this->isVoided()
            && ! $this->isDelivered()
            && ! $this->isReturned()
            && ! $this->isSettled();
    }

    public function canMarkReturned(): bool
    {
        return ! $this->isVoided()
            && ! $this->isReturned()
            && $this->canProgressToStatus(self::STATUS_RETURNED);
    }

    public function isEligibleForSistrackPush(): bool
    {
        $this->loadMissing('shippingCarrier');

        return (bool) $this->shippingCarrier?->supportsSistrack();
    }

    public function canSendToSistrack(): bool
    {
        return $this->isConfirmed()
            && ! $this->isSistrackSent()
            && $this->isEligibleForSistrackPush();
    }

    public function canResendToSistrack(): bool
    {
        return $this->isSistrackSent()
            && $this->isEligibleForSistrackPush();
    }

    public function canSyncSistrackStatus(): bool
    {
        return $this->isSistrackSent()
            && filled($this->sistrack_external_id)
            && ! $this->isVoided();
    }

    public function hasSistrackLabel(): bool
    {
        return $this->isSistrackSent()
            && filled($this->sistrack_external_id)
            && ! $this->isVoided();
    }

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

    public function inTransitSince(): ?CarbonInterface
    {
        if (! $this->isInTransit()) {
            return null;
        }

        return $this->status_changed_at
            ?? $this->sistrack_status_synced_at
            ?? $this->shipped_at;
    }

    /**
     * neto = COD − comisión Sistrack − flete − comisión ROLO
     */
    public static function computePayable(
        float $collectAmount,
        float $carrierShippingCost,
        float $carrierCommissionAmount,
        float $serviceCommission,
    ): float {
        return round(max(
            0,
            max(0, $collectAmount)
            - max(0, $carrierCommissionAmount)
            - max(0, $carrierShippingCost)
            - max(0, $serviceCommission)
        ), 2);
    }
}
