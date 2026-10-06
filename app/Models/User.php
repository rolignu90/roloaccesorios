<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role_id',
        'seller_id',
        'is_active',
        'must_change_password',
        'pos_pin',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'pos_pin',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'pos_pin' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->role?->is_admin;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && (bool) $this->role?->allows($permission);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Active stores this user may operate. Admins and cash.view_all see every store.
     *
     * @return Collection<int, Store>
     */
    public function accessibleStores(): Collection
    {
        $query = Store::query()->active()->orderBy('name');

        if (! $this->hasPermission('cash.view_all')) {
            $query->whereIn('id', $this->stores()->pluck('stores.id'));
        }

        return $query->get();
    }

    public function canAccessStore(?Store $store): bool
    {
        if (! $store) {
            return false;
        }

        return $this->hasPermission('cash.view_all')
            || $this->stores()->whereKey($store->id)->exists();
    }

    public function checkPosPin(string $pin): bool
    {
        return $this->pos_pin !== null && Hash::check($pin, $this->pos_pin);
    }
}
