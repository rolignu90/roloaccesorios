<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'description',
        'permissions',
        'is_admin',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_admin' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function allows(string $permission): bool
    {
        return $this->is_admin || in_array($permission, $this->permissions ?? [], true);
    }
}
