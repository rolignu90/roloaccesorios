<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    public const TYPE_ENTRADA = 'entrada';

    public const TYPE_VENTA = 'venta';

    public const TYPE_ANULACION_VENTA = 'anulacion_venta';

    public const TYPE_AJUSTE = 'ajuste';

    public const TYPE_ANULACION_ENTRADA = 'anulacion_entrada';

    public const TYPE_CONSIGNACION = 'consignacion';

    public const TYPE_DEVOLUCION_CONSIGNACION = 'devolucion_consignacion';

    public const TYPE_ANULACION_CONSIGNACION = 'anulacion_consignacion';

    public const TYPES = [
        self::TYPE_ENTRADA => 'Entrada',
        self::TYPE_VENTA => 'Venta',
        self::TYPE_ANULACION_VENTA => 'Anulación venta',
        self::TYPE_AJUSTE => 'Ajuste / rectificación',
        self::TYPE_ANULACION_ENTRADA => 'Anulación entrada',
        self::TYPE_CONSIGNACION => 'Consignación',
        self::TYPE_DEVOLUCION_CONSIGNACION => 'Devolución consignación',
        self::TYPE_ANULACION_CONSIGNACION => 'Anulación consignación',
    ];

    protected $fillable = [
        'product_id',
        'inventory_lot_id',
        'type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'occurred_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isInbound(): bool
    {
        return $this->quantity > 0;
    }
}
