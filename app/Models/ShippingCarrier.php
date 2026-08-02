<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingCarrier extends Model
{
    use HasFactory;

    public const COMMISSION_FIXED = 'fixed';

    public const COMMISSION_PERCENT = 'percent';

    public const COMMISSION_TYPES = [
        self::COMMISSION_FIXED => 'Monto fijo (USD)',
        self::COMMISSION_PERCENT => '% del total c/IVA',
    ];

    protected $fillable = [
        'name',
        'code',
        'shipping_cost',
        'commission_type',
        'commission_value',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shipping_cost' => 'decimal:2',
            'commission_value' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function commissionTypeLabel(): string
    {
        return self::COMMISSION_TYPES[$this->commission_type] ?? $this->commission_type;
    }

    /**
     * Commission for collecting cash (COD), based on sale total with VAT.
     */
    public function commissionForTotal(float $totalWithVat): float
    {
        $totalWithVat = max(0, $totalWithVat);

        if ($this->commission_type === self::COMMISSION_PERCENT) {
            $percent = max(0, (float) $this->commission_value);

            return round($totalWithVat * ($percent / 100), 2);
        }

        return round(max(0, (float) $this->commission_value), 2);
    }

    public function costsForTotal(float $totalWithVat): array
    {
        $shippingCost = round(max(0, (float) $this->shipping_cost), 2);
        $commission = $this->commissionForTotal($totalWithVat);

        return [
            'shipping_cost' => $shippingCost,
            'commission_amount' => $commission,
            'total_cost' => round($shippingCost + $commission, 2),
        ];
    }

    public function rateSummary(): string
    {
        $parts = ['Envío '.money($this->shipping_cost)];

        if ($this->commission_type === self::COMMISSION_PERCENT) {
            $parts[] = 'COD '.rtrim(rtrim(number_format((float) $this->commission_value, 4, '.', ''), '0'), '.').'%';
        } else {
            $parts[] = 'COD '.money($this->commission_value);
        }

        return implode(' · ', $parts);
    }
}
