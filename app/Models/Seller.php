<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seller extends Model
{
    use HasFactory;

    public const TYPE_EXTERNAL = 'external';

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_LABELS = [
        self::TYPE_EXTERNAL => 'Externo',
        self::TYPE_INTERNAL => 'Interno',
    ];

    protected $fillable = [
        'code',
        'sale_prefix',
        'name',
        'phone',
        'email',
        'notes',
        'is_active',
        'type',
        'salary_amount',
        'commission_percent',
        'customer_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'salary_amount' => 'decimal:2',
            'commission_percent' => 'decimal:2',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(SellerSettlement::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isExternal(): bool
    {
        return $this->type === self::TYPE_EXTERNAL;
    }

    public function isInternal(): bool
    {
        return $this->type === self::TYPE_INTERNAL;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? (string) $this->type;
    }

    public static function nextCode(): string
    {
        $max = static::query()
            ->where('code', 'like', 'VEN-%')
            ->selectRaw('MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)) as max_seq')
            ->value('max_seq');

        return 'VEN-'.str_pad((string) (((int) $max) + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function normalizeSalePrefix(?string $prefix): ?string
    {
        $prefix = strtoupper(trim((string) $prefix));
        if ($prefix === '') {
            return null;
        }

        $prefix = preg_replace('/[^A-Z0-9\-]/', '', $prefix) ?? '';
        $prefix = trim($prefix, '-');

        if ($prefix === '') {
            return null;
        }

        return $prefix.'-';
    }

    public function saleNumberPrefix(): string
    {
        return static::normalizeSalePrefix($this->sale_prefix) ?? 'V-';
    }
}
