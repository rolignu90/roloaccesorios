<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsignmentReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'consignment_id',
        'returned_at',
        'total_with_vat',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'total_with_vat' => 'decimal:2',
        ];
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentReturnItem::class);
    }
}
