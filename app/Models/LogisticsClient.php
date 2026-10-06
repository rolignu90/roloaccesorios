<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsClient extends Model
{
    use HasFactory;

    public const COMMISSION_FIXED = 'fixed';

    public const COMMISSION_PERCENT = 'percent';

    public const COMMISSION_TYPES = [
        self::COMMISSION_FIXED => 'Monto fijo (USD) por envío',
        self::COMMISSION_PERCENT => '% del COD',
    ];

    protected $fillable = [
        'code',
        'name',
        'phone',
        'email',
        'notes',
        'commission_type',
        'commission_value',
        'default_shipping_carrier_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'commission_value' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function defaultShippingCarrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'default_shipping_carrier_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(LogisticsShipment::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(LogisticsSettlement::class);
    }

    public static function nextCode(): string
    {
        $max = static::query()
            ->where('code', 'like', 'LOG-%')
            ->selectRaw('MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)) as max_seq')
            ->value('max_seq');

        return 'LOG-'.str_pad((string) (((int) $max) + 1), 4, '0', STR_PAD_LEFT);
    }

    public function commissionTypeLabel(): string
    {
        return self::COMMISSION_TYPES[$this->commission_type] ?? $this->commission_type;
    }

    public function serviceCommissionForCollect(float $collectAmount): float
    {
        $collectAmount = max(0, $collectAmount);

        if ($this->commission_type === self::COMMISSION_PERCENT) {
            return round($collectAmount * (max(0, (float) $this->commission_value) / 100), 2);
        }

        return round(max(0, (float) $this->commission_value), 2);
    }

    public function commissionSummary(): string
    {
        if ($this->commission_type === self::COMMISSION_PERCENT) {
            $pct = rtrim(rtrim(number_format((float) $this->commission_value, 4, '.', ''), '0'), '.');

            return $pct.'% del COD';
        }

        return money($this->commission_value).' por envío';
    }
}
