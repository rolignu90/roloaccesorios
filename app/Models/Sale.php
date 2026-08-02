<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'number',
        'customer_id',
        'seller_id',
        'sold_at',
        'status',
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
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
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
        ];
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

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
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
