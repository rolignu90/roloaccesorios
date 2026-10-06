<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use RuntimeException;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'document_type',
        'document_number',
        'email',
        'phone',
        'address',
        'municipality',
        'department',
        'country',
        'postal_code',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function returnedSales(): HasMany
    {
        return $this->hasMany(Sale::class)->where('status', Sale::STATUS_RETURNED);
    }

    /** Normaliza teléfono SV a últimos 8 dígitos para comparar. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';
        if ($digits === '') {
            return null;
        }

        return strlen($digits) >= 8 ? substr($digits, -8) : $digits;
    }

    public function normalizedPhone(): ?string
    {
        return static::normalizePhone($this->phone);
    }

    /**
     * Clientes con al menos una venta en devolución.
     */
    public function scopeWithReturns($query)
    {
        return $query->whereHas('sales', fn ($q) => $q->where('status', Sale::STATUS_RETURNED));
    }

    public function productPriceTiers(): HasMany
    {
        return $this->hasMany(CustomerProductPriceTier::class);
    }

    public function linkedSeller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }

    public static function nextCode(): string
    {
        $max = static::query()
            ->where('code', 'like', 'CLI-%')
            ->whereRaw("code REGEXP '^CLI-[0-9]+$'")
            ->selectRaw('MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)) as max_seq')
            ->value('max_seq');

        return 'CLI-'.str_pad((string) (((int) $max) + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a customer allocating the next CLI-#### code, retrying on unique collisions.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithNextCode(array $attributes): self
    {
        $attempts = 0;

        while ($attempts < 8) {
            $attempts++;

            try {
                return static::query()->create(array_merge($attributes, [
                    'code' => static::nextCode(),
                ]));
            } catch (QueryException $e) {
                if (! static::isUniqueCodeViolation($e) || $attempts >= 8) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('No se pudo asignar un código de cliente único.');
    }

    private static function isUniqueCodeViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            && str_contains(strtolower($e->getMessage()), 'customers_code_unique');
    }
}
